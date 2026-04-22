<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('casos', function (Blueprint $table) {
            $table->id();

            $table->string('radicado')->unique();

            $table->foreignId('tipo_id')->constrained('tipos_proceso');
            $table->foreignId('subtipo_id')->constrained('subtipos_proceso');

            $table->text('descripcion')->nullable();
            $table->text('observacion_inicial')->nullable();
            $table->text('link_drive')->nullable();

            $table->foreignId('solicitante_id')->constrained('solicitantes');

            $table->enum('estado', ['Pendiente','En proceso','Completado','Finalizado'])
                ->default('Pendiente');

            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();

            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('casos');
    }
};
