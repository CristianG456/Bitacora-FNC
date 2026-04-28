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
        Schema::table('tipos_proceso', function (Blueprint $table) {
            $table->string('descripcion')->nullable()->after('codigo');
            $table->boolean('activo')->default(true)->after('descripcion');
        });

        Schema::table('subtipos_proceso', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('codigo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tipos_proceso', function (Blueprint $table) {
            $table->dropColumn(['descripcion', 'activo']);
        });

        Schema::table('subtipos_proceso', function (Blueprint $table) {
            $table->dropColumn(['activo']);
        });
    }
};
