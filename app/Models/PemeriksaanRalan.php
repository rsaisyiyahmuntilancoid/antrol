<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PemeriksaanRalan extends Model
{
    use HasFactory;

    protected $table = 'pemeriksaan_ralan';

    // Composite primary key: no_rawat, tgl_perawatan, jam_rawat
    // Laravel doesn't natively support composite keys, so we'll handle it manually
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'no_rawat',
        'tgl_perawatan',
        'jam_rawat',
        'suhu_tubuh',
        'tensi',
        'nadi',
        'respirasi',
        'tinggi',
        'berat',
        'spo2',
        'gcs',
        'kesadaran',
        'keluhan',
        'pemeriksaan',
        'alergi',
        'lingkar_perut',
        'rtl',
        'penilaian',
        'instruksi',
        'evaluasi',
        'nip',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tgl_perawatan' => 'date',
            'jam_rawat' => 'datetime:H:i:s',
        ];
    }

    /**
     * Get the reg_periksa that owns the PemeriksaanRalan.
     */
    public function regPeriksa()
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    /**
     * Get the petugas that owns the PemeriksaanRalan.
     */
    public function petugas()
    {
        return $this->belongsTo(Petugas::class, 'nip', 'nip');
    }

    /**
     * Get the dokter that owns the PemeriksaanRalan.
     */
    public function dokter()
    {
        return $this->belongsTo(Dokter::class, 'nip', 'kd_dokter');
    }
}
