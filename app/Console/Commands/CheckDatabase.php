<?php

namespace App\Console\Commands;

use App\Support\DatabaseSafety;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDatabase extends Command
{
    protected $signature = 'dineflow:check-database';

    protected $description = 'Verify the isolated database and show non-sensitive readiness counts';

    public function handle(DatabaseSafety $safety): int
    {
        $connection = DB::connection();
        $safety->assertSafe($connection->getConfig(), app()->environment());
        $this->info('Isolated target: '.$connection->getDatabaseName());
        $this->table(['Users', 'Outlets', 'Audit records'], [[DB::table('users')->count(), DB::table('outlets')->count(), DB::table('activity_logs')->count()]]);

        return self::SUCCESS;
    }
}
