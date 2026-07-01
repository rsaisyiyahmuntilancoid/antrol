<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegPeriksa extends Model
{
    use HasFactory;

    protected $table = 'reg_periksa';

    protected $primaryKey = 'no_rawat';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'no_reg',
        'no_rawat',
        'tgl_registrasi',
        'jam_reg',
        'kd_dokter',
        'no_rkm_medis',
        'kd_poli',
        'p_jawab',
        'almt_pj',
        'hubunganpj',
        'biaya_reg',
        'stts',
        'stts_daftar',
        'status_lanjut',
        'kd_pj',
        'umurdaftar',
        'sttsumur',
        'status_bayar',
        'status_poli',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tgl_registrasi' => 'date',
            'jam_reg' => 'datetime:H:i:s',
            'biaya_reg' => 'decimal:2',
        ];
    }

    /**
     * Get the pasien that owns the RegPeriksa.
     */
    public function pasien()
    {
        return $this->belongsTo(Pasien::class, 'no_rkm_medis', 'no_rkm_medis');
    }

    /**
     * Get the poliklinik that owns the RegPeriksa.
     */
    public function poliklinik()
    {
        return $this->belongsTo(Poliklinik::class, 'kd_poli', 'kd_poli');
    }

    /**
     * Get the dokter that owns the RegPeriksa.
     */
    public function dokter()
    {
        return $this->belongsTo(Dokter::class, 'kd_dokter', 'kd_dokter');
    }

    /**
     * Get the penjab that owns the RegPeriksa.
     */
    public function penjab()
    {
        return $this->belongsTo(Penjab::class, 'kd_pj', 'kd_pj');
    }

    /**
     * Get the bridgingSep for the RegPeriksa.
     */
    public function bridgingSep()
    {
        return $this->hasOne(BridgingSep::class, 'no_rawat', 'no_rawat');
    }

    /**
     * Get the referensiMobilejknBpjs for the RegPeriksa.
     */
    public function referensiMobilejknBpjs()
    {
        return $this->hasOne(ReferensiMobilejknBpjs::class, 'no_rawat', 'no_rawat');
    }

    /**
     * Get the referensiMobilejknBpjsTaskid for the RegPeriksa.
     */
    public function referensiMobilejknBpjsTaskid()
    {
        return $this->hasMany(ReferensiMobilejknBpjsTaskid::class, 'no_rawat', 'no_rawat');
    }

    /**
     * Get the pemeriksaanRalan for the RegPeriksa.
     */
    public function pemeriksaanRalan()
    {
        return $this->hasMany(PemeriksaanRalan::class, 'no_rawat', 'no_rawat');
    }

    /**
     * Get the resepObat for the RegPeriksa.
     */
    public function resepObat()
    {
        return $this->hasMany(ResepObat::class, 'no_rawat', 'no_rawat');
    }
}
