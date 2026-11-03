<?php

use App\Http\Controllers\Panel\FotoDeResenaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Fotos de reseñas para el panel (incluidas las pendientes, que no son públicas).
Route::get('/panel/resenas/fotos/{foto}/{variante}', FotoDeResenaController::class)
    ->whereIn('variante', ['grande', 'miniatura'])
    ->name('panel.resenas.foto');
