<?php

namespace App\Support;

use App\Models\StoreConfig;
use Throwable;

/**
 * Textos del pie de los correos, por idioma. Los usan el layout base y el correo de
 * recuperación de pago, que tiene su propia plantilla en tablas.
 *
 * Van en código y no en lang/ a propósito: así ningún correo depende de esos archivos
 * ni de APP_LOCALE, y los que no indican idioma salen en español, como siempre.
 */
class PieDeCorreo
{
    /** Solo el nombre: el pie no lleva ciudad ni país. */
    public const TIENDA = 'FatherMotoSport';

    /** Respaldo si no se puede leer "Configuración de la tienda" (sin fila o sin base de datos). */
    public const EMAIL_RESPALDO = 'contacto@fathermotosport.com';

    public const WHATSAPP_RESPALDO = '59168736384';

    /**
     * Correo y WhatsApp (solo dígitos) del panel. Un correo vacío usa el de respaldo;
     * un WhatsApp vacío queda en null y el pie no muestra el enlace.
     *
     * @return array{email: string, whatsapp: ?string}
     */
    public static function contacto(): array
    {
        try {
            $config = StoreConfig::current();
        } catch (Throwable $e) {
            report($e);
            $config = null;
        }

        if ($config === null) {
            return ['email' => self::EMAIL_RESPALDO, 'whatsapp' => self::WHATSAPP_RESPALDO];
        }

        $email = trim((string) $config->email);

        return [
            'email' => $email !== '' ? $email : self::EMAIL_RESPALDO,
            'whatsapp' => StoreConfig::soloDigitos($config->whatsapp),
        ];
    }

    /** Enlace de WhatsApp del pie, o null si el panel no tiene número. */
    public static function whatsappUrl(): ?string
    {
        $digitos = self::contacto()['whatsapp'];

        return $digitos !== null ? "https://wa.me/{$digitos}" : null;
    }

    private const TEXTOS = [
        'es' => ['envio' => 'Envío gratis a toda América y Europa', 'tienda' => 'Ver tienda'],
        'pt' => ['envio' => 'Frete grátis para toda a América e Europa', 'tienda' => 'Ver loja'],
        'en' => ['envio' => 'Free shipping to all of the Americas and Europe', 'tienda' => 'Visit store'],
    ];

    /** Idioma del pie: el pedido, si es uno de la tienda; si no, español. */
    public static function idioma(?string $locale): string
    {
        return array_key_exists((string) $locale, self::TEXTOS) ? $locale : 'es';
    }

    /** @return array{envio: string, tienda: string} */
    public static function textos(?string $locale): array
    {
        return self::TEXTOS[self::idioma($locale)];
    }
}
