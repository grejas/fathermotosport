{{--
  Campaña manual (promocional, CorreoCampana). Misma base que el aviso de descuento:
  tablas y estilos en línea para Outlook, Gmail y Apple Mail; ancho máximo 600 px; colores
  en hex con bgcolor; color-scheme para que el modo oscuro no lo invierta.

  Orden de lectura: cabecera, imagen (opcional, con alt), titular, texto, botón (opcional),
  enlace a la tienda y pie con contacto y baja visible. El texto llega limpio
  (HtmlDeCorreo: párrafos, negrita, cursiva y enlaces https) y con estilos en línea.
--}}
<!DOCTYPE html>
<html lang="{{ $idiomaCorreo }}" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{{ $asunto }}</title>
<!--[if mso]>
<noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
<![endif]-->
<style>
  @media only screen and (max-width: 480px) {
    .px { padding-left: 20px !important; padding-right: 20px !important; }
    .titular { font-size: 24px !important; line-height: 30px !important; }
    .fondo { padding: 12px 8px !important; }
  }
  @media (prefers-color-scheme: dark) {
    .fondo, .fondo-tabla { background: #000000 !important; }
  }
  a { text-decoration: none; }
</style>
</head>
<body style="margin:0; padding:0; background:#F2F2F2; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;" bgcolor="#F2F2F2">

@if ($preheader !== '')
<div style="display:none; max-height:0; max-width:0; overflow:hidden; opacity:0; mso-hide:all; font-size:1px; line-height:1px; color:#F2F2F2;">
  {{ $preheader }}&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;
</div>
@endif

<table role="presentation" class="fondo-tabla" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F2F2F2" style="background:#F2F2F2;">
  <tr>
    <td class="fondo" align="center" style="padding:32px 12px;">
      <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#0A0A0A"
             style="max-width:600px; width:100%; background:#0A0A0A; border-radius:16px; overflow:hidden; font-family:'Inter',Arial,Helvetica,sans-serif;">

        <tr>
          <td class="px" bgcolor="#0A0A0A" style="background:#0A0A0A; padding:20px 32px; border-bottom:3px solid #E8001D;">
            <a href="{{ $urlTienda }}" style="font-size:19px; line-height:24px; font-weight:700; letter-spacing:1px; color:#FFFFFF; text-decoration:none;"><span style="color:#FF4D5E;">Father</span>MotoSport</a>
          </td>
        </tr>

        @if ($imagen)
          <tr>
            <td style="padding:0; font-size:0; line-height:0;">
              @if ($botonUrl)<a href="{{ $botonUrl }}">@endif<img src="{{ $imagen }}" width="600" alt="{{ $imagenAlt }}"
                   style="display:block; width:100%; max-width:600px; height:auto; border:0;">@if ($botonUrl)</a>@endif
            </td>
          </tr>
        @endif

        <tr>
          <td class="px" style="padding:28px 32px 0 32px;">
            <h1 class="titular" style="margin:0 0 14px 0; font-size:28px; line-height:34px; font-weight:800; color:#FFFFFF;">{{ $titular }}</h1>
            {!! $cuerpoHtml !!}
          </td>
        </tr>

        @if ($botonUrl && $botonTexto !== '')
          <tr>
            <td class="px" style="padding:12px 32px 0 32px;">
              <!--[if mso]>
              <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $botonUrl }}"
                           style="height:58px; v-text-anchor:middle; width:536px;" arcsize="18%" stroke="f" fillcolor="#E8001D">
                <w:anchorlock/>
                <center style="color:#FFFFFF; font-family:Arial,sans-serif; font-size:17px; font-weight:bold;">{{ $botonTexto }}</center>
              </v:roundrect>
              <![endif]-->
              <!--[if !mso]><!-->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td align="center" bgcolor="#E8001D" style="background:#E8001D; border-radius:12px;">
                    <a href="{{ $botonUrl }}" target="_blank"
                       style="display:block; padding:19px 24px; font-size:17px; line-height:22px; font-weight:800; letter-spacing:0.3px; color:#FFFFFF; text-decoration:none; border-radius:12px;">
                      {{ $botonTexto }}
                    </a>
                  </td>
                </tr>
              </table>
              <!--<![endif]-->
            </td>
          </tr>
        @endif

        <tr>
          <td class="px" align="center" style="padding:24px 32px 30px 32px; font-size:14px; line-height:20px;">
            <a href="{{ $urlTienda }}" style="color:#FFFFFF; text-decoration:underline;">{{ $textoTienda }}</a>
          </td>
        </tr>

        {{-- Pie: por qué lo recibe, contacto y baja visible. --}}
        <tr>
          <td class="px" bgcolor="#050505" align="center" style="background:#050505; border-top:1px solid #1C1C1C; padding:22px 32px 26px 32px; font-size:13px; line-height:21px; color:#8F8F8F;">
            {{ \App\Support\PieDeCorreo::TIENDA }}<br>
            {{ __('marketing.footer_reason', [], $idiomaCorreo) }}<br>
            <a href="mailto:{{ $emailContacto }}" style="color:#C9C9C9; text-decoration:underline;">{{ $textoContacto }}</a>
            @if ($whatsapp)
              &nbsp;·&nbsp; <a href="{{ $whatsapp }}" style="color:#C9C9C9; text-decoration:underline;">WhatsApp</a>
            @endif
            <div style="margin-top:14px;">
              <a href="{{ $urlBaja }}" style="font-size:13px; color:#C9C9C9; text-decoration:underline;">{{ __('marketing.unsubscribe', [], $idiomaCorreo) }}</a>
            </div>
          </td>
        </tr>

      </table>
      <!--[if mso]></td></tr></table><![endif]-->
    </td>
  </tr>
</table>
</body>
</html>
