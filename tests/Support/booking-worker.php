<?php

use App\Models\User;
use App\Services\BookingManager;
use App\Services\ConfigurationManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__).'/bootstrap-mysql.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$jobPath = $argv[1];
$gate = $argv[2];
$allowed = realpath(storage_path('framework/testing'));
if (realpath(dirname($jobPath)) !== $allowed || realpath(dirname($gate)) !== $allowed || DB::connection()->getDatabaseName() !== 'dineflow_laravel_test') {
    throw new RuntimeException('Unsafe booking worker target.');
}
$job = json_decode(file_get_contents($jobPath), true, flags: JSON_THROW_ON_ERROR);
$actor = User::findOrFail($job['actor']);
echo "READY\n";
flush();
$deadline = microtime(true) + 12;
while (! file_exists($gate)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Barrier timeout');
    }usleep(10000);
}
try {
    $manager = app(BookingManager::class);
    $record = match ($job['action']) {
        'create' => $manager->create($actor, $job['outlet'], $job['data']),
        'update' => $manager->update($actor, $job['outlet'], $job['id'], $job['data']),
        'cancel' => $manager->cancel($actor, $job['outlet'], $job['id'], $job['data']),
        'deactivate' => app(ConfigurationManager::class)->save($actor, 'tables', $job['id'], ['status' => 'inactive'], $job['outlet']),
    };
    echo json_encode(['ok' => true, 'id' => $record->getKey()]);
} catch (ValidationException $e) {
    echo json_encode(['ok' => false, 'fields' => array_keys($e->errors()), 'status' => $e->status]);
} catch (ModelNotFoundException|HttpException|AuthorizationException $e) {
    echo json_encode(['ok' => false, 'type' => get_class($e)]);
}
