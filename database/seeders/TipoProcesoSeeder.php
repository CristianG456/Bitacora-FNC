<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoProcesoSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tipos_proceso')->insert([
            ['nombre' => 'Contratos', 'codigo' => 'CT'],
            ['nombre' => 'Convenios', 'codigo' => 'CV'],
            ['nombre' => 'Acuerdos internacionales', 'codigo' => 'AI'],
            ['nombre' => 'Derechos de petición', 'codigo' => 'DP'],
            ['nombre' => 'Tutelas', 'codigo' => 'TT'],
            ['nombre' => 'Solicitudes generales', 'codigo' => 'SG'],
            ['nombre' => 'Actas contractuales', 'codigo' => 'AC'],
            ['nombre' => 'Pólizas', 'codigo' => 'PL'],
            ['nombre' => 'Verificación de pólizas', 'codigo' => 'VP'],
        ]);

        foreach ([
            'CT' => 2,
            'CV' => 2,
            'DP' => 15,
            'AI' => 4,
            'TT' => 4,
            'SG' => 4,
            'AC' => 4,
            'PL' => 4,
            'VP' => 4,
        ] as $codigo => $dias) {
            DB::table('tipos_proceso')
                ->where('codigo', $codigo)
                ->update([
                    'ans_dias' => $dias,
                    'ans_tipo_dias' => 'calendario',
                ]);
        }
    }
}
