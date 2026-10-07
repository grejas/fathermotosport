<?php

namespace App\Filament\Resources\UpsellRuleResource\Pages;

use App\Models\UpsellRule;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Lo común a crear y editar una regla desde el formulario: guardar con
 * UpsellService::guardarReglas y avisar qué se creó y qué se omitió por repetido.
 */
trait GuardaReglas
{
    /** @var array{creadas: Collection<int, string>, omitidas: Collection<int, string>}|null */
    protected ?array $resultadoDeGuardado = null;

    /**
     * Los errores del servicio vuelven como errores de campo del formulario (por eso
     * el prefijo data.): una regla repetida se ve como un aviso bajo el selector, no
     * como el error del índice único.
     *
     * @param  Closure(): array{regla: UpsellRule, creadas: Collection<int, string>, omitidas: Collection<int, string>}  $guardar
     */
    protected function guardar(Closure $guardar): UpsellRule
    {
        try {
            $resultado = $guardar();
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                collect($e->errors())
                    ->mapWithKeys(fn (array $mensajes, string $campo) => ['data.'.($campo === 'trigger' ? 'trigger_product_id' : $campo) => $mensajes])
                    ->all()
            );
        }

        $this->resultadoDeGuardado = $resultado;

        return $resultado['regla'];
    }

    protected function avisoDeGuardado(string $singular, string $plural, bool $cuentaLaEditada = false): Notification
    {
        $creadas = $this->resultadoDeGuardado['creadas'] ?? collect();
        $omitidas = $this->resultadoDeGuardado['omitidas'] ?? collect();
        $cantidad = $creadas->count() + ($cuentaLaEditada ? 1 : 0);

        $aviso = Notification::make()
            ->title(sprintf('%d %s %s', $cantidad, $cantidad === 1 ? 'regla' : 'reglas', $cantidad === 1 ? $singular : $plural));

        $cuerpo = [];

        if ($cuentaLaEditada && $creadas->isNotEmpty()) {
            $cuerpo[] = 'Nuevas: '.$creadas->implode(', ').'.';
        }

        if ($omitidas->isNotEmpty()) {
            $cuerpo[] = 'Omitidas porque ya existían: '.$omitidas->implode(', ').'.';
        }

        if ($cuerpo) {
            // Con omitidas el admin tiene que leerlo: no se va solo.
            $aviso->body(implode(' ', $cuerpo))->persistent();
        }

        return $omitidas->isNotEmpty() ? $aviso->warning() : $aviso->success();
    }
}
