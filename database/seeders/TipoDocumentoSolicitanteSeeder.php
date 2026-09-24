<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoDocumentoSolicitanteSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['nombre' => 'Cédula de ciudadanía', 'codigo' => 'CC', 'aplica_a' => 'natural', 'orden' => 10],
            ['nombre' => 'Cédula de extranjería', 'codigo' => 'CE', 'aplica_a' => 'natural', 'orden' => 20],
            ['nombre' => 'Tarjeta de identidad', 'codigo' => 'TI', 'aplica_a' => 'natural', 'orden' => 30],
            ['nombre' => 'Registro civil', 'codigo' => 'RC', 'aplica_a' => 'natural', 'orden' => 40],
            ['nombre' => 'Pasaporte', 'codigo' => 'PAS', 'aplica_a' => 'natural', 'orden' => 50],
            ['nombre' => 'Permiso por Protección Temporal', 'codigo' => 'PPT', 'aplica_a' => 'natural', 'orden' => 60],
            ['nombre' => 'NIT', 'codigo' => 'NIT', 'aplica_a' => 'juridica', 'orden' => 70],
            ['nombre' => 'Otro', 'codigo' => 'OTRO', 'aplica_a' => 'ambos', 'orden' => 80],
        ];

        foreach ($tipos as $tipo) {
            DB::table('tipos_documento_solicitante')->updateOrInsert(
                ['codigo' => $tipo['codigo']],
                [...$tipo, 'activo' => true, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }
}
