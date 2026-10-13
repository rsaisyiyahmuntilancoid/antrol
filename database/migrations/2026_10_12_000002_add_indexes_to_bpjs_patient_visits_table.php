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
        $schema = Schema::connection('log');

        if (!$schema->hasTable('bpjs_patient_visits')) {
            return;
        }

        try {
            $schema->table('bpjs_patient_visits', function (Blueprint $table) {
                $table->index(['tanggalperiksa', 'kodepoli'], 'idx_visits_tanggal_kodepoli');
                $table->index('status', 'idx_visits_status');
            });
        } catch (\Throwable $e) {
            // Indexes might already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('log');

        if (!$schema->hasTable('bpjs_patient_visits')) {
            return;
        }

        try {
            $schema->table('bpjs_patient_visits', function (Blueprint $table) {
                $table->dropIndex('idx_visits_tanggal_kodepoli');
                $table->dropIndex('idx_visits_status');
            });
        } catch (\Throwable $e) {
            // Ignore if index doesn't exist
        }
    }
};
