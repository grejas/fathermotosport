@extends('emails.layouts.base')

@section('emoji', '🔒')

@section('content')
  <div class="greeting">Hola{{ $user->first_name ? ' ' . $user->first_name : '' }},</div>
  <div class="title">Recuperá tu contraseña</div>
  <div class="text">Recibimos una solicitud para restablecer la contraseña de tu cuenta. Hacé clic en el botón para crear una nueva:</div>

  <a href="{{ $resetUrl }}" class="btn">Restablecer contraseña</a>

  <div class="warning">⏱️ Este enlace expira en 60 minutos por tu seguridad.</div>

  <div class="text">Si no solicitaste este cambio, podés ignorar este correo — tu contraseña seguirá siendo la misma.</div>
@endsection
