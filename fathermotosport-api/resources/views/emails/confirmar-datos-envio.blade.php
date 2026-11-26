@php
  $t = fn (string $k, array $d = []) => __("confirmar_datos_envio.{$k}", $d, $l);
  $nombre = strtok(trim($a?->full_name ?? ''), ' ') ?: '';
  $n = 0;
  $campo = fn ($v, bool $confirmar = false) => filled($v) ? $v.($confirmar ? ' ('.$t('confirm').')' : '') : '('.$t('missing').')';
@endphp
{!! $t('hello', ['name' => $nombre]) !!}
@if($nota)

{!! $nota !!}
@endif

{!! $t('intro', ['order' => $order->order_number, 'total' => number_format((float) $order->total, 2)]) !!}

{!! $t('ask') !!}

@foreach($items as $item)
@if($item['es_casco'])
{!! ++$n !!}) {!! $t('visor') !!} – {!! $item['name'] !!}:{!! $item['visor'] ? ' '.$t('visor_chosen', ['visor' => $item['visor']]) : '' !!}
@if(count($item['visores']))
{!! $t('visor_options') !!}
@foreach($item['visores'] as $v)
- {!! $v !!}
@endforeach
@if($item['slug'])
{!! $t('visor_photos') !!} {!! $web !!}/product/{!! $item['slug'] !!}
@endif
@endif

@endif
@endforeach
@foreach($items as $item)
@if($item['size'])
{!! ++$n !!}) {!! $t('size') !!} – {!! $item['name'] !!}: {!! $item['size'] !!} ({!! $t('size_note') !!})

@endif
@endforeach
{!! ++$n !!}) {!! $t('address') !!}:
Recipient Name: {!! $campo($a?->full_name) !!}
Recipient Address: {!! $campo(collect([$a?->address_line, $a?->reference])->filter()->implode(', ')) !!}
Recipient City: {!! $campo($a?->city) !!}
Recipient Province/State: {!! $campo($a?->state) !!}
Recipient Postal Code: {!! $campo($a?->postal_code) !!}
Recipient's phone number: {!! $campo($a?->phone) !!}

{!! $t('outro') !!}

{!! $t('bye') !!}
{!! $t('team') !!}
{!! $ventas !!}
{!! $web !!}
