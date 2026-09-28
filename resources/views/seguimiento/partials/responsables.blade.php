<section aria-labelledby="seguimiento-responsables">
    <div class="tracking-section-head">
        <div><h2 id="seguimiento-responsables">Seguimiento por responsable</h2><p>Carga operativa y alertas de cada responsable.</p></div>
    </div>
    <div class="tracking-table-wrap">
        <table class="tracking-table">
            <thead><tr><th class="text-left">Responsable</th><th class="text-left">Rol</th><th class="tracking-number">Casos</th><th class="tracking-number">Pendientes</th><th class="tracking-number">En riesgo</th><th class="tracking-number">Vencidos</th></tr></thead>
            <tbody>
                @forelse($responsables as $fila)
                    <tr>
                        <td><a class="font-semibold text-[#9f1024] hover:underline" href="{{ route('seguimiento.responsable', array_merge(['responsable' => $fila->usuario->id], request()->except('casos_page'))) }}">{{ $fila->usuario->name }}</a></td>
                        <td><span class="tracking-role-badge">{{ $fila->usuario->role?->nombre }}</span></td>
                        <td class="tracking-number">{{ $fila->casos_asociados }}</td>
                        <td class="tracking-number font-semibold {{ $fila->tareas_pendientes ? 'text-[#a70f27]' : 'text-gray-500' }}">{{ $fila->tareas_pendientes }}</td>
                        <td class="tracking-number">{{ $fila->casos_proximos }}</td>
                        <td class="tracking-number {{ $fila->casos_vencidos ? 'text-red-700 font-bold' : 'text-gray-500' }}">{{ $fila->casos_vencidos }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center text-gray-500">No hay responsables para los filtros aplicados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>