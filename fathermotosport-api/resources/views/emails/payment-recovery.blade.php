{{--
  Correo de recuperación de pago. Transaccional a propósito (Gmail lo mandaba a
  Promociones): una sola imagen por producto, un solo botón, sin insignias, sin
  promesas comerciales y sin enlaces de lista de correo.

  Plantilla propia en tablas y estilos en línea, para
  Outlook (Word como motor), Gmail (recorta <style> en algunas apps) y Apple Mail.
  Misma estética que el resto de los correos (layouts/base): tarjeta negra, cabecera
  roja con el logo de texto, acento #E8001D.

  Reglas: ancho máximo 600 px, una columna, ningún texto bajo 13 px, colores en hex
  (Outlook ignora rgba) y bgcolor además de background en cada fondo.
--}}
@php
  $l = $emailLocale;
  $t = fn (string $clave, array $datos = []) => __("payment_recovery.{$clave}", $datos, $l);
  $dinero = fn ($valor) => '$'.number_format((float) $valor, 2);
@endphp
<!DOCTYPE html>
<html lang="{{ $l }}" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
{{-- Avisa que el correo ya es oscuro: Apple Mail no lo invierte. --}}
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{{ $t('subject') }}</title>
<!--[if mso]>
<noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
<![endif]-->
<style>
  /* Solo ajustes finos: todo lo necesario va en línea por si el cliente quita esto. */
  @media only screen and (max-width: 480px) {
    .px { padding-left: 20px !important; padding-right: 20px !important; }
    .titulo { font-size: 23px !important; line-height: 30px !important; }
    .fondo { padding: 12px 8px !important; }
  }
  @media (prefers-color-scheme: dark) {
    .fondo, .fondo-tabla { background: #000000 !important; }
  }
  a { text-decoration: none; }
</style>
</head>
<body style="margin:0; padding:0; background:#F2F2F2; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;" bgcolor="#F2F2F2">

{{-- Texto de vista previa en la bandeja de entrada; no se ve en el cuerpo. --}}
<div style="display:none; max-height:0; max-width:0; overflow:hidden; opacity:0; mso-hide:all; font-size:1px; line-height:1px; color:#F2F2F2;">
  {{ $t('preheader') }}&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;
</div>

<table role="presentation" class="fondo-tabla" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F2F2F2" style="background:#F2F2F2;">
  <tr>
    <td class="fondo" align="center" style="padding:32px 12px;">
      <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#0A0A0A"
             style="max-width:600px; width:100%; background:#0A0A0A; border-radius:16px; overflow:hidden; font-family:'Inter',Arial,Helvetica,sans-serif;">

        {{-- Cabecera: el mismo logo de texto que los demás correos. --}}
        <tr>
          <td class="px" bgcolor="#E8001D" style="background:#E8001D; padding:22px 32px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="font-size:19px; line-height:24px; font-weight:700; letter-spacing:1px; color:#FFFFFF;">
                  <a href="{{ $urlTienda }}" style="color:#FFFFFF; text-decoration:none;"><span style="color:#FFC2C9;">Father</span>MotoSport</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td class="px" style="padding:32px 32px 8px 32px;">

            {{-- Pendiente, en ámbar: no es un error ni una confirmación. --}}
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td bgcolor="#2A2310" style="background:#2A2310; border:1px solid #5C4B1F; border-radius:20px; padding:5px 14px; font-size:13px; line-height:18px; font-weight:600; color:#E2C26A;">
                  &#9679;&nbsp; {{ $t('badge') }}
                </td>
              </tr>
            </table>

            <p style="margin:20px 0 6px 0; font-size:15px; line-height:22px; color:#A3A3A3;">
              {{ $t('greeting') }}{{ $nombre ? ' '.$nombre : '' }},
            </p>
            <h1 class="titulo" style="margin:0 0 20px 0; font-size:26px; line-height:33px; font-weight:800; color:#FFFFFF;">
              {{ $t('title', ['number' => $order->order_number]) }}
            </h1>

            {{-- Mensaje central, en un bloque destacado con el acento de la marca. --}}
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td bgcolor="#161616" style="background:#161616; border-left:4px solid #E8001D; border-radius:10px; padding:18px 20px; font-size:16px; line-height:25px; font-weight:500; color:#FFFFFF;">
                  {{ $t('message') }}
                </td>
              </tr>
            </table>
            <p style="margin:12px 0 0 0; font-size:13px; line-height:20px; color:#8F8F8F;">
              {{ $t('single_notice') }}
            </p>

          </td>
        </tr>

        {{-- Resumen del pedido. --}}
        <tr>
          <td class="px" style="padding:24px 32px 0 32px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#121212"
                   style="background:#121212; border:1px solid #262626; border-radius:12px;">
              <tr>
                <td style="padding:18px 20px 6px 20px; font-size:13px; line-height:18px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:#A3A3A3;">
                  {{ $t('summary') }} · {{ $order->order_number }}
                </td>
              </tr>

              @foreach($order->items as $item)
                @php
                  $producto = $item->variant?->product;
                  $imagen = $miniaturas[$item->id] ?? null;
                  $detalle = collect([
                    $item->size ? $t('size').' '.$item->size : null,
                    $item->color,
                    $t('quantity').': '.$item->quantity,
                  ])->filter()->implode(' · ');
                @endphp
                <tr>
                  <td style="padding:12px 20px; border-top:1px solid #222222;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        {{-- Sin imagen no queda un hueco: la columna directamente no está. --}}
                        @if($imagen)
                          <td width="64" valign="top" style="width:64px; padding-right:14px;">
                            <img src="{{ $imagen }}" width="64" height="64" alt="{{ $producto->name }}"
                                 style="display:block; width:64px; height:64px; border-radius:8px; border:0; object-fit:cover; background:#1C1C1C;">
                          </td>
                        @endif
                        <td valign="top">
                          <div style="font-size:15px; line-height:21px; font-weight:700; color:#FFFFFF;">{{ $producto?->name ?? $t('product') }}</div>
                          <div style="margin-top:3px; font-size:13px; line-height:19px; color:#A3A3A3;">{{ $detalle }}</div>
                        </td>
                        <td valign="top" align="right" style="padding-left:12px; white-space:nowrap; font-size:15px; line-height:21px; font-weight:700; color:#FFFFFF;">
                          {{ $dinero($item->subtotal) }}
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              @endforeach

              <tr>
                <td style="padding:14px 20px 4px 20px; border-top:1px solid #222222;">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px; line-height:24px;">
                    <tr>
                      <td style="color:#A3A3A3;">{{ $t('subtotal') }}</td>
                      <td align="right" style="color:#FFFFFF;">{{ $dinero($order->subtotal) }}</td>
                    </tr>
                    @if($order->discount > 0)
                      <tr>
                        <td style="color:#A3A3A3;">{{ $t('discount') }}</td>
                        <td align="right" style="color:#FFFFFF;">-{{ $dinero($order->discount) }}</td>
                      </tr>
                    @endif
                    <tr>
                      <td style="color:#A3A3A3;">{{ $t('shipping') }}{{ $order->shipping_method_name ? ' · '.$order->shipping_method_name : '' }}</td>
                      <td align="right" style="color:#FFFFFF;">{{ $dinero($order->shipping) }}</td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr>
                <td style="padding:10px 20px 18px 20px;">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #2E2E2E;">
                    <tr>
                      <td style="padding-top:12px; font-size:15px; line-height:22px; font-weight:700; color:#FFFFFF;">{{ $t('total') }}</td>
                      <td align="right" style="padding-top:12px; font-size:24px; line-height:30px; font-weight:800; color:#FF2E45;">{{ $dinero($order->total) }}</td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- Botón "bulletproof": VML redondeado para Outlook, tabla para el resto. --}}
        <tr>
          <td class="px" style="padding:28px 32px 0 32px;">
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $urlPago }}"
                         style="height:56px; v-text-anchor:middle; width:536px;" arcsize="18%" stroke="f" fillcolor="#E8001D">
              <w:anchorlock/>
              <center style="color:#FFFFFF; font-family:Arial,sans-serif; font-size:17px; font-weight:bold;">{{ $t('button') }}</center>
            </v:roundrect>
            <![endif]-->
            <!--[if !mso]><!-->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td align="center" bgcolor="#E8001D" style="background:#E8001D; border-radius:12px;">
                  <a href="{{ $urlPago }}" target="_blank"
                     style="display:block; padding:18px 24px; font-size:17px; line-height:22px; font-weight:800; letter-spacing:0.5px; color:#FFFFFF; text-decoration:none; border-radius:12px;">
                    {{ $t('button') }}
                  </a>
                </td>
              </tr>
            </table>
            <!--<![endif]-->
          </td>
        </tr>

        <tr><td style="height:32px; line-height:32px; font-size:13px;">&nbsp;</td></tr>

        {{-- Pie mínimo, de correo transaccional: la tienda y el contacto. Sin promesas de
             envío, enlaces a la tienda ni "cancelar suscripción" (es un aviso de un pedido,
             no una lista de correo). --}}
        <tr>
          <td class="px" bgcolor="#050505" align="center" style="background:#050505; border-top:1px solid #1C1C1C; padding:22px 32px; font-size:13px; line-height:21px; color:#8F8F8F;">
            {{ $nombreTienda }}<br>
            {!! $t('contact', ['email' => '<a href="mailto:'.e($contacto).'" style="color:#8F8F8F; text-decoration:underline;">'.e($contacto).'</a>']) !!}
          </td>
        </tr>

      </table>
      <!--[if mso]></td></tr></table><![endif]-->
    </td>
  </tr>
</table>
</body>
</html>
