<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->foreignId('caso_id')
                ->nullable()
                ->after('user_id')
                ->constrained('casos')
                ->nullOnDelete();
            $table->foreignId('mensaje_id')
                ->nullable()
                ->after('caso_id')
                ->constrained('mensajes')
                ->nullOnDelete();
        });

        DB::table('casos')
            ->select(['id', 'radicado'])
            ->orderBy('id')
            ->eachById(function ($caso) {
                DB::table('notificaciones')
                    ->whereNull('caso_id')
                    ->where('mensaje', 'like', '%'.$caso->radicado.'%')
                    ->update(['caso_id' => $caso->id]);
            });
    }

    public function down(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mensaje_id');
            $table->dropConstrainedForeignId('caso_id');
        });
    }
};
