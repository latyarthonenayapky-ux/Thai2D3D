<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoundSchedule extends Model
{
    protected $fillable = [
        'admin_id',
        'round_no',
        'start_time',
        'close_time',
        'number_limit',
        'number_limit_3d',
    ];

    protected $casts = [
        'admin_id' => 'integer',
        'round_no' => 'integer',
        'number_limit' => 'integer',
        'number_limit_3d' => 'integer',
    ];
}
