{{--
  Antes de avisar: estado, a cuántos clientes llega, cuántos quedan fuera por falta de
  evidencia de su correo y la vista previa del correo en cada idioma.
  Variables: $motivo (por qué no se puede, o null), $existente (aviso anterior o null),
  $destinatarios, $sinEvidencia, $previas (idioma => html).
--}}
<div x-data="{ idioma: 'es' }" class="space-y-4">
    @if ($motivo)
        <div class="rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
            No se puede enviar: {{ $motivo }}
        </div>
    @elseif ($existente)
        <div class="rounded-lg bg-warning-50 p-3 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
            Este descuento ya se avisó el {{ $existente->created_at->timezone(config('marketing.timezone'))->format('d/m/Y H:i') }}
            ({{ $existente->status }}: {{ $existente->sent_count }} enviados de {{ $existente->total_count }}).
            Para mandarlo otra vez usa "Reenviar".
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3 text-sm">
        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
            <div class="text-2xl font-bold">{{ $destinatarios }}</div>
            <div class="text-gray-500">clientes lo recibirían (correo comprobado, sin baja)</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
            <div class="text-2xl font-bold">{{ $sinEvidencia }}</div>
            <div class="text-gray-500">quedan fuera por falta de evidencia de su correo</div>
        </div>
    </div>

    <p class="text-xs text-gray-500">
        Se envía por tandas cada minuto, respetando el cupo de Resend ({{ config('marketing.limits.per_minute') }} por minuto,
        {{ config('marketing.limits.per_day') }} por día). Lo que no quepa hoy sigue mañana.
    </p>

    <div class="flex gap-2">
        @foreach (array_keys($previas) as $codigo)
            <button type="button"
                    x-on:click="idioma = '{{ $codigo }}'"
                    x-bind:class="idioma === '{{ $codigo }}' ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-white/5'"
                    class="rounded-lg px-3 py-1 text-sm font-medium">
                {{ strtoupper($codigo) }}
            </button>
        @endforeach
    </div>

    @foreach ($previas as $codigo => $html)
        <iframe x-show="idioma === '{{ $codigo }}'" title="Vista previa {{ $codigo }}" srcdoc="{{ $html }}"
                style="width:100%;height:620px;border:0;border-radius:8px;background:#f5f5f5"></iframe>
    @endforeach
</div>
