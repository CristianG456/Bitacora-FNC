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
        Schema::create('bitacoras', function (Blueprint $table) {
            $table->id();

            $table->foreignId('caso_id')->nullable()->constrained('casos')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');

            $table->string('modulo');
            $table->string('accion');

            $table->unsignedBigInteger('entidad_id')->nullable();

            $table->foreignId('usuario_afectado')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->text('descripcion');

            $table->json('metadata')->nullable();

            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('caso_id');
            $table->index('user_id');
            $table->index('created_at');
            $table->index('modulo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bitacoras');
    }
};
