<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'verification_token' => Str::random(48),
        ]);

        $this->sendVerificationMail($user);

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'data' => $this->userPayload($user),
            'token' => $token,
            'message' => 'Account created. We sent a confirmation link to '.$user->email.'.',
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', strtolower($validated['email']))->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Those credentials do not match our records.'],
            ]);
        }

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'data' => $this->userPayload($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userPayload($request->user())]);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:64'],
        ]);

        $user = User::where('verification_token', $validated['token'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'token' => ['This confirmation link is invalid or has expired.'],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->forceFill([
                'email_verified_at' => now(),
                'verification_token' => null,
            ])->save();
        }

        return response()->json([
            'data' => $this->userPayload($user),
            'message' => 'Your email address is confirmed. Welcome aboard!',
        ]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Your email is already confirmed.']);
        }

        $key = 'resend-verification:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages([
                'email' => ['Too many requests. Please wait '.RateLimiter::availableIn($key).' seconds.'],
            ]);
        }
        RateLimiter::hit($key, 60);

        $user->forceFill(['verification_token' => Str::random(48)])->save();
        $this->sendVerificationMail($user);

        return response()->json(['message' => 'A new confirmation link is on its way.']);
    }

    /*
    |----------------------------------------------------------------------
    | Google OAuth
    |----------------------------------------------------------------------
    */

    /** Tells the SPA whether Google sign-in has been configured. */
    public function googleConfig(): JsonResponse
    {
        $enabled = filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));

        return response()->json(['data' => ['enabled' => $enabled]]);
    }

    /** Kicks off the Google OAuth dance. */
    public function googleRedirect()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Exchanges the Google code for a user, then hands a Sanctum token to the
     * SPA through the URL fragment (fragments never reach server logs).
     */
    public function googleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->away(config('app.frontend_url').'/login?error=google');
        }

        if (! $googleUser->getEmail()) {
            return redirect()->away(config('app.frontend_url').'/login?error=google_email');
        }

        $user = User::where('email', strtolower($googleUser->getEmail()))->first();

        if (! $user) {
            $user = User::create([
                'name' => $googleUser->getName() ?: 'Google Shopper',
                'email' => strtolower($googleUser->getEmail()),
                'password' => Hash::make(Str::random(40)),
                'google_id' => (string) $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar(),
                // Google has already verified this email address.
                'email_verified_at' => now(),
            ]);
        } else {
            $user->forceFill([
                'google_id' => $user->google_id ?: (string) $googleUser->getId(),
                'avatar_url' => $user->avatar_url ?: $googleUser->getAvatar(),
            ])->save();
        }

        $token = $user->createToken('google-spa')->plainTextToken;

        return redirect()->away(
            config('app.frontend_url').'/auth/google/callback#token='.$token
        );
    }

    private function sendVerificationMail(User $user): void
    {
        $verifyUrl = config('app.frontend_url').'/verify?token='.$user->verification_token;

        try {
            Mail::to($user->email)->send(new VerifyEmailMail($user, $verifyUrl));
        } catch (\Throwable $exception) {
            // Never block sign-up because mail is not configured yet (e.g. in
            // local dev with MAIL_MAILER=log or before Mailgun keys are added).
            report($exception);
        }
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'avatar_emoji' => '🧑‍🚀',
            'avatar_url' => $user->avatar_url,
        ];
    }
}
