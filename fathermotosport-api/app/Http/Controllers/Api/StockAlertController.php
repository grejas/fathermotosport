<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAlertController extends Controller
{
    /** Máximo de avisos nuevos por conexión en 24 h (frena abusos sin guardar la IP). */
    private const MAX_POR_DIA = 10;

    /**
     * Registra un «Avísame cuando esté disponible».
     *
     * 201 nuevo · 200 ya estaba anotado (se actualiza el WhatsApp) · 404 producto
     * inexistente o inactivo · 429 demasiados avisos desde la misma conexión.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
            'whatsapp' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()\-]{7,20}$/'],
            'size' => ['nullable', 'string', 'max:32'],
            'locale' => ['nullable', 'string', 'in:es,en,pt'],
            // Trampa para bots: un campo oculto que una persona nunca llena.
            'website' => ['prohibited'],
        ]);

        $product = Product::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $ip = $request->ip();
        $ipHash = $ip ? hash('sha256', $ip.'|'.config('app.key')) : null;
        $email = mb_strtolower(trim($datos['email']));
        $size = isset($datos['size']) && trim($datos['size']) !== '' ? trim($datos['size']) : null;

        $existente = StockAlert::where('product_id', $product->id)
            ->where('size', $size)
            ->where('email', $email)
            ->first();

        if ($existente) {
            // Si ya lo avisaron y vuelve a pedirlo, queda pendiente otra vez.
            $existente->update([
                'whatsapp' => $datos['whatsapp'] ?? $existente->whatsapp,
                'locale' => $datos['locale'] ?? $existente->locale,
                'notified_at' => null,
            ]);

            return response()->json(['status' => 'existing'], 200);
        }

        if ($ipHash && StockAlert::where('ip_hash', $ipHash)->where('created_at', '>=', now()->subDay())->count() >= self::MAX_POR_DIA) {
            return response()->json(['status' => 'limited'], 429);
        }

        StockAlert::create([
            'product_id' => $product->id,
            'size' => $size,
            'email' => $email,
            'whatsapp' => $datos['whatsapp'] ?? null,
            'locale' => $datos['locale'] ?? null,
            'ip_hash' => $ipHash,
        ]);

        return response()->json(['status' => 'new'], 201);
    }
}
