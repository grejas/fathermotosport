<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Concerns\DatosPersonales;
use App\Http\Requests\User\CompletarPerfilRequest;
use App\Http\Resources\UserResource;
use App\Models\EmailVerificationCode;
use App\Rules\CorreoEntregable;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Lo que le falta a una cuenta existente (antigua o de Google): teléfono, país, fecha de
 * nacimiento opcional con su declaración de edad y, si el correo nunca se verificó, el
 * código por correo. Se pide UNA vez en una ventana que se puede cerrar: nunca impide
 * comprar. Solo con el correo verificado la cuenta recibe promociones.
 */
class PerfilPendienteController extends Controller
{
    public function __construct(private EmailService $emails) {}

    public function completar(CompletarPerfilRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill([
            ...DatosPersonales::paraGuardar($request->validated()),
            'profile_prompted_at' => $user->profile_prompted_at ?? now(),
        ])->save();

        return response()->json([
            'message' => __('registro.profile_saved'),
            'user' => new UserResource($user->load('role')),
        ]);
    }

    /** Cerró la ventana sin completar: no se le vuelve a mostrar. */
    public function cerrarVentana(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->profile_prompted_at === null) {
            $user->forceFill(['profile_prompted_at' => now()])->save();
        }

        return response()->json(['user' => new UserResource($user->load('role'))]);
    }

    /** Idioma de la web (tras entrar con Google, o al cambiar de idioma con sesión). */
    public function idioma(Request $request): JsonResponse
    {
        $data = $request->validate(['locale' => ['required', 'string', 'in:es,pt,en']]);
        $request->user()->forceFill(['locale' => $data['locale']])->save();

        return response()->json(['locale' => $data['locale']]);
    }

    /** Código de 6 dígitos (10 minutos) al correo de la cuenta, para verificarlo. */
    public function enviarCodigo(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at !== null) {
            return response()->json(['message' => __('registro.email_already_verified')], 409);
        }

        // Un dominio sin MX o desechable no recibe el código: se avisa sin gastar un envío.
        Validator::make(['email' => $user->email], ['email' => ['required', 'email', new CorreoEntregable]])->validate();

        EmailVerificationCode::where('email', $user->email)->whereNull('used_at')->update(['used_at' => now()]);

        $codigo = (string) random_int(100000, 999999);
        EmailVerificationCode::create([
            'email' => $user->email,
            'code' => $codigo,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->emails->sendVerificationCode($user->email, $codigo);

        return response()->json(['message' => __('registro.code_sent')]);
    }

    public function verificarCodigo(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();

        if ($user->email_verified_at !== null) {
            return response()->json(['message' => __('registro.email_already_verified'), 'user' => new UserResource($user->load('role'))]);
        }

        $verificacion = EmailVerificationCode::where('email', $user->email)
            ->where('code', $data['code'])
            ->whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->latest('id')
            ->first();

        if (! $verificacion) {
            throw ValidationException::withMessages(['code' => [__('registro.code_invalid')]]);
        }

        $verificacion->update(['used_at' => now()]);
        $user->marcarCorreoVerificado('code');

        return response()->json([
            'message' => __('registro.email_verified'),
            'user' => new UserResource($user->load('role')),
        ]);
    }
}
