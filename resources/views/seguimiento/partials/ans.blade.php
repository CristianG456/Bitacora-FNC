<section aria-labelledby="situacion-ans">
    <div class="tracking-section-head">
        <div><h2 id="situacion-ans">Situación ANS</h2><p>Estados de cumplimiento y alerta en una sola vista.</p></div>
        <p class="hidden sm:block">Selecciona un estado para filtrar</p>
    </div>
    <div class="tracking-ans-grid">
        @foreach([
            ['Vigente','vigente','#26734d','#f4faf6','#cde8d7'],
            ['Preventivo','preventivo','#946200','#fffaf0','#f1dfb8'],
            ['Crítico','critico','#a34b16','#fff7f1','#f0d4c3'],
            ['Vencido','vencido','#a92a22','#fff5f4','#edcfcc'],
            ['Cumplido','cumplido','#286396','#f3f8fc','#d0e0ed'],
            ['Incumplido','incumplido','#9f2440','#fff5f7','#ebced5']
        ] as [$label,$key,$color,$bg,$line])
            <a href="{{ route(request()->routeIs('seguimiento.reportes') ? 'seguimiento.reportes' : 'seguimiento.index', array_merge(request()->except('casos_page'), ['ans_estado' => $key])) }}" class="tracking-ans-card" style="--ans-color: {{ $color }}; --ans-bg: {{ $bg }}; --ans-line: {{ $line }}">
                <span class="tracking-ans-label">{{ $label }}</span>
                <strong class="tracking-ans-value">{{ $resumen['por_ans'][$key] ?? 0 }}</strong>
            </a>
        @endforeach
    </div>
</section>