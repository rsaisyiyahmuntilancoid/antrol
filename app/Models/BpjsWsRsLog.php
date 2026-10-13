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
        'operation',
        'status',
        'code',
        'status_code',
        'http_status',
        'error_type',
        'bpjs_meta_code',
        'bpjs_meta_message',
        'request',
        'response',
        'message',
        'endpoint',
        'url',
        'method',
        'norm',
        'nokartu',
        'no_sep',
        'no_rujukan',
        'exception',
        'created_at',
        'updated_at',
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
            'updated_at' => 'datetime',
            'code' => 'integer',
            'status_code' => 'integer',
            'http_status' => 'integer',
        ];
    }
}
