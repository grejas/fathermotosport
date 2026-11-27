<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\WithdrawalReceivedMail;
use App\Models\Order;
use App\Models\WithdrawalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WithdrawalController extends Controller
{
    /**
     * "Desistir de una compra": registra la declaración del cliente y le manda el acuse
     * por correo (soporte duradero). Se acepta aunque el pedido no coincida con el email,
     * para no poner trabas al derecho: el equipo lo revisa en el panel.
     *
     * 201 registrada · 422 datos inválidos · 429 demasiadas solicitudes.
     */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'order_number' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'items' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'locale' => ['nullable', 'string', 'in:es,en,pt'],
            // Trampa para bots: un campo oculto que una persona nunca llena.
            'website' => ['prohibited'],
        ]);

        $numero = mb_strtoupper(trim($datos['order_number']));
        $email = mb_strtolower(trim($datos['email']));

        $order = Order::where('order_number', $numero)->first();
        $coincide = $order && mb_strtolower((string) $order->emailDelCliente()) === $email;

        $ip = $request->ip();

        $solicitud = WithdrawalRequest::create([
            'order_number' => $numero,
            'order_id' => $coincide ? $order->id : null,
            'name' => trim($datos['name']),
            'email' => $email,
            'items' => isset($datos['items']) ? trim($datos['items']) ?: null : null,
            'reason' => isset($datos['reason']) ? trim($datos['reason']) ?: null : null,
            'locale' => $datos['locale'] ?? ($coincide ? $order->idioma() : 'es'),
            'ip_hash' => $ip ? hash('sha256', $ip.'|'.config('app.key')) : null,
        ]);

        try {
            Mail::to($email)
                ->bcc(config('mail.ventas.address'))
                ->send(new WithdrawalReceivedMail($solicitud));
            $solicitud->update(['confirmation_sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Error enviando acuse de desistimiento', ['withdrawal' => $solicitud->id, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'reference' => $solicitud->referencia(),
            'received_at' => $solicitud->created_at->toIso8601String(),
            'email_sent' => $solicitud->confirmation_sent_at !== null,
        ], 201);
    }
}
