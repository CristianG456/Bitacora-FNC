<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitantes', function (Blueprint $table) {
            $table->string('documento')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('solicitantes')
            ->whereNull('documento')
            ->orderBy('id')
            ->eachById(function ($solicitante) {
                DB::table('solicitantes')
                    ->where('id', $solicitante->id)
                    ->update(['documento' => 'SIN-DOCUMENTO-'.$solicitante->id]);
            });

        Schema::table('solicitantes', function (Blueprint $table) {
            $table->string('documento')->nullable(false)->change();
        });
    }
};
