<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ForeignKeyDefinition;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('log')->create('bpjs_dashboard_waktutunggu', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('waktu'); // 'rs' or 'server'
            $table->string('kodepoli');
            $table->string('namapoli')->nullable();
            $table->integer('jumlah_antrean')->default(0);
            
            // Raw total task times (usually sent by BPJS but sometimes null)
            $table->integer('waktu_task1')->default(0)->nullable();
            $table->integer('waktu_task2')->default(0)->nullable();
            $table->integer('waktu_task3')->default(0)->nullable();
            $table->integer('waktu_task4')->default(0)->nullable();
            $table->integer('waktu_task5')->default(0)->nullable();
            $table->integer('waktu_task6')->default(0)->nullable();
            
            // Average task times (the main fields used in comparison calculations)
            $table->integer('avg_waktu_task1')->default(0)->nullable();
            $table->integer('avg_waktu_task2')->default(0)->nullable();
            $table->integer('avg_waktu_task3')->default(0)->nullable();
            $table->integer('avg_waktu_task4')->default(0)->nullable();
            $table->integer('avg_waktu_task5')->default(0)->nullable();
            $table->integer('avg_waktu_task6')->default(0)->nullable();
            
            $table->bigInteger('insertdate')->nullable();
            $table->timestamps();

            // Composite unique key to prevent duplicate poliklinik data per date & waktu type
            $table->unique(['tanggal', 'waktu', 'kodepoli'], 'uq_bpjs_dash_tgl_wkt_poli');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('log')->dropIfExists('bpjs_dashboard_waktutunggu');
    }
};
