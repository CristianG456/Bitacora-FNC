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
        Schema::create('subtipos_proceso', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tipo_id')
                ->constrained('tipos_proceso')
                ->cascadeOnDelete();

            $table->string('nombre');
            $table->string('codigo', 3); 

            $table->timestamps();

            $table->unique(['tipo_id', 'codigo']); // evita duplicados por tipo
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subtipos_proceso');
    }
};
