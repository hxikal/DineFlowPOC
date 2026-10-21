<?php

use App\Models\Booking;
use App\Models\BookingTable;
use App\Models\DiningTable;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\ConfigurationManager;
use App\Services\ReservationLocks;
use App\Services\UserManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__).'/bootstrap-mysql.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$operation = $argv[1];
$actor = (int) $argv[2];
$target = (int) $argv[3];
$gate = $argv[4];
$allowed = realpath(dirname(__DIR__, 2).'/storage/framework/testing');
if (realpath(dirname($gate)) !== $allowed) {
    throw new RuntimeException('Invalid synchronization directory');
}
if (DB::connection()->getDatabaseName() !== 'dineflow_laravel_test') {
    throw new RuntimeException('Invalid worker database');
}
$actor = User::findOrFail($actor);
echo "READY\n";
flush();
$deadline = microtime(true) + 10;
while (! file_exists($gate)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Barrier timeout');
    }
    usleep(10000);
}
try {
    if ($operation === 'admin') {
        app(UserManager::class)->save($actor, $target, ['role' => 'user']);
    } elseif ($operation === 'deactivate') {
        $table = DiningTable::findOrFail($target);
        app(ConfigurationManager::class)->save($actor, 'tables', $target, ['status' => 'inactive'], $table->outlet_id);
    } elseif ($operation === 'reserve') {
        DB::transaction(function () use ($actor, $target) {
            $table = DiningTable::findOrFail($target);
            app(ReservationLocks::class)->acquire($table->outlet_id, [$target]);
            usleep(150000);
            $method = PaymentMethod::firstOrFail();
            $booking = Booking::create(['booking_code' => 'TEST-CONCURRENT', 'table_id' => $target, 'outlet_id' => $table->outlet_id, 'dining_date' => now()->toDateString(), 'customer_name' => 'Synthetic race fixture', 'contact_number' => '010-0000000', 'pax' => 4, 'payment_method_id' => $method->getKey(), 'created_by' => $actor->getKey(), 'booking_status' => 'booked']);
            BookingTable::create(['booking_id' => $booking->getKey(), 'table_id' => $target, 'dining_date' => now()->toDateString(), 'pax' => 4]);
        }, 3);
    } else {
        throw new RuntimeException('Unknown worker operation');
    }
    echo json_encode(['ok' => true]);
} catch (ValidationException|ModelNotFoundException|HttpException $e) {
    echo json_encode(['ok' => false, 'rejected' => get_class($e)]);
}
