<?php

namespace App\Services;

use App\Mail\CorreoCampana;
use App\Models\MailCampaign;
use App\Models\User;
use App\Support\HtmlDeCorreo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Campañas manuales de correo (remarketing, fase 4). Reutilizan lo de las fases 1 a 3:
 * mail_campaigns / mail_campaign_recipients, el envío por tandas de campanas:enviar
 * (EnvioPorTandas), el cupo de EnvioPromocional y los destinatarios fiables de
 * User::destinatariosDePromociones() (con evidencia del correo y sin baja).
 *
 * payload de una campaña:
 *  - contenido.es: asunto, preheader, titular, cuerpo (HTML limpio, HtmlDeCorreo),
 *    boton_texto, boton_url (https) e imagen_alt. Hoy solo se escribe y se envía en
 *    español (IDIOMAS_ACTIVOS); las campañas antiguas con contenido.pt/en se envían en
 *    español sin perder esos datos.
 *  - imagen: ruta en el disco public (ImagenDeCampana) o null.
 *  - segmento: filtros avanzados opcionales. Vacío = todos los destinatarios fiables.
 *    paises (users.country), idiomas (users.locale; "es" incluye a quien no tiene idioma)
 *    y solo_compradores (con un pedido pagado).
 *
 * Estados: borrador → programada → enviando ⇄ pausada → completada; cancelable antes de
 * terminar. Los destinatarios se fijan al empezar el envío (no al crearla): quien se da de
 * baja antes no entra, y EnvioPromocional lo vuelve a comprobar en cada correo.
 */
class CampanaManual
{
    /** Idiomas que el payload sabe guardar (las campañas antiguas tienen los tres). */
    public const IDIOMAS = ['es' => 'Español', 'pt' => 'Português', 'en' => 'English'];

    /**
     * Idiomas que se escriben en el panel y se envían. Hoy solo español: todos los clientes
     * reciben la campaña en español, sea cual sea su idioma o país. Para reactivar otro
     * idioma basta sumarlo acá (el formulario, la vista previa y el envío lo leen).
     */
    public const IDIOMAS_ACTIVOS = ['es'];

    public function __construct(private EnvioPromocional $envio) {}

    /** Idiomas activos con su nombre, para el panel. */
    public static function idiomasActivos(): array
    {
        return array_intersect_key(self::IDIOMAS, array_flip(self::IDIOMAS_ACTIVOS));
    }

    /** El idioma en que se le escribe a un cliente: el suyo si está activo; si no, español. */
    public static function idiomaDeEnvio(?string $idioma): string
    {
        return in_array($idioma, self::IDIOMAS_ACTIVOS, true) ? $idioma : 'es';
    }

    // ── Contenido ───────────────────────────────────────────────────────────

    /** Contenido de un idioma completo (asunto, titular y cuerpo), o null. */
    public static function contenidoDe(array $payload, string $idioma): ?array
    {
        $c = $payload['contenido'][$idioma] ?? null;

        return is_array($c) && filled($c['asunto'] ?? null) && filled($c['titular'] ?? null) && filled(HtmlDeCorreo::limpiar($c['cuerpo'] ?? ''))
            ? $c
            : null;
    }

    /**
     * El contenido que recibe alguien en ese idioma: el suyo si es un idioma activo y la
     * campaña lo tiene; si no, el español (también en campañas antiguas con pt/en).
     */
    public static function contenidoPara(array $payload, string $idioma): array
    {
        return self::contenidoDe($payload, self::idiomaDeEnvio($idioma)) ?? self::contenidoDe($payload, 'es') ?? [];
    }

    /** Idiomas activos sin contenido propio, que recibirán el español (hoy ninguno). */
    public static function idiomasConEspanol(array $payload): array
    {
        return array_values(array_filter(array_diff(self::IDIOMAS_ACTIVOS, ['es']), fn ($i) => self::contenidoDe($payload, $i) === null));
    }

    /** Limpia el HTML de los cuerpos (al guardar). */
    public static function limpiarPayload(array $payload): array
    {
        // Solo la vista previa usa una URL de imagen directa; nunca se guarda.
        unset($payload['imagen_url_previa']);

        foreach (array_keys(self::IDIOMAS) as $idioma) {
            if (isset($payload['contenido'][$idioma]['cuerpo'])) {
                $payload['contenido'][$idioma]['cuerpo'] = HtmlDeCorreo::limpiar($payload['contenido'][$idioma]['cuerpo']);
            }
            foreach (['asunto', 'preheader', 'titular', 'boton_texto', 'boton_url', 'imagen_alt'] as $campo) {
                if (isset($payload['contenido'][$idioma][$campo])) {
                    $payload['contenido'][$idioma][$campo] = trim(strip_tags((string) $payload['contenido'][$idioma][$campo]));
                }
            }
        }

        return $payload;
    }

    /** Por qué no se puede enviar todavía (lista vacía = se puede). */
    public static function errores(MailCampaign $campana): array
    {
        $p = $campana->payload ?? [];
        $errores = [];

        if (self::contenidoDe($p, 'es') === null) {
            $errores[] = 'Falta el contenido en español (asunto, titular y texto): es el que se usa si falta otro idioma.';
        }

        foreach (self::IDIOMAS_ACTIVOS as $idioma) {
            $c = $p['contenido'][$idioma] ?? [];
            if ($idioma !== 'es' && self::contenidoDe($p, $idioma) === null) {
                continue;
            }
            if (filled($p['imagen'] ?? null) && blank($c['imagen_alt'] ?? null)) {
                $errores[] = "Falta el texto alternativo de la imagen ({$idioma}).";
            }
            if (filled($c['boton_texto'] ?? null) xor filled($c['boton_url'] ?? null)) {
                $errores[] = "El botón necesita texto y enlace ({$idioma}).";
            }
            if (filled($c['boton_url'] ?? null) && HtmlDeCorreo::hrefSeguro($c['boton_url']) === null) {
                $errores[] = "El enlace del botón tiene que empezar con https:// ({$idioma}).";
            }
        }

        return $errores;
    }

    // ── Destinatarios ───────────────────────────────────────────────────────

    /** Clientes activos del segmento (antes de exigir evidencia y que no estén de baja). */
    public static function consulta(array $segmento): Builder
    {
        $paises = array_values(array_filter((array) ($segmento['paises'] ?? [])));
        $idiomas = array_values(array_intersect((array) ($segmento['idiomas'] ?? []), array_keys(self::IDIOMAS)));

        return User::query()
            ->whereHas('role', fn (Builder $q) => $q->where('slug', 'cliente'))
            ->where('status', 'active')
            ->when($paises !== [], fn (Builder $q) => $q->whereIn('country', $paises))
            ->when($idiomas !== [], fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereIn('locale', $idiomas)
                ->when(in_array('es', $idiomas, true), fn (Builder $w2) => $w2->orWhereNull('locale')->orWhereNotIn('locale', array_keys(self::IDIOMAS)))))
            ->when(! empty($segmento['solo_compradores']), fn (Builder $q) => $q->whereHas('orders', fn (Builder $o) => $o->where('payment_status', 'paid')));
    }

    /** @return Collection<int, User> */
    public function destinatarios(MailCampaign $campana): Collection
    {
        return User::destinatariosDePromociones(self::consulta($campana->payload['segmento'] ?? []));
    }

    /**
     * Números del segmento para el panel. Una cuenta puede estar en más de un grupo de
     * excluidos (por ejemplo sin evidencia y dada de baja).
     */
    public function resumen(MailCampaign $campana): array
    {
        $segmento = $campana->payload['segmento'] ?? [];
        $base = fn () => self::consulta($segmento);
        $recibirian = $this->destinatarios($campana)->count();
        $libresHoy = max(0, (int) config('marketing.limits.per_day', 100) - $this->envio->enviadosHoy());

        return [
            'recibirian' => $recibirian,
            'sin_evidencia' => $base()->count() - $base()->conCorreoComprobado()->count(),
            'baja' => $base()->whereNotNull('promo_unsubscribed_at')->count(),
            'caben_hoy' => min($recibirian, $libresHoy),
            'por_dia' => (int) config('marketing.limits.per_day', 100),
            'idiomas_con_espanol' => self::idiomasConEspanol($campana->payload ?? []),
        ];
    }

    // ── Estados ─────────────────────────────────────────────────────────────

    /**
     * Programa la campaña (si scheduled_at es futuro) o empieza a enviarla ya. Solo desde
     * borrador; un doble clic no la programa dos veces (cambio de estado atómico).
     */
    public function programar(MailCampaign $campana): MailCampaign
    {
        if ($errores = self::errores($campana)) {
            throw new RuntimeException(implode(' ', $errores));
        }

        if ($campana->scheduled_at && $campana->scheduled_at->isFuture()) {
            $this->cambiar($campana, [MailCampaign::BORRADOR], MailCampaign::PROGRAMADA);

            return $campana->fresh();
        }

        return $this->iniciar($campana);
    }

    /**
     * Pasa a "enviando" y fija los destinatarios del momento (en una transacción). Desde
     * borrador o programada; si otro proceso ya la empezó, no hace nada dos veces.
     */
    public function iniciar(MailCampaign $campana): MailCampaign
    {
        DB::transaction(function () use ($campana) {
            $this->cambiar($campana, [MailCampaign::BORRADOR, MailCampaign::PROGRAMADA], MailCampaign::ENVIANDO);

            $campana->refresh();
            $campana->agregarDestinatarios($this->destinatarios($campana));
            $campana->recontar();

            if ($campana->total_count === 0) {
                $campana->update(['status' => MailCampaign::COMPLETADA, 'finished_at' => now()]);
            }
        });

        return $campana->fresh();
    }

    /**
     * Las programadas cuya hora ya llegó (también si el servidor estuvo caído y la hora
     * pasó hace rato). Lo llama campanas:enviar cada minuto. Devuelve cuántas empezaron.
     */
    public function iniciarProgramadas(): int
    {
        $iniciadas = 0;
        foreach (MailCampaign::where('type', MailCampaign::TIPO_MANUAL)->where('status', MailCampaign::PROGRAMADA)
            ->where('scheduled_at', '<=', now())->orderBy('scheduled_at')->get() as $campana) {
            try {
                $this->iniciar($campana);
                $iniciadas++;
            } catch (RuntimeException) {
                // Otro proceso la tomó primero.
            }
        }

        return $iniciadas;
    }

    public function pausar(MailCampaign $campana): void
    {
        $this->cambiar($campana, [MailCampaign::ENVIANDO], MailCampaign::PAUSADA);
    }

    public function reanudar(MailCampaign $campana): void
    {
        $this->cambiar($campana, [MailCampaign::PAUSADA], MailCampaign::ENVIANDO);
    }

    public function volverABorrador(MailCampaign $campana): void
    {
        $this->cambiar($campana, [MailCampaign::PROGRAMADA], MailCampaign::BORRADOR);
    }

    /** Cancela: lo no enviado queda cancelado (no se pierde el registro). */
    public function cancelar(MailCampaign $campana, string $motivo = 'cancelada desde el panel'): void
    {
        $actual = $campana->estadoActual();
        if (! in_array($actual, [MailCampaign::BORRADOR, MailCampaign::PROGRAMADA, MailCampaign::ENVIANDO, MailCampaign::PAUSADA], true)) {
            throw new RuntimeException('Esta campaña ya terminó: no se puede cancelar.');
        }

        $campana->refresh()->cancelar($motivo);
    }

    /** Copia como borrador nuevo (sin fecha de envío ni destinatarios). */
    public function duplicar(MailCampaign $campana, ?User $admin = null): MailCampaign
    {
        return MailCampaign::create([
            'type' => MailCampaign::TIPO_MANUAL,
            'name' => 'Copia de '.$campana->name,
            'dedupe_key' => 'manual:'.Str::uuid(),
            'payload' => $campana->payload,
            'status' => MailCampaign::BORRADOR,
            'created_by' => $admin?->id,
        ]);
    }

    /** Correo de ejemplo (vista previa y prueba): no crea destinatarios ni cuenta en el cupo. */
    public function correoDePrueba(MailCampaign $campana, string $idioma = 'es'): CorreoCampana
    {
        return self::correoDeEjemplo($campana->payload ?? [], $idioma);
    }

    /**
     * Correo armado con un payload que todavía no se guardó (la vista previa en vivo y la
     * prueba desde el formulario), para un cliente de ejemplo con un enlace de baja
     * ficticio. No crea filas, no pasa por EnvioPromocional y no cuenta en el cupo.
     * $imagenUrl: la imagen recién subida (URL temporal), si todavía no tiene ruta.
     */
    public static function correoDeEjemplo(array $payload, string $idioma = 'es', ?string $imagenUrl = null): CorreoCampana
    {
        $cliente = (new User)->forceFill([
            'id' => (string) Str::uuid(),
            'first_name' => 'Cliente',
            'last_name' => 'de ejemplo',
            'email' => 'cliente.de.ejemplo@fathermotosport.com',
            'locale' => $idioma,
        ]);

        $payload = self::limpiarPayload($payload);
        if ($imagenUrl !== null) {
            $payload['imagen_url_previa'] = $imagenUrl;
        }

        return new CorreoCampana($cliente, $payload, 'prueba:'.Str::uuid());
    }

    /** Cambio de estado atómico: solo si sigue en uno de $desde. */
    private function cambiar(MailCampaign $campana, array $desde, string $hacia): void
    {
        $cambiadas = MailCampaign::whereKey($campana->getKey())->where('type', MailCampaign::TIPO_MANUAL)
            ->whereIn('status', $desde)->update(['status' => $hacia, 'updated_at' => now()]);

        if ($cambiadas === 0) {
            throw new RuntimeException('La campaña cambió de estado mientras tanto. Recarga la página.');
        }

        $campana->refresh();
    }
}
