{{--
  Aviso de descuento de categoría (promocional, CorreoAvisoDescuento).

  Plantilla propia en tablas y estilos en línea, como payment-recovery: Outlook (Word
  como motor), Gmail (recorta <style> en algunas apps) y Apple Mail. Ancho máximo
  600 px, una columna salvo la grilla de productos (2 por fila, también en el móvil),
  colores en hex (Outlook ignora rgba) y bgcolor además de background en cada fondo.

  Orden de lectura: banner (imagen de la categoría o bloque rojo con el porcentaje),
  titular, subtítulo, vigencia (solo si hay fecha de fin), botón, productos, enlace a
  la tienda, confianza, pie con contacto y baja.
--}}
@php
  $dinero = fn ($valor) => '$'.number_format((float) $valor, 2);
@endphp
<!DOCTYPE html>
<html lang="{{ $idiomaCorreo }}" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
{{-- Avisa que el correo ya es oscuro: Apple Mail no lo invierte. --}}
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{{ $asunto }}</title>
<!--[if mso]>
<noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
<![endif]-->
<style>
  /* Solo ajustes finos: todo lo necesario va en línea por si el cliente quita esto. */
  @media only screen and (max-width: 480px) {
    .px { padding-left: 20px !important; padding-right: 20px !important; }
    .titular { font-size: 24px !important; line-height: 30px !important; }
    .porcentaje { font-size: 34px !important; line-height: 40px !important; }
    .fondo { padding: 12px 8px !important; }
    .celda { padding: 6px !important; }
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
  {{ $preheader }}&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;
</div>

<table role="presentation" class="fondo-tabla" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F2F2F2" style="background:#F2F2F2;">
  <tr>
    <td class="fondo" align="center" style="padding:32px 12px;">
      <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#0A0A0A"
             style="max-width:600px; width:100%; background:#0A0A0A; border-radius:16px; overflow:hidden; font-family:'Inter',Arial,Helvetica,sans-serif;">

        {{-- Cabecera oscura con el logo de texto; el rojo queda para el descuento. --}}
        <tr>
          <td class="px" bgcolor="#0A0A0A" style="background:#0A0A0A; padding:20px 32px; border-bottom:3px solid #E8001D;">
            <a href="{{ $urlTienda }}" style="font-size:19px; line-height:24px; font-weight:700; letter-spacing:1px; color:#FFFFFF; text-decoration:none;"><span style="color:#FF4D5E;">Father</span>MotoSport</a>
          </td>
        </tr>

        {{-- Banner: la imagen de la categoría o, sin imagen, el porcentaje en grande. --}}
        <tr>
          @if ($banner)
            <td style="padding:0; font-size:0; line-height:0;">
              <a href="{{ $url }}"><img src="{{ $banner }}" width="600" alt="{{ $nombreCategoria }}"
                   style="display:block; width:100%; max-width:600px; height:auto; border:0;"></a>
            </td>
          @else
            <td align="center" bgcolor="#E8001D" style="background:#E8001D; padding:24px 24px 22px 24px;">
              <div class="porcentaje" style="font-size:42px; line-height:48px; font-weight:800; letter-spacing:-1px; color:#FFFFFF;">{{ $porcentajeGrande }}</div>
              <div style="margin-top:4px; font-size:14px; line-height:19px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#FFFFFF;">{{ $nombreCategoria }}</div>
            </td>
          @endif
        </tr>

        <tr>
          <td class="px" style="padding:28px 32px 0 32px;">
            <h1 class="titular" style="margin:0 0 10px 0; font-size:28px; line-height:34px; font-weight:800; color:#FFFFFF;">{{ $titular }}</h1>
            <p style="margin:0; font-size:16px; line-height:25px; color:#C9C9C9;">{{ $subtitulo }}</p>

            @if ($textoVigencia)
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:18px;">
                <tr>
                  <td bgcolor="#1F0A0D" style="background:#1F0A0D; border:1px solid #5C1720; border-radius:20px; padding:7px 14px; font-size:14px; line-height:19px; font-weight:600; color:#FFB3BB;">
                    {{ $textoVigencia }}
                  </td>
                </tr>
              </table>
            @endif

            @if ($excepto)
              <p style="margin:14px 0 0 0; font-size:13px; line-height:19px; color:#A3A3A3;">{{ $excepto }}</p>
            @endif
          </td>
        </tr>

        {{-- Botón principal "bulletproof": VML para Outlook, tabla para el resto. --}}
        <tr>
          <td class="px" style="padding:24px 32px 0 32px;">
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}"
                         style="height:58px; v-text-anchor:middle; width:536px;" arcsize="18%" stroke="f" fillcolor="#E8001D">
              <w:anchorlock/>
              <center style="color:#FFFFFF; font-family:Arial,sans-serif; font-size:17px; font-weight:bold;">{{ $boton }}</center>
            </v:roundrect>
            <![endif]-->
            <!--[if !mso]><!-->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td align="center" bgcolor="#E8001D" style="background:#E8001D; border-radius:12px;">
                  <a href="{{ $url }}" target="_blank"
                     style="display:block; padding:19px 24px; font-size:17px; line-height:22px; font-weight:800; letter-spacing:0.3px; color:#FFFFFF; text-decoration:none; border-radius:12px;">
                    {{ $boton }}
                  </a>
                </td>
              </tr>
            </table>
            <!--<![endif]-->
          </td>
        </tr>

        {{-- Productos destacados: 2 por fila; sin productos, la sección no está. --}}
        @if (count($productos))
          <tr>
            <td class="px" style="padding:34px 26px 0 26px;">
              <div style="padding:0 6px 8px 6px; font-size:13px; line-height:18px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; color:#A3A3A3;">{{ $tituloProductos }}</div>
              @foreach (array_chunk($productos, 2) as $fila)
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="table-layout:fixed;">
                  <tr>
                    @if (count($fila) === 1)<td width="25%" style="width:25%;">&nbsp;</td>@endif
                    @foreach ($fila as $p)
                      <td class="celda" width="50%" valign="top" style="width:50%; padding:6px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#141414"
                               style="background:#141414; border:1px solid #262626; border-radius:12px;">
                          @if ($p['imagen'])
                            <tr>
                              <td style="padding:10px 10px 0 10px; font-size:0; line-height:0;">
                                <a href="{{ $p['url'] }}"><img src="{{ $p['imagen'] }}" width="236" alt="{{ $p['nombre'] }}"
                                     style="display:block; width:100%; max-width:236px; height:auto; border:0; border-radius:8px; background:#1C1C1C;"></a>
                              </td>
                            </tr>
                          @endif
                          <tr>
                            <td style="padding:12px 12px 14px 12px;">
                              <a href="{{ $p['url'] }}" style="display:block; font-size:14px; line-height:19px; font-weight:700; color:#FFFFFF; text-decoration:none;">{{ $p['nombre'] }}</a>
                              <div style="margin-top:8px; font-size:13px; line-height:18px; color:#A3A3A3;">
                                {{ $antes }} <span style="text-decoration:line-through;">{{ $dinero($p['precio']) }}</span>
                              </div>
                              <div style="font-size:20px; line-height:26px; font-weight:800; color:#FF4D5E;">
                                <span style="font-size:13px; font-weight:600; color:#C9C9C9;">{{ $ahora }}</span> {{ $dinero($p['precio_final']) }}
                              </div>
                              <a href="{{ $p['url'] }}" style="display:inline-block; margin-top:10px; padding:8px 12px; border:1px solid #E8001D; border-radius:8px; font-size:13px; line-height:16px; font-weight:700; color:#FFFFFF; text-decoration:none;">{{ $verProducto }}</a>
                            </td>
                          </tr>
                        </table>
                      </td>
                    @endforeach
                    @if (count($fila) === 1)<td width="25%" style="width:25%;">&nbsp;</td>@endif
                  </tr>
                </table>
              @endforeach
            </td>
          </tr>
        @endif

        {{-- Enlace secundario, discreto, a toda la tienda. --}}
        <tr>
          <td class="px" align="center" style="padding:24px 32px 0 32px; font-size:14px; line-height:20px;">
            <a href="{{ $urlTienda }}" style="color:#FFFFFF; text-decoration:underline;">{{ $textoTienda }}</a>
          </td>
        </tr>

        {{-- Confianza: los mismos textos que muestra la web. --}}
        <tr>
          <td class="px" style="padding:26px 32px 30px 32px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #222222;">
              <tr>
                <td align="center" style="padding-top:18px; font-size:13px; line-height:21px; color:#C9C9C9;">
                  @foreach ($confianza as $i => $texto)
                    {{-- Sin separador: el ✓ ya divide, y en el móvil cada texto baja entero. --}}
                    <span style="white-space:nowrap;"><span style="color:#FF4D5E;">&#10003;</span>&nbsp;{{ $texto }}</span>@if ($i < count($confianza) - 1)&nbsp;&nbsp;&nbsp; @endif
                  @endforeach
                </td>
              </tr>
            </table>
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
