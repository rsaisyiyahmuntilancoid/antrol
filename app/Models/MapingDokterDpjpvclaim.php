<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MapingDokterDpjpvclaim extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'maping_dokter_dpjpvclaim';

    // Primary key is kd_dokter (varchar)
    protected $primaryKey = 'kd_dokter';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'kd_dokter',
        'kd_dokter_bpjs',
        'nm_dokter_bpjs',
    ];

    /**
     * Relation to Dokter
     */
    public function dokter()
    {
        return $this->belongsTo(Dokter::class, 'kd_dokter', 'kd_dokter');
    }
}
