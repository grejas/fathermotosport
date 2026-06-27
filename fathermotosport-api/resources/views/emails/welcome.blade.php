@component('mail::message')
# ¡Bienvenido a FatherMotoSport, {{ $user->first_name }}!

Gracias por crear tu cuenta. Como regalo de bienvenida, aquí tienes un cupón de **$5 de descuento** en tu primera compra:

@component('mail::panel')
## {{ $coupon->code }}
$5 de descuento — válido hasta {{ $coupon->end_date?->format('d/m/Y') }}
@endcomponent

Usa este código al finalizar tu compra. ¡Y recuerda que el envío siempre es **gratis**!

Nos vemos en la ruta,
**El equipo de FatherMotoSport**
@endcomponent
