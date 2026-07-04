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
        Schema::connection('log')->create('bpjs_patient_visits', function (Blueprint $table) {
            $table->id();
            $table->string('kodebooking')->unique();
            $table->string('no_rawat')->nullable();
            $table->date('tanggalperiksa');
            $table->string('nomorkartu')->nullable();
            $table->string('nik')->nullable();
            $table->string('nohp')->nullable();
            $table->string('norm')->nullable();
            $table->string('kodepoli')->nullable();
            $table->string('namapoli')->nullable();
            $table->string('kodedokter')->nullable();
            $table->string('namadokter')->nullable();
            $table->string('jampraktek')->nullable();
            $table->integer('jeniskunjungan')->nullable();
            $table->string('nomorreferensi')->nullable();
            $table->string('nomorantrean')->nullable();
            $table->integer('angkaantrean')->nullable();
            $table->bigInteger('estimasidilayani')->nullable();
            $table->integer('sisakuotajkn')->nullable();
            $table->integer('kuotajkn')->nullable();
            $table->integer('sisakuotanonjkn')->nullable();
            $table->integer('kuotanonjkn')->nullable();
            $table->string('status')->nullable(); // Check-in/Batal/etc
            $table->timestamp('validasi')->nullable();
            $table->json('task_data')->nullable(); // Store tasks 1-7 as JSON
            $table->timestamp('last_sync')->nullable();
            $table->timestamps();
            
            $table->index('tanggalperiksa');
            $table->index('no_rawat');
            $table->index('kodepoli');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('log')->dropIfExists('bpjs_patient_visits');
    }
};
