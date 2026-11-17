<?php

namespace App\Support;

use App\Models\PopupLead;
use Illuminate\Support\Facades\URL;

/**
 * Baja de los contactos del popup (no tienen cuenta, así que no usan BajaDePromociones).
 * Enlace firmado por Laravel, sin vencimiento: GET muestra la confirmación, POST es la baja
 * de un clic (List-Unsubscribe-Post, RFC 8058) que piden Gmail y Yahoo.
 */
class BajaDelPopup
{
    public static function url(PopupLead $lead): string
    {
        return URL::signedRoute('popup.unsubscribe', ['lead' => $lead->getKey()]);
    }

    public static function darDeBaja(PopupLead $lead): void
    {
        if (! $lead->unsubscribed_at) {
            $lead->forceFill(['unsubscribed_at' => now()])->save();
        }
    }
}
