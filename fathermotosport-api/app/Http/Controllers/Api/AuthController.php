<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\CouponService;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function __construct(
        private CouponService $coupons,
        private EmailService $emails,
    ) {
    }

    /**
     * Registra un cliente, genera su cupón de bienvenida de $5 y devuelve un token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $clienteRole = Role::where('slug', 'cliente')->firstOrFail();

        $user = User::create([
            'role_id' => $clienteRole->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'birth_date' => $request->birth_date,
            'password' => Hash::make($request->password),
            'status' => 'active',
            'last_password_change' => now(),
        ]);

        // Descuento de $5 automático al registrarse → cupón único.
        $coupon = $this->coupons->generateWelcomeCoupon($user->id);
        $this->emails->sendWelcome($user, $coupon);

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user->load('role')),
            'welcome_coupon' => [
                'code' => $coupon->code,
                'value' => $coupon->value,
            ],
            'token' => $token,
        ], 201);
    }

    /**
     * Autentica y devuelve user + token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Tu cuenta no está activa.'],
            ]);
        }

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user->load('role')),
            'token' => $token,
        ]);
    }

    /**
     * Invalida el token actual.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    /**
     * Usuario autenticado con su rol.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('role'));
    }

    /**
     * Envía el email de recuperación de contraseña.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => __($status),
        ]);
    }

    /**
     * Devuelve la URL de consentimiento de Google para que el frontend redirija.
     * stateless(): no usamos sesión (flujo API/SPA).
     */
    public function redirectToGoogle(): JsonResponse
    {
        $url = Socialite::driver('google')
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    /**
     * Callback de Google: crea el usuario si no existe (con cupón + email de
     * bienvenida, igual que register()) y redirige al frontend con un token.
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'https://fathermotosport.com'), '/');

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            Log::error('Google OAuth error: '.$e->getMessage());

            return redirect("{$frontendUrl}/login?error=google_auth_failed");
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if (! $user) {
            $clienteRole = Role::where('slug', 'cliente')->firstOrFail();

            $user = User::create([
                'role_id' => $clienteRole->id,
                'first_name' => $googleUser->user['given_name'] ?? $googleUser->getName(),
                'last_name' => $googleUser->user['family_name'] ?? '',
                'email' => $googleUser->getEmail(),
                'avatar' => $googleUser->getAvatar(),
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
                'status' => 'active',
                'last_password_change' => now(),
            ]);

            // Mismo beneficio que el registro normal: cupón de $5 + email.
            $coupon = $this->coupons->generateWelcomeCoupon($user->id);
            $this->emails->sendWelcome($user, $coupon);
        }

        if ($user->status !== 'active') {
            return redirect("{$frontendUrl}/login?error=account_inactive");
        }

        $token = $user->createToken('google-auth')->plainTextToken;
        $user->loadMissing('role');

        $payload = urlencode(json_encode([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'role' => $user->role?->slug,
        ]));

        return redirect("{$frontendUrl}/auth/google/callback?token={$token}&user={$payload}");
    }
}
