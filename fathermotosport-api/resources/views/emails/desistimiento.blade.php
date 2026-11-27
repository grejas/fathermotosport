@extends('emails.layouts.base')

@section('emoji')&#8617;@endsection

@section('content')
  <div class="title">{{ $t('title') }}</div>
  <div class="text">{{ $t('intro', ['name' => $nombre]) }}</div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0; border:1px solid rgba(255,255,255,0.1); border-radius:12px;">
    <tr><td style="padding:12px 16px; font-size:13px; color:rgba(255,255,255,0.6); width:42%;">{{ $t('reference') }}</td><td style="padding:12px 16px; font-size:13px; color:#fff; font-weight:700;">{{ $referencia }}</td></tr>
    <tr><td style="padding:12px 16px; font-size:13px; color:rgba(255,255,255,0.6); border-top:1px solid rgba(255,255,255,0.08);">{{ $t('received_at') }}</td><td style="padding:12px 16px; font-size:13px; color:#fff; border-top:1px solid rgba(255,255,255,0.08);">{{ $fecha }}</td></tr>
    <tr><td style="padding:12px 16px; font-size:13px; color:rgba(255,255,255,0.6); border-top:1px solid rgba(255,255,255,0.08);">{{ $t('order') }}</td><td style="padding:12px 16px; font-size:13px; color:#fff; border-top:1px solid rgba(255,255,255,0.08);">{{ $pedido }}</td></tr>
    <tr><td style="padding:12px 16px; font-size:13px; color:rgba(255,255,255,0.6); border-top:1px solid rgba(255,255,255,0.08);">{{ $t('customer') }}</td><td style="padding:12px 16px; font-size:13px; color:#fff; border-top:1px solid rgba(255,255,255,0.08);">{{ $nombre }} · {{ $email }}</td></tr>
    <tr><td style="padding:12px 16px; font-size:13px; color:rgba(255,255,255,0.6); border-top:1px solid rgba(255,255,255,0.08);">{{ $t('items') }}</td><td style="padding:12px 16px; font-size:13px; color:#fff; border-top:1px solid rgba(255,255,255,0.08);">{{ $items ?: $t('whole_order') }}</td></tr>
    @if ($motivo)
    <tr><td style="padding:12px 16px; font-size:13px; color:rgba(255,255,255,0.6); border-top:1px solid rgba(255,255,255,0.08);">{{ $t('reason') }}</td><td style="padding:12px 16px; font-size:13px; color:#fff; border-top:1px solid rgba(255,255,255,0.08);">{{ $motivo }}</td></tr>
    @endif
  </table>

  <div class="text"><strong style="color:#fff;">{{ $t('next_title') }}</strong></div>
  <div class="text">1. {{ $t('next_1') }}</div>
  <div class="text">2. {{ $t('next_2') }}</div>
  <div class="text">3. {{ $t('next_3') }}</div>

  <a href="{{ $url }}" class="btn">{{ $t('button') }}</a>

  <div class="text" style="font-size:12px;">{{ $t('keep') }}</div>
@endsection
