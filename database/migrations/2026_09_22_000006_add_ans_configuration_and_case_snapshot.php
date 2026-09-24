<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_proceso', function (Blueprint $table) {
            $table->unsignedSmallInteger('ans_dias')->nullable()->after('activo');
            $table->string('ans_tipo_dias', 20)->default('calendario')->after('ans_dias');
        });

        Schema::table('casos', function (Blueprint $table) {
            $table->date('ans_fecha_inicio')->nullable()->after('fecha_solicitud');
            $table->unsignedSmallInteger('ans_dias')->nullable()->after('ans_fecha_inicio');
            $table->string('ans_tipo_dias', 20)->nullable()->after('ans_dias');
            $table->date('ans_fecha_limite')->nullable()->after('ans_tipo_dias');
            $table->string('ans_estado', 20)->nullable()->after('ans_fecha_limite');
            $table->index(['ans_estado', 'ans_fecha_limite'], 'casos_ans_seguimiento_idx');
        });

        Schema::table('bitacoras', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        $configuracionInicial = [
            'CT' => 2,
            'CV' => 2,
            'DP' => 15,
            'AI' => 4,
            'TT' => 4,
            'SG' => 4,
            'AC' => 4,
            'PL' => 4,
            'VP' => 4,
        ];

        foreach ($configuracionInicial as $codigo => $dias) {
            DB::table('tipos_proceso')
                ->where('codigo', $codigo)
                ->update([
                    'ans_dias' => $dias,
                    'ans_tipo_dias' => 'calendario',
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('bitacoras', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('casos', function (Blueprint $table) {
            $table->dropIndex('casos_ans_seguimiento_idx');
            $table->dropColumn([
                'ans_fecha_inicio',
                'ans_dias',
                'ans_tipo_dias',
                'ans_fecha_limite',
                'ans_estado',
            ]);
        });

        Schema::table('tipos_proceso', function (Blueprint $table) {
            $table->dropColumn(['ans_dias', 'ans_tipo_dias']);
        });
    }
};
