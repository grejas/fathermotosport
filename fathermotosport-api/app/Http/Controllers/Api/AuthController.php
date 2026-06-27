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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

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
            'password' => Hash::make($request->password),
            'status' => 'active',
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
}
