<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserManager
{
    public function __construct(private AuditWriter $audit) {}

    public function save(User $actor, ?int $id, array $data): User
    {
        return DB::transaction(function () use ($actor, $id, $data) {
            DB::table('system_locks')->where('name', 'administrators')->lockForUpdate()->firstOrFail();
            $currentActor = User::findOrFail($actor->getKey());
            abort_unless($currentActor->isAdmin(), 403);
            $user = $id ? User::whereKey($id)->lockForUpdate()->firstOrFail() : new User;
            $before = $user->toArray();
            if ($id === $actor->getKey() && ! empty($data['password'])) {
                throw ValidationException::withMessages(['password' => __('admin.password_reset_self')]);
            }
            if ($id && $user->isAdmin() && (($data['role'] ?? $user->role) !== 'admin' || ($data['status'] ?? $user->status) !== 'active') && User::where('role', 'admin')->where('status', 'active')->count() <= 1) {
                throw ValidationException::withMessages(['status' => __('admin.last_admin')]);
            }
            if (! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
                $user->auth_version = ($user->auth_version ?? 0) + 1;
            } else {
                unset($data['password']);
                if ($id && (($data['role'] ?? $user->role) !== $user->role || ($data['status'] ?? $user->status) !== $user->status)) {
                    $user->auth_version++;
                }
            }
            $user->fill($data);
            $user->save();
            $this->audit->record($currentActor, $id ? (isset($data['password']) ? 'RESET_PASSWORD' : 'UPDATE_USER') : 'CREATE_USER', 'user', $user->getKey(), $before, $user->toArray());

            return $user;
        }, 3);
    }

    public function changePassword(User $actor, string $current, string $password): User
    {
        return DB::transaction(function () use ($actor, $current, $password) {
            $user = User::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($user->status === 'active', 403);
            if (! Hash::check($current, $user->password)) {
                throw ValidationException::withMessages(['current_password' => __('admin.current_password_invalid')]);
            }
            $user->password = Hash::make($password);
            $user->auth_version++;
            $user->save();
            $this->audit->record($user, 'CHANGE_PASSWORD', 'user', $user->getKey());

            return $user;
        });
    }
}
