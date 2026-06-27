<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\Request;

class CartService
{
    /**
     * Obtiene (o crea) el carrito vigente para la petición actual,
     * resolviendo por user_id si está autenticado o por session_id (header/param) si es guest.
     */
    public function resolveCart(Request $request, bool $createIfMissing = true): ?Cart
    {
        $user = $request->user();
        $sessionId = $this->sessionId($request);

        $query = Cart::query();

        if ($user) {
            $cart = $query->where('user_id', $user->id)->latest()->first();
        } else {
            $cart = $sessionId ? $query->where('session_id', $sessionId)->first() : null;
        }

        if (! $cart && $createIfMissing) {
            $cart = Cart::create([
                'user_id' => $user?->id,
                'session_id' => $sessionId ?: (string) \Illuminate\Support\Str::uuid(),
                'expires_at' => now()->addDays(30),
            ]);
        }

        return $cart?->load(['items.variant.product']);
    }

    /**
     * Fusiona el carrito anónimo (session_id) con el carrito del usuario autenticado.
     */
    public function merge(User $user, string $sessionId): Cart
    {
        $guestCart = Cart::with('items')->where('session_id', $sessionId)->first();

        $userCart = Cart::firstOrCreate(
            ['user_id' => $user->id],
            ['session_id' => 'user-' . $user->id, 'expires_at' => now()->addDays(30)]
        );

        if ($guestCart && $guestCart->id !== $userCart->id) {
            foreach ($guestCart->items as $item) {
                $existing = $userCart->items()
                    ->where('product_variant_id', $item->product_variant_id)
                    ->first();

                if ($existing) {
                    $existing->increment('quantity', $item->quantity);
                } else {
                    $userCart->items()->create([
                        'product_variant_id' => $item->product_variant_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                    ]);
                }
            }

            $guestCart->items()->delete();
            $guestCart->delete();
        }

        return $userCart->load(['items.variant.product']);
    }

    public function sessionId(Request $request): ?string
    {
        return $request->header('X-Session-Id')
            ?? $request->input('session_id')
            ?? $request->query('session_id');
    }
}
