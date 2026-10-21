<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class ActivityLog extends Model
{
    use HasFactory;

    protected $primaryKey = 'activity_id';

    const UPDATED_AT = null;

    protected $guarded = ['activity_id'];

    protected function casts(): array
    {
        return ['before_values' => 'array', 'after_values' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit logs are append-only.'));
    }
}
