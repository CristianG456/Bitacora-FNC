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
        Schema::create('backup_histories', function (Blueprint $table) {
            $table->id();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->default(0)->comment('Size in bytes');
            $table->enum('backup_type', ['manual', 'automatico'])->default('automatico');
            $table->enum('status', ['exitoso', 'fallido'])->default('exitoso');
            $table->text('sent_to')->nullable();
            $table->float('execution_time')->default(0)->comment('Time in seconds');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_histories');
    }
};
