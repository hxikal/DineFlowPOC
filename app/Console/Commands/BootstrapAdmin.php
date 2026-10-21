<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class BootstrapAdmin extends Command
{
    protected $signature = 'dineflow:bootstrap-admin {--username=} {--name=} {--department=Hostess}';

    protected $description = 'Create the first admin using a locally entered secret; refuses an initialized user database';

    public function handle(AuditWriter $audit): int
    {
        if ($this->option('no-interaction')) {
            $this->error('Interactive password entry is required.');

            return self::FAILURE;
        }
        $data = ['username' => $this->option('username') ?: $this->ask('Username'), 'name' => $this->option('name') ?: $this->ask('Name'), 'department' => $this->option('department'), 'password' => $this->secret('Password (minimum 12 characters)'), 'password_confirmation' => $this->secret('Confirm password')];
        $validator = Validator::make($data, ['username' => 'required|string|max:100', 'name' => 'required|string|max:150', 'department' => 'required|in:Hostess,F&B,Marcom', 'password' => 'required|string|min:12|max:255|confirmed']);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        return DB::transaction(function () use ($data, $audit) {
            DB::table('system_locks')->where('name', 'administrators')->lockForUpdate()->firstOrFail();
            if (User::exists()) {
                $this->error('Users already exist. Sign in with an existing admin; bootstrap is disabled.');

                return self::FAILURE;
            }
            $user = User::create(['username' => $data['username'], 'name' => $data['name'], 'department' => $data['department'], 'password' => Hash::make($data['password']), 'role' => 'admin', 'status' => 'active']);
            $audit->record($user, 'BOOTSTRAP_ADMIN', 'user', $user->getKey(), [], $user->toArray());
            $this->info('Admin created. Sign in at /login.');

            return self::SUCCESS;
        });
    }
}
