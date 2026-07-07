@extends('emails.layouts.base')

@section('emoji', '🏍️')

@section('content')
  <div class="greeting">Hola{{ $employee->first_name ? ' ' . $employee->first_name : '' }},</div>
  <div class="title">Bienvenido al equipo FatherMotoSport</div>
  <div class="text">El administrador te agregó como parte del equipo. Estas son tus credenciales de acceso al panel:</div>

  <div class="creds-box">
    <div class="cred-row"><span class="cred-k">Email</span><span class="cred-v">{{ $employee->email }}</span></div>
    <div class="cred-row"><span class="cred-k">Contraseña</span><span class="cred-v">{{ $password }}</span></div>
  </div>

  <a href="{{ $panelUrl }}" class="btn">Acceder al panel</a>

  <div class="warning">🔒 Por tu seguridad, cambiá tu contraseña en tu primer ingreso.</div>
@endsection
