<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garantiza que solo el personal (Administrador o Empleado) acceda al panel.
 * Un cliente autenticado es expulsado y redirigido a la tienda.
 */
class EnsurePanelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect('/admin/login');
        }

        if (! $user->isStaff()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect(config('services.frontend_url', 'http://localhost:3001'))
                ->with('error', 'No tenés permisos para acceder al panel de administración.');
        }

        return $next($request);
    }
}
