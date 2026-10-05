<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Textos de envío y devoluciones editables desde el panel (fila única, id=1).
 *
 * Cada texto es un JSON por idioma: {"es": "...", "pt": "...", "en": "..."}; las
 * listas (*_items) guardan un array de strings por idioma. Los textos pueden usar
 * {hours} y {days}, que la web reemplaza con damage_report_hours / withdrawal_days.
 */
class ShippingReturnsSetting extends Model
{
    public const LOCALES = ['es', 'pt', 'en'];

    public const CACHE_KEY = 'shipping_returns_settings';

    /** Textos de una línea o párrafo. */
    public const TEXT_FIELDS = [
        'badge_shipping',
        'badge_returns',
        'summary_shipping',
        'summary_damaged',
        'summary_withdrawal',
        'page_title',
        'page_intro',
        'damaged_title',
        'withdrawal_title',
        'process_title',
        'cancellations_title',
        'help_text',
    ];

    /** Listas de puntos de la página /returns-policy. */
    public const LIST_FIELDS = [
        'damaged_items',
        'withdrawal_items',
        'process_items',
        'cancellations_items',
    ];

    protected $table = 'shipping_returns_settings';

    protected $guarded = ['id'];

    protected $casts = [
        'damage_report_hours' => 'integer',
        'withdrawal_days' => 'integer',
        'badge_shipping' => 'array',
        'badge_returns' => 'array',
        'summary_shipping' => 'array',
        'summary_damaged' => 'array',
        'summary_withdrawal' => 'array',
        'page_title' => 'array',
        'page_intro' => 'array',
        'damaged_title' => 'array',
        'damaged_items' => 'array',
        'withdrawal_title' => 'array',
        'withdrawal_items' => 'array',
        'process_title' => 'array',
        'process_items' => 'array',
        'cancellations_title' => 'array',
        'cancellations_items' => 'array',
        'help_text' => 'array',
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
     * Textos y plazos para un idioma. Un texto vacío (o una lista sin puntos)
     * sale como null, para que la web use su texto por defecto.
     */
    public function toPublicArray(string $locale): array
    {
        $texts = [];

        foreach (self::TEXT_FIELDS as $field) {
            $value = trim((string) data_get($this->{$field}, $locale, ''));
            $texts[$field] = $value === '' ? null : $value;
        }

        foreach (self::LIST_FIELDS as $field) {
            $items = array_values(array_filter(
                array_map(fn ($item) => trim((string) $item), (array) data_get($this->{$field}, $locale, [])),
                fn (string $item) => $item !== ''
            ));
            $texts[$field] = $items === [] ? null : $items;
        }

        return [
            'damage_report_hours' => $this->damage_report_hours,
            'withdrawal_days' => $this->withdrawal_days,
            'texts' => $texts,
        ];
    }
}
