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
        Schema::table('backup_settings', function (Blueprint $table) {
            $table->boolean('r2_enabled')->default(false);
            $table->string('r2_bucket')->nullable();
            $table->string('r2_path')->nullable();
            $table->integer('r2_retention_days')->nullable();
        });

        Schema::table('backup_histories', function (Blueprint $table) {
            $table->string('storage_provider')->default('local')->after('status');
            $table->string('storage_path')->nullable()->after('file_path');
            $table->timestamp('r2_uploaded_at')->nullable()->after('storage_path');
            $table->string('checksum_sha256')->nullable()->after('r2_uploaded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backup_settings', function (Blueprint $table) {
            $table->dropColumn([
                'r2_enabled',
                'r2_bucket',
                'r2_path',
                'r2_retention_days'
            ]);
        });

        Schema::table('backup_histories', function (Blueprint $table) {
            $table->dropColumn([
                'storage_provider',
                'storage_path',
                'r2_uploaded_at',
                'checksum_sha256'
            ]);
        });
    }
};
