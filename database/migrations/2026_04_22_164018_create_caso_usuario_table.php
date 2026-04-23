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
        Schema::create('caso_usuario', function (Blueprint $table) {
            $table->id();

            $table->foreignId('caso_id')->constrained('casos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');

            $table->enum('estado', ['Pendiente','En proceso','Finalizado'])
                ->default('Pendiente');

            $table->timestamp('fecha_asignacion')->nullable();
            $table->timestamp('fecha_finalizacion')->nullable();

            $table->boolean('activo')->default(true);
            $table->string('motivo_salida')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caso_usuario');
    }
};
