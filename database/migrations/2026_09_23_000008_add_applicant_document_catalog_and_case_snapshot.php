<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_documento_solicitante', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo', 20)->unique();
            $table->string('aplica_a', 20);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['activo', 'aplica_a', 'orden'], 'tipos_documento_filtro_idx');
        });

        $ahora = now();
        DB::table('tipos_documento_solicitante')->upsert([
            ['nombre' => 'Cédula de ciudadanía', 'codigo' => 'CC', 'aplica_a' => 'natural', 'activo' => true, 'orden' => 10, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Cédula de extranjería', 'codigo' => 'CE', 'aplica_a' => 'natural', 'activo' => true, 'orden' => 20, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Tarjeta de identidad', 'codigo' => 'TI', 'aplica_a' => 'natural', 'activo' => true, 'orden' => 30, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Registro civil', 'codigo' => 'RC', 'aplica_a' => 'natural', 'activo' => true, 'orden' => 40, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Pasaporte', 'codigo' => 'PAS', 'aplica_a' => 'natural', 'activo' => true, 'orden' => 50, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Permiso por Protección Temporal', 'codigo' => 'PPT', 'aplica_a' => 'natural', 'activo' => true, 'orden' => 60, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'NIT', 'codigo' => 'NIT', 'aplica_a' => 'juridica', 'activo' => true, 'orden' => 70, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Otro', 'codigo' => 'OTRO', 'aplica_a' => 'ambos', 'activo' => true, 'orden' => 80, 'created_at' => $ahora, 'updated_at' => $ahora],
        ], ['codigo'], ['nombre', 'aplica_a', 'activo', 'orden', 'updated_at']);

        Schema::table('solicitantes', function (Blueprint $table) {
            $table->foreignId('tipo_documento_solicitante_id')
                ->nullable()
                ->after('tipo_solicitante')
                ->constrained('tipos_documento_solicitante')
                ->nullOnDelete();
        });

        Schema::table('casos', function (Blueprint $table) {
            $table->string('solicitante_nombre_snapshot')->nullable()->after('solicitante_id');
            $table->string('solicitante_tipo_snapshot', 20)->nullable()->after('solicitante_nombre_snapshot');
            $table->foreignId('solicitante_tipo_documento_id')
                ->nullable()
                ->after('solicitante_tipo_snapshot')
                ->constrained('tipos_documento_solicitante')
                ->nullOnDelete();
            $table->string('solicitante_documento_snapshot')->nullable()->after('solicitante_tipo_documento_id');
        });
    }

    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('solicitante_tipo_documento_id');
            $table->dropColumn([
                'solicitante_nombre_snapshot',
                'solicitante_tipo_snapshot',
                'solicitante_documento_snapshot',
            ]);
        });

        Schema::table('solicitantes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipo_documento_solicitante_id');
        });

        Schema::dropIfExists('tipos_documento_solicitante');
    }
};
