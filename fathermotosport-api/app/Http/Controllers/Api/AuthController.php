<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SendVerificationCodeRequest;
use App\Http\Requests\Auth\VerifyAndRegisterRequest;
use App\Http\Requests\Auth\RegisterFromOrderRequest;
use App\Http\Resources\UserResource;
use App\Models\Coupon;
use App\Models\EmailVerificationCode;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\CouponService;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
     * Paso 1 del registro: verifica el reCAPTCHA y envía un código de 6 dígitos
     * (válido 10 minutos) al email indicado. Aún no crea la cuenta.
     */
    public function sendVerificationCode(SendVerificationCodeRequest $request): JsonResponse
    {
        $this->verifyRecaptcha($request->validated('recaptcha_token'));

        // Invalida cualquier código anterior pendiente para este email.
        EmailVerificationCode::where('email', $request->email)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code = (string) random_int(100000, 999999);

        EmailVerificationCode::create([
            'email' => $request->email,
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->emails->sendVerificationCode($request->email, $code);

        return response()->json([
            'message' => 'Te enviamos un código de verificación a tu email.',
        ]);
    }

    /**
     * Paso 2 del registro: valida el código de verificación y, si es correcto,
     * crea al cliente, genera su cupón de bienvenida de $5 y devuelve un token.
     */
    public function verifyAndRegister(VerifyAndRegisterRequest $request): JsonResponse
    {
        $verification = EmailVerificationCode::where('email', $request->email)
            ->where('code', $request->code)
            ->whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->latest('id')
            ->first();

        if (! $verification) {
            throw ValidationException::withMessages([
                'code' => ['El código es inválido o ha expirado.'],
            ]);
        }

        $verification->update(['used_at' => now()]);

        // Ya hay una cuenta sin verificar con este email (creada desde un pedido con
        // tarjeta, donde el email lo escribió quien compró). Quien acaba de probar con
        // el código que controla la casilla es el dueño: se queda con la cuenta.
        if ($reclamada = User::reclamablePorEmail($request->email)) {
            return $this->reclamarCuenta($reclamada, $request);
        }

        $clienteRole = Role::where('slug', 'cliente')->firstOrFail();

        $user = User::create([
            'role_id' => $clienteRole->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'birth_date' => $request->birth_date,
            'country' => $request->country,
            'password' => Hash::make($request->password),
            'status' => 'active',
            'email_verified_at' => now(),
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
     * El dueño verificado del email se queda con una cuenta que creó otro (o él mismo y
     * no lo recuerda): se reemplazan los datos y la contraseña por los que acaba de
     * dar, y se cierran todas las sesiones abiertas con la contraseña anterior.
     *
     * De los pedidos vinculados se quedan solo los de email confirmado por PayPal; el
     * resto vuelve a ser de invitado (ver desvincularPedidosSinEmailVerificado).
     */
    private function reclamarCuenta(User $user, VerifyAndRegisterRequest $request): JsonResponse
    {
        DB::transaction(function () use ($user, $request) {
            $user->forceFill([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'birth_date' => $request->birth_date,
                'country' => $request->country,
                'password' => Hash::make($request->password),
                'email_verified_at' => now(),
                'last_password_change' => now(),
            ])->save();

            $user->tokens()->delete();
            $this->desvincularPedidosSinEmailVerificado($user);
        });

        // Ya recibió un cupón de bienvenida al crearse la cuenta: si sigue vigente se
        // devuelve ese; si venció o se usó, se le da uno nuevo como a cualquier alta.
        $coupon = Coupon::where('user_id', $user->id)
            ->where('used_count', 0)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $coupon) {
            $coupon = $this->coupons->generateWelcomeCoupon($user->id);
            $this->emails->sendWelcome($user, $coupon);
        }

        return response()->json([
            'user' => new UserResource($user->load('role')),
            'welcome_coupon' => ['code' => $coupon->code, 'value' => $coupon->value],
            'token' => $user->createToken('auth')->plainTextToken,
        ], 201);
    }

    /**
     * Al reclamar una cuenta sin verificar, sus pedidos con email escrito a mano vuelven
     * a ser de invitado: los pudo hacer un tercero con este email, y el dueño real no
     * tiene por qué ver su dirección ni su teléfono. Quien compró los sigue viendo con
     * el token de su pedido. Los de PayPal se quedan: ese email lo confirmó PayPal.
     */
    private function desvincularPedidosSinEmailVerificado(User $user): void
    {
        Order::where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('email_verificado_por')
                ->orWhere('email_verificado_por', '!=', 'paypal'))
            ->update(['user_id' => null]);
    }

    /**
     * Crea una cuenta a partir de un pedido de invitado ya pagado, con PayPal o con
     * tarjeta: el pedido ya tiene nombre y email, solo falta la contraseña.
     *
     * Autorización: el token del pedido (X-Order-Token), el mismo que autorizó el
     * pago. Conocer el order_id NO alcanza: si no, cualquiera podría reclamar el
     * pedido de otra persona.
     */
    public function registerFromOrder(RegisterFromOrderRequest $request): JsonResponse
    {
        $order = Order::with('address')->findOrFail($request->order_id);

        if (! $order->isAccessibleBy(null, Order::tokenFromRequest($request))) {
            abort(403, 'No puedes crear una cuenta para este pedido.');
        }

        if ($order->payment_status !== 'paid') {
            return response()->json(['message' => 'Este pedido todavía no está pagado.'], 409);
        }

        if ($order->user_id) {
            return response()->json(['message' => 'Este pedido ya pertenece a una cuenta.'], 409);
        }

        $email = $order->guest_email;

        if (! $email) {
            return response()->json(['message' => 'Este pedido no tiene un email asociado.'], 409);
        }

        // Ya existe una cuenta con ese email: se invita a iniciar sesión en vez de
        // duplicarla. El pedido NO se vincula acá; para eso hay que autenticarse.
        if (User::where('email', $email)->exists()) {
            return response()->json([
                'message' => 'Ya existe una cuenta con este email. Iniciá sesión para ver tus pedidos.',
                'email' => $email,
                'should_login' => true,
            ], 409);
        }

        [$nombre, $apellido] = $this->partirNombre($order->address?->full_name);

        $user = DB::transaction(function () use ($order, $email, $nombre, $apellido, $request) {
            $user = User::create([
                'role_id' => Role::where('slug', 'cliente')->firstOrFail()->id,
                'first_name' => $nombre,
                'last_name' => $apellido,
                'email' => $email,
                'phone' => $order->address?->phone,
                'country' => $order->country,
                'password' => Hash::make($request->password),
                'status' => 'active',
                // Si el email lo informó PayPal al pagar, quien compró controla esa
                // casilla. Si lo escribió a mano en el checkout (pago con tarjeta),
                // no hay nada que lo pruebe: la cuenta queda sin verificar.
                'email_verified_at' => $order->email_verificado_por === 'paypal' ? now() : null,
                'last_password_change' => now(),
            ]);

            $order->update(['user_id' => $user->id]);

            // Pedidos de invitado anteriores con el mismo email quedan vinculados,
            // pero SOLO si ese email lo informó PayPal en este pedido. Si lo hubiera
            // escrito alguien a mano en el checkout, podría estar reclamando el
            // historial de otra persona.
            if ($order->email_verificado_por === 'paypal') {
                Order::whereNull('user_id')
                    ->where('guest_email', $email)
                    ->where('id', '!=', $order->id)
                    ->update(['user_id' => $user->id]);
            }

            return $user;
        });

        // Cupón de bienvenida, igual que en el registro normal: es para una compra
        // futura, no se aplica al pedido que se acaba de pagar.
        $coupon = $this->coupons->generateWelcomeCoupon($user->id);
        $this->emails->sendWelcome($user, $coupon);

        return response()->json([
            'user' => new UserResource($user->load('role')),
            'welcome_coupon' => ['code' => $coupon->code, 'value' => $coupon->value],
            'linked_orders' => Order::where('user_id', $user->id)->count(),
            'token' => $user->createToken('auth')->plainTextToken,
        ], 201);
    }

    /** "Ana María Pérez" → ["Ana", "María Pérez"]. */
    private function partirNombre(?string $completo): array
    {
        $partes = preg_split('/\s+/', trim((string) $completo), 2) ?: [];

        return [$partes[0] ?? 'Cliente', $partes[1] ?? ''];
    }

    /**
     * Verifica el token de reCAPTCHA v2 contra la API de Google.
     * Lanza ValidationException (→ 422) si la verificación falla.
     */
    private function verifyRecaptcha(string $token): void
    {
        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.recaptcha.secret_key'),
            'response' => $token,
        ]);

        if (! $response->successful() || $response->json('success') !== true) {
            throw ValidationException::withMessages([
                'recaptcha_token' => ['La verificación de reCAPTCHA falló. Intenta nuevamente.'],
            ]);
        }
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
     * Aplica el nuevo password usando el token enviado por email.
     * Devuelve 422 con un mensaje claro si el token es inválido o expiró.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'last_password_change' => now(),
                    // Usar el enlace del correo prueba que controla la casilla. Es la
                    // salida para quien encuentra una cuenta sin verificar con su email.
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                // Las sesiones abiertas con la contraseña anterior dejan de valer.
                $user->tokens()->delete();
            }
        );

        $message = match ($status) {
            Password::PASSWORD_RESET => 'Tu contraseña fue restablecida correctamente.',
            Password::INVALID_USER => 'No encontramos una cuenta con ese email.',
            Password::RESET_THROTTLED => 'Ya solicitaste un restablecimiento hace poco. Esperá unos minutos e intentá de nuevo.',
            default => 'El enlace de recuperación es inválido o ha expirado.',
        };

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [$message],
            ]);
        }

        return response()->json(['message' => $message]);
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
     * bienvenida, igual que verifyAndRegister()) y redirige al frontend con un token.
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

        // Google confirma el email: si la cuenta existía sin verificar (creada desde un
        // pedido con tarjeta), queda verificada y la contraseña que puso quien la creó
        // deja de valer, igual que al reclamarla con el código.
        if ($user->email_verified_at === null && $user->isCliente()) {
            DB::transaction(function () use ($user) {
                $user->forceFill([
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(32)),
                    'last_password_change' => now(),
                ])->save();
                $user->tokens()->delete();
                $this->desvincularPedidosSinEmailVerificado($user);
            });
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
