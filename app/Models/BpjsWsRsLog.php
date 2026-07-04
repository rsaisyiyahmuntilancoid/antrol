<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BpjsWsRsLog extends Model
{
    use HasFactory;

    protected $table = 'bpjs_ws_rs_log';

    protected $connection = 'log';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'request_id',
        'status',
        'code',
        'request',
        'message',
        'url',
        'method',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'code' => 'integer',
        ];
    }
}
