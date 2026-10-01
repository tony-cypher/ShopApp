<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrderConfirmedMail;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    /**
     * Test-only checkout: validates the card shape (Luhn) but never charges
     * anything and never talks to a payment processor.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'customer.name' => ['required', 'string', 'max:80'],
            'customer.email' => ['required', 'string', 'email', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:30'],
            'delivery_method' => ['required', 'in:standard,pickup'],
            'address.line1' => ['nullable', 'string', 'max:120'],
            'address.city' => ['nullable', 'string', 'max:60'],
            'address.state' => ['nullable', 'string', 'max:60'],
            'address.zip' => ['nullable', 'string', 'max:20'],
            'address.country' => ['nullable', 'string', 'max:60'],
            'payment.cardholder' => ['required', 'string', 'max:80'],
            'payment.card_number' => ['required', 'string', 'max:25'],
            'payment.expiry' => ['required', 'string', 'max:5'],
            'payment.cvc' => ['required', 'string', 'min:3', 'max:4'],
        ]);

        if ($validated['delivery_method'] === 'standard') {
            $request->validate([
                'address.line1' => ['required', 'string', 'max:120'],
                'address.city' => ['required', 'string', 'max:60'],
                'address.state' => ['required', 'string', 'max:60'],
                'address.zip' => ['required', 'string', 'max:20'],
                'address.country' => ['required', 'string', 'max:60'],
            ]);
        }

        $this->assertTestCardIsValid($validated['payment']);

        $order = DB::transaction(function () use ($validated, $request) {
            $ids = array_column($validated['items'], 'product_id');
            $products = Product::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

            $subtotal = 0;
            $lineItems = [];

            foreach ($validated['items'] as $item) {
                $product = $products->get((int) $item['product_id']);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => ['A product in your cart is no longer available.'],
                    ]);
                }

                $quantity = (int) $item['quantity'];

                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => ['Not enough stock for "'.$product->name.'".'],
                    ]);
                }

                if ($validated['delivery_method'] === 'standard' && ! $product->delivery_standard) {
                    throw ValidationException::withMessages([
                        'items' => ['"'.$product->name.'" is not available for standard delivery.'],
                    ]);
                }

                if ($validated['delivery_method'] === 'pickup' && ! $product->delivery_pickup) {
                    throw ValidationException::withMessages([
                        'items' => ['"'.$product->name.'" is not available for pick up.'],
                    ]);
                }

                $subtotal += (float) $product->price * $quantity;
                $lineItems[] = ['product' => $product, 'quantity' => $quantity];
            }

            $subtotal = round($subtotal, 2);
            // Free standard shipping over $100 — pick up is always free.
            $shipping = $validated['delivery_method'] === 'pickup' || $subtotal >= 100 ? 0.0 : 9.99;
            $total = round($subtotal + $shipping, 2);

            $order = Order::create([
                'reference' => 'MLC-'.now()->format('ymd').'-'.strtoupper(Str::random(4)),
                // Checkout is public, but attaches the order to the buyer when
                // they happen to be signed in (token still accepted on public routes).
                'user_id' => $request->user('sanctum')?->id,
                'email' => strtolower($validated['customer']['email']),
                'name' => $validated['customer']['name'],
                'phone' => $validated['customer']['phone'] ?? null,
                'status' => 'confirmed',
                'payment_status' => 'test_paid',
                'payment_method' => 'test_card',
                'card_last4' => substr(preg_replace('/\D/', '', $validated['payment']['card_number']), -4),
                'delivery_method' => $validated['delivery_method'],
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'total' => $total,
                'address' => $validated['delivery_method'] === 'standard'
                    ? array_filter($validated['address'] ?? [])
                    : null,
                'placed_at' => now(),
            ]);

            foreach ($lineItems as $line) {
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'name' => $line['product']->name,
                    'emoji' => $line['product']->emoji,
                    'image_url' => $line['product']->image_url,
                    'price' => $line['product']->price,
                    'quantity' => $line['quantity'],
                ]);

                $line['product']->decrement('stock', $line['quantity']);
            }

            return $order;
        });

        $order->load('items');

        try {
            Mail::to($order->email)->send(new OrderConfirmedMail($order));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return response()->json([
            'data' => $order,
            'message' => 'Order placed — test payment approved. A confirmation email is on its way.',
        ], 201);
    }

    private function assertTestCardIsValid(array $payment): void
    {
        $number = preg_replace('/\D/', '', $payment['card_number']);

        if (strlen($number) < 13 || strlen($number) > 19 || ! $this->passesLuhn($number)) {
            throw ValidationException::withMessages([
                'payment.card_number' => ['That test card number is invalid. Try 4242 4242 4242 4242.'],
            ]);
        }

        if (! preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $payment['expiry'])) {
            throw ValidationException::withMessages([
                'payment.expiry' => ['Use the MM/YY format, e.g. 12/29.'],
            ]);
        }

        [$month, $year] = explode('/', $payment['expiry']);
        $expiresAt = \Carbon\Carbon::createFromDate(2000 + (int) $year, (int) $month)->endOfMonth();

        if ($expiresAt->isPast()) {
            throw ValidationException::withMessages([
                'payment.expiry' => ['That test card is expired.'],
            ]);
        }
    }

    private function passesLuhn(string $number): bool
    {
        $sum = 0;
        $alt = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if ($alt) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $alt = ! $alt;
        }

        return $sum % 10 === 0;
    }
}
