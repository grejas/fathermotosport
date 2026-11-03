{{-- Sin enlace de baja visible en el pie (decisión de la tienda); siguen las cabeceras
     List-Unsubscribe y List-Unsubscribe-Post, y quien se dio de baja no lo recibe. --}}
@extends('emails.layouts.promocional', ['mostrarBaja' => false])

@section('content')
  {{-- Texto de vista previa en la bandeja de entrada (oculto en el cuerpo). --}}
  <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">{{ $preheader }}</div>

  <div style="font-size:40px; line-height:1; margin-bottom:12px;">&#127874;</div>
  <div class="title">{{ $saludo }}</div>
  <div class="text">{{ $deseo }}</div>
  <div class="text">{{ $celebra }}</div>

  <div style="background:linear-gradient(135deg,#1a0505,#2a0808); border:2px dashed rgba(232,0,29,0.45); border-radius:12px; padding:22px 16px; text-align:center; margin:20px 0;">
    <div style="font-size:10px; letter-spacing:0.2em; text-transform:uppercase; color:rgba(232,0,29,0.8); margin-bottom:10px;">{{ $etiquetaCupon }}</div>
    <div style="font-size:15px; font-weight:700; color:#fff; margin-bottom:12px;">{{ $descuento }}</div>
    <div style="font-size:24px; font-weight:800; color:#fff; letter-spacing:0.12em; font-family:monospace; word-break:break-all; margin-bottom:10px;">{{ $codigo }}</div>
    <div style="font-size:12px; color:rgba(255,255,255,0.6);">{{ $textoVence }}</div>
  </div>

  <a href="{{ $url }}" class="btn">{{ $boton }}</a>

  <div class="text" style="font-size:12px; margin-bottom:6px;">{{ $personal }}</div>
  <div class="text" style="font-size:12px;">{{ $pistaCodigo }}</div>
@endsection
