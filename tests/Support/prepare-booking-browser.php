<?php

use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\OutletSection;
use App\Models\PaymentMethod;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'dineflow_laravel_test') {
    throw new RuntimeException('Requires isolated test database.');
}
if (DiningTable::exists()) {
    throw new RuntimeException('Refusing to overwrite existing test tables.');
}
$outlet = Outlet::where('outlet_code', 'QA')->firstOrFail();
$section = OutletSection::factory()->create(['outlet_id' => $outlet->getKey(), 'section_code' => 'HALL', 'section_name' => 'Ruang Ujian']);
for ($i = 1; $i <= 8; $i++) {
    DiningTable::factory()->create(['outlet_id' => $outlet->getKey(), 'section_id' => $section->getKey(), 'table_code' => 'A'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'table_number' => $i, 'sort_order' => $i]);
}
PaymentMethod::factory()->create(['method_name' => 'Tunai Ujian']);
echo "Synthetic P4 browser configuration prepared in test database.\n";
