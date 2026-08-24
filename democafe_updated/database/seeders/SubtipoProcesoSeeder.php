<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubtipoProcesoSeeder extends Seeder
{
    public function run(): void
    {
        // Obtener IDs de tipos
        $tipos = DB::table('tipos_proceso')->pluck('id', 'codigo');

        DB::table('subtipos_proceso')->insert([

            // CONTRATOS (CT)
            ['tipo_id' => $tipos['CT'], 'nombre' => 'Compraventa', 'codigo' => 'CP'],
            ['tipo_id' => $tipos['CT'], 'nombre' => 'Suministro', 'codigo' => 'SM'],
            ['tipo_id' => $tipos['CT'], 'nombre' => 'Obra civil', 'codigo' => 'OB'],
            ['tipo_id' => $tipos['CT'], 'nombre' => 'Arrendamiento', 'codigo' => 'AR'],
            ['tipo_id' => $tipos['CT'], 'nombre' => 'Prestación de servicios', 'codigo' => 'PS'],
            ['tipo_id' => $tipos['CT'], 'nombre' => 'Consultoría', 'codigo' => 'CO'],

            // CONVENIOS (CV)
            ['tipo_id' => $tipos['CV'], 'nombre' => 'Suscripción de convenios', 'codigo' => 'SC'],
            ['tipo_id' => $tipos['CV'], 'nombre' => 'Trámite de convenios', 'codigo' => 'TC'],

            // ACUERDOS INTERNACIONALES (AI)
            ['tipo_id' => $tipos['AI'], 'nombre' => 'Acuerdos internacionales', 'codigo' => 'AI1'],

            //  DERECHOS DE PETICIÓN (DP)
            ['tipo_id' => $tipos['DP'], 'nombre' => 'Derechos de petición', 'codigo' => 'DP1'],

            // TUTELAS (TT)
            ['tipo_id' => $tipos['TT'], 'nombre' => 'Acciones de tutela', 'codigo' => 'TT1'],

            // SOLICITUDES GENERALES (SG)
            ['tipo_id' => $tipos['SG'], 'nombre' => 'Solicitudes en general', 'codigo' => 'SG1'],

            // ACTAS CONTRACTUALES (AC)
            ['tipo_id' => $tipos['AC'], 'nombre' => 'Acta de inicio', 'codigo' => 'AI'],
            ['tipo_id' => $tipos['AC'], 'nombre' => 'Suspensión', 'codigo' => 'AS'],
            ['tipo_id' => $tipos['AC'], 'nombre' => 'Reinicio', 'codigo' => 'AR'],
            ['tipo_id' => $tipos['AC'], 'nombre' => 'Liquidación', 'codigo' => 'AL'],

            // PÓLIZAS (PL)
            ['tipo_id' => $tipos['PL'], 'nombre' => 'Trámite de pólizas', 'codigo' => 'TP'],

            // VERIFICACIÓN DE PÓLIZAS (VP)
            ['tipo_id' => $tipos['VP'], 'nombre' => 'Verificación de pólizas', 'codigo' => 'VP1'],
        ]);
    }
}