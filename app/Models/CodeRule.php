<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodeRule extends Model
{
    protected $fillable = [
        'admin_id',
        'code',
        'name',
        'description',
        'rule_type',
        'rule_config',
        'allow_bracket',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'rule_config' => 'array',
        'allow_bracket' => 'boolean',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeCustom($query)
    {
        return $query->where('is_system', false);
    }
}
