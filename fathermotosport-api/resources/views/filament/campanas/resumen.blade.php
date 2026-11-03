{{--
  Antes de enviar una campaña: qué falta, a cuántos clientes llega, cuántos quedan fuera
  (sin evidencia del correo o de baja), cuántos caben hoy en el cupo, qué
  idiomas usan el español, y la vista previa en cada idioma.
  Variables: $resumen (CampanaManual::resumen o null), $errores, $programada,
  $previas (idioma => html), $conEspanol.
--}}
<div x-data="{ idioma: 'es' }" class="space-y-4">
    @if ($errores)
        <div class="rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
            Todavía no se puede enviar:
            <ul class="mt-1 list-disc pl-5">
                @foreach ($errores as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($conEspanol)
        <div class="rounded-lg bg-warning-50 p-3 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
            Sin contenido en {{ collect($conEspanol)->map(fn ($i) => \App\Services\CampanaManual::IDIOMAS[$i])->implode(' ni ') }}:
            esos clientes recibirán el español.
        </div>
    @endif

    @if ($resumen)
        {{-- Rejilla en línea: el CSS del panel no trae las clases grid-cols de Tailwind. --}}
        <div class="text-sm" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:0.75rem">
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <div class="text-2xl font-bold">{{ $resumen['recibirian'] }}</div>
                <div class="text-gray-500">clientes lo recibirían</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <div class="text-2xl font-bold">{{ $resumen['caben_hoy'] }}</div>
                <div class="text-gray-500">caben hoy en el cupo (el resto sigue mañana)</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <div class="text-2xl font-bold">{{ $resumen['sin_evidencia'] }}</div>
                <div class="text-gray-500">fuera por falta de evidencia del correo</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <div class="text-2xl font-bold">{{ $resumen['baja'] }}</div>
                <div class="text-gray-500">fuera por baja</div>
            </div>
        </div>

        <p class="text-xs text-gray-500">
            @if ($programada)
                Sale el {{ $programada }} (hora de Bolivia).
            @else
                Sale al confirmar.
            @endif
            Se envía por tandas cada minuto, respetando el cupo de Resend ({{ config('marketing.limits.per_minute') }} por minuto,
            {{ $resumen['por_dia'] }} por día, compartido con cumpleaños y avisos). Una cuenta puede estar en más de un grupo de excluidos.
        </p>
    @endif

    <div class="flex gap-2">
        @foreach (array_keys($previas) as $codigo)
            <button type="button"
                    x-on:click="idioma = '{{ $codigo }}'"
                    x-bind:class="idioma === '{{ $codigo }}' ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-white/5'"
                    class="rounded-lg px-3 py-1 text-sm font-medium">
                {{ strtoupper($codigo) }}{{ in_array($codigo, $conEspanol, true) ? ' (usa ES)' : '' }}
            </button>
        @endforeach
    </div>

    @foreach ($previas as $codigo => $html)
        <iframe x-show="idioma === '{{ $codigo }}'" title="Vista previa {{ $codigo }}" srcdoc="{{ $html }}"
                style="width:100%;height:620px;border:0;border-radius:8px;background:#f5f5f5"></iframe>
    @endforeach
</div>
