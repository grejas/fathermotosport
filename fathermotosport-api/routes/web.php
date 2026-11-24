<?php

use App\Http\Controllers\Media\VarianteDeImagenController;
use App\Http\Controllers\Panel\FotoDeResenaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Fotos de reseñas para el panel (incluidas las pendientes, que no son públicas).
Route::get('/panel/resenas/fotos/{foto}/{variante}', FotoDeResenaController::class)
    ->whereIn('variante', ['grande', 'miniatura'])
    ->name('panel.resenas.foto');

// Imágenes livianas (WebP al ancho pedido). Solo la primera vez pasa por acá: después el
// archivo existe en public/storage/v/... y lo sirve Apache. Sin sesión ni cookies, para
// que la respuesta se pueda cachear.
Route::get('/storage/v/{ancho}/{ruta}', VarianteDeImagenController::class)
    ->where(['ancho' => '[0-9]+', 'ruta' => '.+'])
    ->withoutMiddleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ])
    ->name('imagen.variante');
