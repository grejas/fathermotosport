<?php

namespace App\Services;

use App\Mail\CorreoAvisoDescuento;
use App\Models\Category;
use App\Models\MailCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Aviso por correo de un descuento de categoría (remarketing, fase 3).
 *
 * Un aviso por descuento: la clave category:{id}:{porcentaje}:{inicio}:{fin} es única en
 * mail_campaigns. Editar otros campos o apagar y encender el descuento no cambia la clave,
 * así que no se repite; solo "Reenviar" crea otro envío (clave con :reenvio:N).
 *
 * crear() solo registra el envío y sus destinatarios; los manda el comando
 * campanas:enviar por tandas (EnvioPorTandas), respetando el cupo de Resend.
 */
class AvisoDescuento
{
    /** Datos del correo, congelados al crear el envío. */
    public function datos(Category $category): array
    {
        return [
            'category_id' => $category->id,
            'category_name' => $category->name,
            'percent' => round((float) $category->discount_percent, 2),
            'starts_at' => $category->discount_starts_at?->toIso8601String(),
            'ends_at' => $category->discount_ends_at?->toIso8601String(),
            'has_exclusions' => $category->productosExcluidos()->exists(),
        ];
    }

    /** category:{id}:{porcentaje}:{inicio}:{fin}, con fechas en UTC. */
    public function clave(Category $category): string
    {
        $fecha = fn ($f) => $f ? $f->copy()->utc()->format('Y-m-d\TH:i') : '-';

        return sprintf(
            'category:%d:%s:%s:%s',
            $category->id,
            number_format((float) $category->discount_percent, 2, '.', ''),
            $fecha($category->discount_starts_at),
            $fecha($category->discount_ends_at),
        );
    }

    /** El aviso más reciente de este mismo descuento (original o reenvío), o null. */
    public function avisoExistente(Category $category): ?MailCampaign
    {
        $clave = $this->clave($category);

        return MailCampaign::where('dedupe_key', $clave)
            ->orWhere('dedupe_key', 'like', $clave.':reenvio:%')
            ->latest('id')
            ->first();
    }

    /** Por qué no se puede avisar ahora, o null si se puede. */
    public function motivoParaNoEnviar(Category $category): ?string
    {
        if (! $category->notify_customers) {
            return 'El interruptor "Avisar a los clientes por correo" está apagado.';
        }

        return match ($category->estadoDelDescuento()) {
            Category::DESCUENTO_APAGADO => 'El descuento está apagado o no tiene porcentaje.',
            Category::DESCUENTO_PROGRAMADO => 'El descuento todavía no empezó: se puede avisar cuando esté vigente.',
            Category::DESCUENTO_VENCIDO => 'El descuento ya venció.',
            default => null,
        };
    }

    /** Clientes que lo recibirían: el criterio fiable de la fase 1 y 2. */
    public function destinatarios(): Collection
    {
        return User::destinatariosDePromociones();
    }

    /** Clientes activos con el correo marcado como verificado pero sin evidencia: quedan fuera. */
    public function excluidosSinEvidencia(): int
    {
        // Verificados menos los que tienen evidencia (una negación en SQL fallaría con los
        // NULL de email_verified_via y avatar).
        $verificados = fn () => User::query()
            ->whereHas('role', fn (Builder $q) => $q->where('slug', 'cliente'))
            ->where('status', 'active')
            ->whereNotNull('email_verified_at');

        return $verificados()->count() - $verificados()->conCorreoComprobado()->count();
    }

    /**
     * Registra el aviso y sus destinatarios (en una transacción). Lanza RuntimeException
     * con un mensaje claro si no se puede o si este descuento ya se avisó.
     */
    public function crear(Category $category, ?User $admin = null, bool $reenvio = false): MailCampaign
    {
        if ($motivo = $this->motivoParaNoEnviar($category)) {
            throw new RuntimeException($motivo);
        }

        $existente = $this->avisoExistente($category);
        if ($existente && ! $reenvio) {
            throw new RuntimeException('Este descuento ya se avisó el '.$existente->created_at->timezone(config('marketing.timezone'))->format('d/m/Y H:i').'. Usa "Reenviar" si quieres mandarlo otra vez.');
        }
        if (! $existente && $reenvio) {
            throw new RuntimeException('Este descuento todavía no se avisó: usa "Avisar por correo".');
        }

        $clave = $this->clave($category);
        if ($reenvio) {
            $clave .= ':reenvio:'.MailCampaign::where('dedupe_key', 'like', $clave.':reenvio:%')->count() + 1;
        }

        $destinatarios = $this->destinatarios();

        try {
            return DB::transaction(function () use ($category, $admin, $clave, $destinatarios, $reenvio) {
                $campana = MailCampaign::create([
                    'type' => MailCampaign::TIPO_DESCUENTO,
                    'name' => ($reenvio ? 'Reenvío: ' : '').'Descuento en '.$category->name,
                    'dedupe_key' => $clave,
                    'category_id' => $category->id,
                    'payload' => $this->datos($category),
                    'status' => MailCampaign::ENVIANDO,
                    'created_by' => $admin?->id,
                ]);

                $campana->agregarDestinatarios($destinatarios);
                $campana->recontar();
                if ($campana->total_count === 0) {
                    $campana->update(['status' => MailCampaign::COMPLETADA, 'finished_at' => now()]);
                }

                return $campana->fresh();
            });
        } catch (UniqueConstraintViolationException) {
            throw new RuntimeException('Este descuento ya se avisó (otro envío se registró al mismo tiempo).');
        }
    }

    /** Por qué un aviso en curso tiene que cancelarse, o null si sigue valiendo. */
    public function motivoDeCancelacion(MailCampaign $campana): ?string
    {
        $category = $campana->category_id ? Category::find($campana->category_id) : null;
        if (! $category) {
            return 'La categoría ya no existe.';
        }

        if (! $category->descuentoVigente()) {
            return match ($category->estadoDelDescuento()) {
                Category::DESCUENTO_VENCIDO => 'El descuento venció durante el envío.',
                Category::DESCUENTO_PROGRAMADO => 'El descuento se reprogramó para más adelante.',
                default => 'El descuento se apagó durante el envío.',
            };
        }

        // Si cambió el porcentaje o las fechas, el correo ya no dice la verdad.
        if (! str_starts_with((string) $campana->dedupe_key, $this->clave($category))) {
            return 'El descuento cambió (porcentaje o fechas) durante el envío.';
        }

        return null;
    }

    /** Correo de ejemplo (vista previa y prueba): no crea envío ni cuenta en el cupo. */
    public function correoDePrueba(Category $category, string $idioma = 'es'): CorreoAvisoDescuento
    {
        $cliente = (new User)->forceFill([
            'id' => (string) Str::uuid(),
            'first_name' => 'Cliente',
            'last_name' => '',
            'email' => 'prueba@fathermotosport.com',
            'locale' => $idioma,
        ]);

        return new CorreoAvisoDescuento($cliente, $this->datos($category), 'prueba:'.Str::uuid());
    }
}
