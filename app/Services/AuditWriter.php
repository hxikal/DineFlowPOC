<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Arr;

class AuditWriter
{
    public function record(User $actor, string $action, string $type, ?int $id, array $before = [], array $after = []): void
    {
        $allowed = ['user_id', 'name', 'username', 'role', 'department', 'status', 'preferred_locale', 'outlet_id', 'outlet_code', 'outlet_name', 'section_id', 'section_code', 'section_name', 'table_id', 'table_number', 'table_code', 'sort_order', 'payment_method_id', 'method_name'];
        ActivityLog::create(['user_id' => $actor->getKey(), 'action' => $action, 'reference_type' => $type, 'reference_id' => $id, 'description' => $action, 'before_values' => Arr::only($before, $allowed), 'after_values' => Arr::only($after, $allowed), 'ip_address' => app()->runningInConsole() ? null : request()->ip(), 'user_agent' => app()->runningInConsole() ? null : mb_substr((string) request()->userAgent(), 0, 500)]);
    }
}
