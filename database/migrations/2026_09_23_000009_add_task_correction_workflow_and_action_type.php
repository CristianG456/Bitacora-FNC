<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->string('tipo_accion', 20)->default('normal')->after('descripcion');
            $table->index('tipo_accion');
        });

        Schema::create('solicitudes_correccion_tarea', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();
            $table->foreignId('solicitante_user_id')->constrained('users');
            $table->text('motivo');
            $table->string('estado', 20)->default('pendiente');
            $table->boolean('activa')->nullable()->default(true);
            $table->foreignId('revisada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo_rechazo')->nullable();
            $table->timestamp('aprobada_en')->nullable();
            $table->timestamp('rechazada_en')->nullable();
            $table->timestamp('utilizada_en')->nullable();
            $table->timestamps();

            $table->unique(['tarea_id', 'solicitante_user_id', 'activa'], 'solicitud_correccion_activa_unique');
            $table->index(['estado', 'created_at']);
        });

        Schema::create('tarea_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('datos_anteriores');
            $table->text('datos_nuevos');
            $table->text('campos_modificados');
            $table->foreignId('corregida_por')->constrained('users');
            $table->foreignId('solicitud_correccion_id')
                ->unique()
                ->constrained('solicitudes_correccion_tarea')
                ->cascadeOnDelete();
            $table->text('motivo');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tarea_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarea_versiones');
        Schema::dropIfExists('solicitudes_correccion_tarea');

        Schema::table('tareas', function (Blueprint $table) {
            $table->dropIndex(['tipo_accion']);
            $table->dropColumn('tipo_accion');
        });
    }
};
