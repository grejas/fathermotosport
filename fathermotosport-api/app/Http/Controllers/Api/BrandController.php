<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    /**
     * Todas las marcas activas con el conteo de productos activos.
     */
    public function index(): AnonymousResourceCollection
    {
        $brands = Brand::query()
            ->active()
            ->withCount(['products' => fn ($q) => $q->active()])
            ->orderBy('name')
            ->get();

        return BrandResource::collection($brands);
    }
}
