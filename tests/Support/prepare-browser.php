<?php

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'dineflow_laravel_test') {
    throw new RuntimeException('Browser fixtures require the isolated test database.');
}
if (User::exists()) {
    throw new RuntimeException('Refusing to overwrite existing test accounts.');
}
$adminPassword = bin2hex(random_bytes(16));
$staffPassword = bin2hex(random_bytes(16));
$admin = User::factory()->admin()->create(['username' => 'qa.admin', 'name' => 'Admin Ujian P2', 'password' => Hash::make($adminPassword)]);
$staff = User::factory()->create(['username' => 'qa.staff', 'name' => 'Staf Ujian P2', 'password' => Hash::make($staffPassword)]);
Outlet::factory()->create(['outlet_code' => 'QA', 'outlet_name' => 'Outlet Ujian P2']);
$path = storage_path('framework/testing');
if (! is_dir($path)) {
    mkdir($path, 0777, true);
}
file_put_contents($path.'/browser-credentials.json', json_encode(['admin' => ['username' => 'qa.admin', 'password' => $adminPassword], 'staff' => ['username' => 'qa.staff', 'password' => $staffPassword]]));
echo "Synthetic browser fixtures prepared in test database.\n";
