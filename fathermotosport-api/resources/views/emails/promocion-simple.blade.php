@extends('emails.layouts.promocional')

@section('content')
  <div class="title">{{ $contenido['titulo'] }}</div>
  <div class="text">{!! nl2br(e($contenido['texto'])) !!}</div>
  @if ($url && ! empty($contenido['boton']))
    <a href="{{ $url }}" class="btn">{{ $contenido['boton'] }}</a>
  @endif
@endsection
