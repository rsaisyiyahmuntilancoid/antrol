<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BpjsPatientVisit extends Model
{
    use HasFactory;

    protected $connection = 'log';
    protected $table = 'bpjs_patient_visits';
    protected $fillable = [
        'kodebooking',
        'no_rawat',
        'tanggalperiksa',
        'nomorkartu',
        'nik',
        'nohp',
        'norm',
        'kodepoli',
        'namapoli',
        'kodedokter',
        'namadokter',
        'jampraktek',
        'jeniskunjungan',
        'nomorreferensi',
        'nomorantrean',
        'angkaantrean',
        'estimasidilayani',
        'sisakuotajkn',
        'kuotajkn',
        'sisakuotanonjkn',
        'kuotanonjkn',
        'status',
        'validasi',
        'task_data',
        'last_sync',
    ];
    
    protected $casts = [
        'tanggalperiksa' => 'date',
        'validasi' => 'datetime',
        'task_data' => 'array',
        'last_sync' => 'datetime',
    ];
    
    public function regPeriksa()
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }
}
