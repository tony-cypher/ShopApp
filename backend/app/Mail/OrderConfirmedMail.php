<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order '.$this->order->reference.' confirmed — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.order-confirmed',
            with: [
                'order' => $this->order,
                'items' => $this->order->items,
            ],
        );
    }
}
