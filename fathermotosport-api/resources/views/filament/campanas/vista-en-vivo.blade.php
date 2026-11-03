{{--
  Vista previa en vivo del formulario de campañas: cómo se ve en la bandeja de entrada
  (remitente, asunto y preheader) y el correo a 600 o 375 px. Se arma con lo escrito, sin
  guardar ni enviar, para un cliente de ejemplo; no cuenta en el cupo.
  Variables: $html (correo renderizado o null si falta contenido), $asunto, $preheader, $ancho.
--}}
<div class="space-y-3">
    <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5" style="max-width: {{ $ancho }}px;">
        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Bandeja de entrada</div>
        <div class="mt-1 flex items-baseline gap-2 text-sm">
            <span class="font-bold text-gray-950 dark:text-white">FatherMotoSport</span>
        </div>
        <div class="truncate text-sm">
            <span class="font-semibold text-gray-950 dark:text-white">{{ $asunto !== '' ? $asunto : '(sin asunto)' }}</span>
            @if ($preheader !== '')
                <span class="text-gray-500"> — {{ $preheader }}</span>
            @endif
        </div>
    </div>

    @if ($html)
        <div class="overflow-x-auto">
            {{-- Alto según el contenido (srcdoc es del mismo origen); 900 px si no se puede medir. --}}
            <iframe title="Vista previa del correo" srcdoc="{{ $html }}"
                    onload="try { this.style.height = (this.contentWindow.document.documentElement.scrollHeight + 4) + 'px'; } catch (e) {}"
                    style="display:block; width:{{ $ancho }}px; max-width:none; height:900px; border:0; border-radius:8px; background:#f2f2f2;"></iframe>
        </div>
        <p class="text-xs text-gray-500">Cliente de ejemplo con enlace de baja ficticio. No se guarda ni se envía.</p>
    @else
        <p class="text-sm text-gray-500">Escribe el asunto, el titular y el texto para ver la vista previa.</p>
    @endif
</div>
