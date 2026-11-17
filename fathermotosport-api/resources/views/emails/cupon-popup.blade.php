@extends('emails.layouts.promocional')

@section('content')
  {{-- Texto de vista previa en la bandeja de entrada (oculto en el cuerpo). --}}
  <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">{{ $preheader }}</div>

  <div class="title">{{ $titulo }}</div>
  <div class="text">{{ $texto }}</div>

  <div style="background:linear-gradient(135deg,#1a0505,#2a0808); border:2px dashed rgba(201,168,76,0.55); border-radius:12px; padding:22px 16px; text-align:center; margin:20px 0;">
    <div style="font-size:10px; letter-spacing:0.2em; text-transform:uppercase; color:#C9A84C; margin-bottom:10px;">{{ $etiquetaCupon }}</div>
    <div style="font-size:15px; font-weight:700; color:#fff; margin-bottom:12px;">{{ $descuento }}</div>
    <div style="font-size:26px; font-weight:800; color:#fff; letter-spacing:0.14em; font-family:monospace; word-break:break-all; margin-bottom:10px;">{{ $codigo }}</div>
    @if ($textoVence)
    <div style="font-size:12px; color:rgba(255,255,255,0.6);">{{ $textoVence }}</div>
    @endif
  </div>

  <a href="{{ $url }}" class="btn">{{ $boton }}</a>

  <div class="text" style="font-size:12px;">{{ $pista }}</div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px; border-top:1px solid rgba(255,255,255,0.08);">
    @foreach ($beneficios as $b)
    <tr><td style="padding:8px 0 0; font-size:12px; color:rgba(255,255,255,0.7);"><span style="color:#C9A84C;">&#10003;</span>&nbsp; {{ $b }}</td></tr>
    @endforeach
  </table>
@endsection
