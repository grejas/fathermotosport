<?php

namespace App\Support;

/**
 * Textos del pie de los correos, por idioma. Los usan el layout base y el correo de
 * recuperación de pago, que tiene su propia plantilla en tablas.
 *
 * Van en código y no en lang/ a propósito: así ningún correo depende de esos archivos
 * ni de APP_LOCALE, y los que no indican idioma salen en español, como siempre.
 */
class PieDeCorreo
{
    public const DIRECCION = 'FatherMotoSport · Cochabamba, Bolivia';

    public const WHATSAPP = 'https://wa.me/59168736384';

    private const TEXTOS = [
        'es' => ['envio' => 'Envío gratuito a Bolivia y Brasil', 'tienda' => 'Ver tienda', 'baja' => 'Cancelar suscripción'],
        'pt' => ['envio' => 'Frete grátis para Bolívia e Brasil', 'tienda' => 'Ver loja', 'baja' => 'Cancelar inscrição'],
        'en' => ['envio' => 'Free shipping to Bolivia and Brazil', 'tienda' => 'Visit store', 'baja' => 'Unsubscribe'],
    ];

    /** Idioma del pie: el pedido, si es uno de la tienda; si no, español. */
    public static function idioma(?string $locale): string
    {
        return array_key_exists((string) $locale, self::TEXTOS) ? $locale : 'es';
    }

    /** @return array{envio: string, tienda: string, baja: string} */
    public static function textos(?string $locale): array
    {
        return self::TEXTOS[self::idioma($locale)];
    }
}
