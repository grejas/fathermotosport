<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Alias cortos aceptados en las rutas → slug real del rol.
     */
    private const ALIASES = [
        'admin' => 'administrador',
        'administrador' => 'administrador',
        'employee' => 'empleado',
        'empleado' => 'empleado',
        'customer' => 'cliente',
        'client' => 'cliente',
        'cliente' => 'cliente',
    ];

    /**
     * Uso: ->middleware('role:admin') o ->middleware('role:admin,employee')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $allowed = array_map(
            fn (string $role) => self::ALIASES[strtolower($role)] ?? strtolower($role),
            $roles
        );

        $userRoleSlug = optional($user->role)->slug;

        if (! in_array($userRoleSlug, $allowed, true)) {
            return response()->json([
                'message' => 'No tienes permisos para realizar esta acción.',
            ], 403);
        }

        return $next($request);
    }
}
