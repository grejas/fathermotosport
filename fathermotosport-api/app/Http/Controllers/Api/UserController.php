<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreAddressRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\AddressResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\UserResource;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Datos del usuario autenticado.
     */
    public function profile(Request $request): UserResource
    {
        return new UserResource($request->user()->load('role'));
    }

    /**
     * Actualiza nombre, teléfono y avatar.
     */
    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->update($request->validated());

        return new UserResource($user->load('role'));
    }

    /**
     * Mis pedidos paginados.
     */
    public function orders(Request $request): AnonymousResourceCollection
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['items.variant.product', 'address', 'payments'])
            ->latest()
            ->paginate(10);

        return OrderResource::collection($orders);
    }

    /**
     * Mis favoritos.
     */
    public function favorites(Request $request): AnonymousResourceCollection
    {
        $favorites = $request->user()
            ->favorites()
            ->with(['brand', 'category', 'images', 'variants'])
            ->paginate(12);

        return ProductResource::collection($favorites);
    }

    /**
     * Agrega o quita un producto de favoritos.
     */
    public function toggleFavorite(Request $request, string $productId): JsonResponse
    {
        Product::findOrFail($productId);

        $result = $request->user()->favorites()->toggle($productId);
        $added = ! empty($result['attached']);

        return response()->json([
            'favorited' => $added,
            'message' => $added ? 'Agregado a favoritos.' : 'Eliminado de favoritos.',
        ]);
    }

    /**
     * Mis direcciones.
     */
    public function addresses(Request $request): AnonymousResourceCollection
    {
        return AddressResource::collection(
            $request->user()->addresses()->latest()->get()
        );
    }

    /**
     * Guarda una nueva dirección.
     */
    public function storeAddress(StoreAddressRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Si se marca como predeterminada, desmarcar las demás.
        if (! empty($data['is_default'])) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address = $request->user()->addresses()->create($data);

        return response()->json([
            'message' => 'Dirección guardada.',
            'address' => new AddressResource($address),
        ], 201);
    }

    /**
     * Elimina una dirección propia.
     */
    public function deleteAddress(Request $request, string $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);
        $address->delete();

        return response()->json(['message' => 'Dirección eliminada.']);
    }

    /**
     * Mi cupón de bienvenida de $5 (con vencimiento exacto para el countdown).
     */
    public function myCoupon(Request $request): JsonResponse
    {
        $user = $request->user();

        // El cupón de bienvenida es fixed de $5 generado al registrarse y vinculado al usuario.
        $coupon = \App\Models\Coupon::where('user_id', $user->id)
            ->where('type', 'fixed')
            ->where('value', \App\Services\CouponService::WELCOME_AMOUNT)
            ->latest()
            ->first();

        return response()->json([
            'loyalty_discount_used' => $user->loyalty_discount_used,
            'coupon' => $coupon ? [
                'code' => $coupon->code,
                'value' => $coupon->value,
                'used' => $user->loyalty_discount_used,
                'expires_at' => $coupon->expires_at,
                'is_expired' => $coupon->isExpired(),
            ] : null,
        ]);
    }

    /**
     * Cambia la contraseña verificando la contraseña actual.
     * Registra last_password_change (para el recordatorio de seguridad).
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contraseña actual no es correcta.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'last_password_change' => now(),
        ])->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }

    /**
     * Cambia el correo verificando la contraseña actual.
     * El nuevo correo queda sin verificar hasta la confirmación.
     */
    public function changeEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contraseña actual no es correcta.'],
            ]);
        }

        $user->forceFill([
            'email' => $data['email'],
            'email_verified_at' => null,
            'last_password_change' => now(),
        ])->save();

        return response()->json([
            'message' => 'Correo actualizado. Recibirás un email de confirmación en tu nueva dirección.',
            'user' => new UserResource($user->load('role')),
        ]);
    }

    /**
     * Descarta el recordatorio de seguridad por otros 14 días.
     */
    public function dismissSecurityReminder(Request $request): JsonResponse
    {
        $request->user()->forceFill(['security_reminder_dismissed_at' => now()])->save();

        return response()->json(['message' => 'Recordatorio pospuesto.']);
    }
}
