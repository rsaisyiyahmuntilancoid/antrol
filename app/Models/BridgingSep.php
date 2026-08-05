<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BridgingSep extends Model
{
    use HasFactory;

    protected $table = 'bridging_sep';

    protected $primaryKey = 'no_sep';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'no_sep',
        'no_rawat',
        'tglsep',
        'tglrujukan',
        'no_rujukan',
        'kdppkrujukan',
        'nmppkrujukan',
        'kdppkpelayanan',
        'nmppkpelayanan',
        'jnspelayanan',
        'catatan',
        'diagawal',
        'nmdiagnosaawal',
        'kdpolitujuan',
        'nmpolitujuan',
        'klsrawat',
        'klsnaik',
        'pembiayaan',
        'pjnaikkelas',
        'lakalantas',
        'user',
        'nomr',
        'nama_pasien',
        'tanggal_lahir',
        'peserta',
        'jkel',
        'no_kartu',
        'tglpulang',
        'asal_rujukan',
        'eksekutif',
        'cob',
        'notelep',
        'katarak',
        'tglkkl',
        'keterangankkl',
        'suplesi',
        'no_sep_suplesi',
        'kdprop',
        'nmprop',
        'kdkab',
        'nmkab',
        'kdkec',
        'nmkec',
        'noskdp',
        'kddpjp',
        'nmdpdjp',
        'tujuankunjungan',
        'flagprosedur',
        'penunjang',
        'asesmenpelayanan',
        'kddpjplayanan',
        'nmdpjplayanan',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tglsep' => 'date',
            'tglrujukan' => 'date',
            'tanggal_lahir' => 'date',
            'tglpulang' => 'datetime',
            'tglkkl' => 'date',
        ];
    }

    /**
     * Get the reg_periksa that owns the BridgingSep.
     */
    public function regPeriksa()
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }
}
