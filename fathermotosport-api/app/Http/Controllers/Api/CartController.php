<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(private CartService $carts)
    {
    }

    /**
     * Carrito vigente (por user_id o session_id).
     */
    public function show(Request $request): CartResource
    {
        return new CartResource($this->carts->resolveCart($request));
    }

    /**
     * Agrega una variante al carrito verificando stock.
     */
    public function addItem(AddCartItemRequest $request): JsonResponse
    {
        $variant = ProductVariant::with('product')->findOrFail($request->variant_id);

        $cart = $this->carts->resolveCart($request);
        $existing = $cart->items()->where('product_variant_id', $variant->id)->first();
        $desiredQty = ($existing?->quantity ?? 0) + (int) $request->quantity;

        if ($variant->stock < $desiredQty) {
            throw ValidationException::withMessages([
                'quantity' => ["Stock insuficiente. Disponible: {$variant->stock}."],
            ]);
        }

        // El precio es del producto (no varía por talla).
        $price = $variant->product->sale_price ?? $variant->product->price;

        if ($existing) {
            $existing->update(['quantity' => $desiredQty, 'price' => $price]);
        } else {
            $cart->items()->create([
                'product_variant_id' => $variant->id,
                'quantity' => (int) $request->quantity,
                'price' => $price,
            ]);
        }

        return response()->json([
            'message' => 'Producto agregado al carrito.',
            'cart' => new CartResource($cart->fresh()->load('items.variant.product')),
        ]);
    }

    /**
     * Cambia la cantidad de un item.
     */
    public function updateItem(UpdateCartItemRequest $request, string $id): JsonResponse
    {
        $cart = $this->carts->resolveCart($request);
        $item = $cart->items()->with('variant')->findOrFail($id);

        if ($item->variant->stock < (int) $request->quantity) {
            throw ValidationException::withMessages([
                'quantity' => ["Stock insuficiente. Disponible: {$item->variant->stock}."],
            ]);
        }

        $item->update(['quantity' => (int) $request->quantity]);

        return response()->json([
            'message' => 'Carrito actualizado.',
            'cart' => new CartResource($cart->fresh()->load('items.variant.product')),
        ]);
    }

    /**
     * Elimina un item del carrito.
     */
    public function removeItem(Request $request, string $id): JsonResponse
    {
        $cart = $this->carts->resolveCart($request);
        $cart->items()->where('id', $id)->delete();

        return response()->json([
            'message' => 'Producto eliminado del carrito.',
            'cart' => new CartResource($cart->fresh()->load('items.variant.product')),
        ]);
    }

    /**
     * Vacía el carrito.
     */
    public function clear(Request $request): JsonResponse
    {
        $cart = $this->carts->resolveCart($request, createIfMissing: false);
        $cart?->items()->delete();

        return response()->json(['message' => 'Carrito vaciado.']);
    }

    /**
     * Fusiona el carrito anónimo con el del usuario tras loguearse.
     */
    public function merge(Request $request): JsonResponse
    {
        $sessionId = $this->carts->sessionId($request);

        if (! $sessionId) {
            throw ValidationException::withMessages([
                'session_id' => ['Se requiere el session_id del carrito anónimo.'],
            ]);
        }

        $cart = $this->carts->merge($request->user(), $sessionId);

        return response()->json([
            'message' => 'Carrito fusionado.',
            'cart' => new CartResource($cart),
        ]);
    }
}
