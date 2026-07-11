<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\CompressResponse;
use App\Http\Middleware\SecurityHeaders;
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

        // API pura: no existe una página "login" a la que redirigir a los
        // invitados. Devolver null evita que el middleware Authenticate llame a
        // route('login') (que lanzaría RouteNotFoundException → 500) y permite
        // que se lance AuthenticationException → 401 JSON limpio.
        $middleware->redirectGuestsTo(fn (Request $request) => null);

        // Cabeceras de seguridad en todas las respuestas (web + API).
        $middleware->append(SecurityHeaders::class);

        // Compresión gzip SOLO en la API REST. NUNCA en el grupo web/panel:
        // rompería los POST de Livewire del login de Filament.
        $middleware->api(append: [
            CompressResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Toda excepción bajo /api/* se renderiza como JSON, aunque el cliente
        // no envíe el header "Accept: application/json". Sin esto, una petición
        // sin token intentaba redirigir a la ruta "login" (inexistente en una
        // API) y devolvía un 500 en lugar de un 401 limpio.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        // Respuestas JSON para peticiones API no autenticadas.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'No autenticado'], 401);
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
