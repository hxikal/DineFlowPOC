<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingTable;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\BookingManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BookingConcurrencyTest extends TestCase
{
    private function prepare(): array
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires isolated MySQL test database.');
        }
        $this->assertSame('dineflow_laravel_test', DB::connection()->getDatabaseName());
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $actor = User::factory()->admin()->create()->fresh();
        $other = User::factory()->create()->fresh();
        $outlet = Outlet::factory()->create();
        $tables = DiningTable::factory()->count(3)->create(['outlet_id' => $outlet->getKey()]);
        $payment = PaymentMethod::factory()->create();

        return compact('actor', 'other', 'outlet', 'tables', 'payment');
    }

    private function data(array $f, array $changes = []): array
    {
        return array_replace(['operation_key' => (string) Str::uuid(), 'dining_date' => now()->toDateString(), 'customer_name' => 'Synthetic race P4', 'contact_number' => '010-0000000', 'payment_method_id' => $f['payment']->getKey(), 'tables' => [['table_id' => $f['tables'][0]->getKey(), 'pax' => 3], ['table_id' => $f['tables'][1]->getKey(), 'pax' => 5]]], $changes);
    }

    private function job(array $f, string $action, array $data = [], ?int $id = null, ?User $actor = null): array
    {
        return ['actor' => ($actor ?? $f['actor'])->getKey(), 'outlet' => $f['outlet']->getKey(), 'action' => $action, 'data' => $data, 'id' => $id];
    }

    private function race(array $jobs): array
    {
        $path = storage_path('framework/testing');
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }$prefix = $path.'/p4-'.bin2hex(random_bytes(8));
        $gate = $prefix.'-go';
        $workers = [];
        $files = [];
        try {
            foreach ($jobs as $i => $job) {
                $file = $prefix.'-'.$i.'.json';
                file_put_contents($file, json_encode($job, JSON_THROW_ON_ERROR));
                $files[] = $file;
                $worker = new Process([PHP_BINARY, base_path('tests/Support/booking-worker.php'), $file, $gate], base_path());
                $worker->setTimeout(25);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 15;
            do {
                $ready = count(array_filter($workers, fn ($w) => str_contains($w->getOutput(), 'READY')));
                if ($ready === count($workers)) {
                    break;
                }if (microtime(true) > $deadline) {
                    throw new \RuntimeException('Barrier failed: '.implode('', array_map(fn ($w) => $w->getErrorOutput(), $workers)));
                }usleep(10000);
            } while (true);
            file_put_contents($gate, 'go');
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $lines = explode("\n", trim($worker->getOutput()));
                $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }foreach (array_merge($files, [$gate]) as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function test_two_staff_requiring_same_tables_produce_one_booking_only(): void
    {
        $f = $this->prepare();
        $results = $this->race([$this->job($f, 'create', $this->data($f)), $this->job($f, 'create', $this->data($f), actor: $f['other'])]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])));
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_tables', 2);
        $this->assertDatabaseCount('operation_keys', 1);
        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseHas('booking_sequences', ['last_number' => 1]);
    }

    public function test_identical_submit_token_concurrently_returns_the_same_booking(): void
    {
        $f = $this->prepare();
        $data = $this->data($f);
        $results = $this->race([$this->job($f, 'create', $data), $this->job($f, 'create', $data)]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('operation_keys', 1);
        $this->assertDatabaseCount('activity_logs', 1);
    }

    public function test_code_counter_is_unique_across_two_outlets_in_the_same_year(): void
    {
        $f = $this->prepare();
        $other = $f;
        $other['outlet'] = Outlet::factory()->create();
        $other['tables'] = DiningTable::factory()->count(2)->create(['outlet_id' => $other['outlet']->getKey()]);
        $results = $this->race([$this->job($f, 'create', $this->data($f)), $this->job($other, 'create', $this->data($other))]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame(['DF-'.now()->year.'-0001', 'DF-'.now()->year.'-0002'], Booking::orderBy('booking_code')->pluck('booking_code')->all());
        $this->assertDatabaseHas('booking_sequences', ['last_number' => 2]);
    }

    public function test_concurrent_edits_with_same_version_do_not_lose_updates(): void
    {
        $f = $this->prepare();
        $booking = app(BookingManager::class)->create($f['actor'], $f['outlet']->getKey(), $this->data($f));
        $a = $this->data($f, ['version' => 1, 'customer_name' => 'Edit A']);
        $b = $this->data($f, ['version' => 1, 'customer_name' => 'Edit B']);
        $results = $this->race([$this->job($f, 'update', $a, $booking->getKey()), $this->job($f, 'update', $b, $booking->getKey(), $f['other'])]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])));
        $this->assertSame(2, $booking->fresh()->version);
        $this->assertSame(1, ActivityLog::where('action', 'UPDATE_BOOKING')->count());
        $this->assertDatabaseCount('booking_tables', 2);
    }

    public function test_duplicate_cancellation_is_idempotent_under_concurrency(): void
    {
        $f = $this->prepare();
        $booking = app(BookingManager::class)->create($f['actor'], $f['outlet']->getKey(), $this->data($f));
        $data = ['operation_key' => (string) Str::uuid(), 'version' => 1, 'cancellation_reason' => 'Synthetic cancellation'];
        $results = $this->race([$this->job($f, 'cancel', $data, $booking->getKey()), $this->job($f, 'cancel', $data, $booking->getKey())]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame('cancelled', $booking->fresh()->booking_status);
        $this->assertSame(2, $booking->fresh()->version);
        $this->assertSame(0, BookingTable::whereNull('released_at')->count());
        $this->assertSame(1, ActivityLog::where('action', 'CANCEL_BOOKING')->count());
    }

    public function test_real_booking_creation_and_config_deactivation_cannot_both_succeed(): void
    {
        $f = $this->prepare();
        $results = $this->race([$this->job($f, 'create', $this->data($f), actor: $f['other']), $this->job($f, 'deactivate', id: $f['tables'][0]->getKey())]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])));
        $this->assertFalse($f['tables'][0]->fresh()->status === 'inactive' && Booking::where('booking_status', 'booked')->exists());
    }

    public function test_edit_and_cancel_competing_on_the_same_version_have_one_winner(): void
    {
        $f = $this->prepare();
        $booking = app(BookingManager::class)->create($f['actor'], $f['outlet']->getKey(), $this->data($f));
        $cancel = ['operation_key' => (string) Str::uuid(), 'version' => 1, 'cancellation_reason' => 'Synthetic cancellation'];
        $results = $this->race([$this->job($f, 'update', $this->data($f, ['version' => 1, 'customer_name' => 'Edited']), $booking->getKey()), $this->job($f, 'cancel', $cancel, $booking->getKey())]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])));
        $this->assertSame(2, $booking->fresh()->version);
        $this->assertDatabaseCount('activity_logs', 2);
        $this->assertDatabaseCount('operation_keys', 2);
    }
}
