<section aria-labelledby="resumen-ejecutivo">
    <div class="tracking-section-head">
        <div><h2 id="resumen-ejecutivo">Resumen ejecutivo</h2><p>Una lectura rápida del conjunto filtrado.</p></div>
    </div>
    <div class="tracking-metrics">
        @foreach([
            ['Total de casos', $resumen['total'], '#344054', 'Universo consultado'],
            ['Casos activos', $resumen['activos'], '#2563a6', 'Pendientes o en proceso'],
            ['En riesgo', $resumen['proximos'], '#a16207', 'Preventivos y críticos'],
            ['ANS vencidos', $resumen['vencidos'], '#b42318', 'Vencidos o incumplidos'],
            ['Tareas pendientes', $resumen['tareas_pendientes'], '#a70f27', 'Gestiones por completar']
        ] as [$label, $valor, $accent, $contexto])
            <article class="tracking-metric" style="--metric-accent: {{ $accent }}">
                <p class="tracking-metric-label">{{ $label }}</p>
                <p class="tracking-metric-value">{{ $valor }}</p>
                <p class="tracking-metric-context">{{ $contexto }}</p>
            </article>
        @endforeach
    </div>
</section>