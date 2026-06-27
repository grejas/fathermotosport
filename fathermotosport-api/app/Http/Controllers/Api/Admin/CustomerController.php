<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    /**
     * Listado de clientes con conteo de pedidos.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $customers = User::query()
            ->with('role')
            ->whereHas('role', fn ($q) => $q->where('slug', 'cliente'))
            ->withCount('orders')
            ->when($request->search, function ($q, $v) {
                $q->where(function ($sub) use ($v) {
                    $sub->where('first_name', 'like', "%{$v}%")
                        ->orWhere('last_name', 'like', "%{$v}%")
                        ->orWhere('email', 'like', "%{$v}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return UserResource::collection($customers);
    }
}
