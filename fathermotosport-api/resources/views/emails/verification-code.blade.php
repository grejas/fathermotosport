@extends('emails.layouts.base')

@section('emoji', '🔑')

@section('content')
  <div class="greeting">Hola,</div>
  <div class="title">Verificá tu email</div>
  <div class="text">Usá este código para completar tu registro en FatherMotoSport:</div>

  <div class="coupon-box">
    <div class="coupon-label">Tu código de verificación</div>
    <div class="coupon-code">{{ $code }}</div>
  </div>

  <div class="warning">⏱️ Este código expira en 10 minutos.</div>

  <div class="text">Si no solicitaste este código, podés ignorar este correo.</div>
@endsection
