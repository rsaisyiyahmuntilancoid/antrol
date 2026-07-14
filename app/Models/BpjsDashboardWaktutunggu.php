<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BpjsDashboardWaktutunggu extends Model
{
    use HasFactory;

    /**
     * The database connection that should be used by the model.
     *
     * @var string
     */
    protected $connection = 'mysql';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bpjs_dashboard_waktutunggu';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tanggal',
        'waktu',
        'kodepoli',
        'namapoli',
        'jumlah_antrean',
        'waktu_task1',
        'waktu_task2',
        'waktu_task3',
        'waktu_task4',
        'waktu_task5',
        'waktu_task6',
        'avg_waktu_task1',
        'avg_waktu_task2',
        'avg_waktu_task3',
        'avg_waktu_task4',
        'avg_waktu_task5',
        'avg_waktu_task6',
        'insertdate',
    ];
}
