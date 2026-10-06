<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Política de envío y devoluciones editable desde el panel (fila única, id=1).
 *
 * Cada texto es un JSON por idioma: {"es": "...", "pt": "...", "en": "..."}.
 * page_body es HTML del RichEditor. Los textos pueden usar {hours}, que la web
 * reemplaza con damage_report_hours.
 */
class ShippingReturnsSetting extends Model
{
    public const LOCALES = ['es', 'pt', 'en'];

    public const CACHE_KEY = 'shipping_returns_settings';

    /** Textos planos: etiqueta y resumen del acordeón, título de la página. */
    public const TEXT_FIELDS = ['badge_returns', 'summary', 'page_title'];

    protected $table = 'shipping_returns_settings';

    protected $guarded = ['id'];

    protected $casts = [
        'damage_report_hours' => 'integer',
        'badge_returns' => 'array',
        'summary' => 'array',
        'page_title' => 'array',
        'page_body' => 'array',
    ];

    protected static function booted(): void
    {
        // Cualquier guardado (panel, tinker, seeder) invalida la copia del endpoint.
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Devuelve la configuración única (singleton).
     */
    public static function current(): ?self
    {
        return static::query()->first();
    }

    /**
     * Limpia el HTML del editor (sin scripts, iframes, atributos on* ni enlaces
     * javascript:) y lo deja en null si no tiene texto visible.
     */
    public static function cleanHtml(?string $html): ?string
    {
        $clean = trim(Str::sanitizeHtml((string) $html));

        // &nbsp; decodificado es U+00A0, que trim() no quita.
        $visible = preg_replace('/[\s\x{00A0}]+/u', '', html_entity_decode(strip_tags($clean), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $visible === '' ? null : $clean;
    }

    /**
     * Textos y plazo para un idioma. Un campo vacío sale como null, para que la
     * web use su texto por defecto. El cuerpo se vuelve a sanitizar al servirlo,
     * por si la fila se escribió sin pasar por el panel.
     */
    public function toPublicArray(string $locale): array
    {
        $texts = [];

        foreach (self::TEXT_FIELDS as $field) {
            $value = trim((string) data_get($this->{$field}, $locale, ''));
            $texts[$field] = $value === '' ? null : $value;
        }

        $texts['page_body'] = self::cleanHtml(data_get($this->page_body, $locale));

        return [
            'damage_report_hours' => $this->damage_report_hours,
            'texts' => $texts,
        ];
    }
}
