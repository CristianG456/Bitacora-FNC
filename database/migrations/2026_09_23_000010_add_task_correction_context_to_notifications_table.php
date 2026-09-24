<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->foreignId('tarea_id')->nullable()->after('caso_id')->constrained('tareas')->nullOnDelete();
            $table->foreignId('solicitud_correccion_id')->nullable()->after('tarea_id')->constrained('solicitudes_correccion_tarea')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('solicitud_correccion_id');
            $table->dropConstrainedForeignId('tarea_id');
        });
    }
};
