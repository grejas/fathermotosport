<?php

use App\Http\Middleware\CheckRole;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Respuestas JSON para peticiones API no autenticadas.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'No autenticado.'], 401);
            }
        });

        // Fallos de comunicación con pasarelas de pago (PayPal/Stripe/MercadoPago)
        // se devuelven como 502 limpio en lugar de un stack trace 500.
        $exceptions->render(function (RequestException $e, Request $request) {
            if ($request->is('api/*')) {
                Log::error('Error de pasarela de pago', [
                    'url' => $request->fullUrl(),
                    'status' => $e->response?->status(),
                    'body' => $e->response?->body(),
                ]);

                return response()->json([
                    'message' => 'No se pudo procesar el pago con la pasarela. Inténtalo nuevamente más tarde.',
                ], 502);
            }
        });
    })->create();
