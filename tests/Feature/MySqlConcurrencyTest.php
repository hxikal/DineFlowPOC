<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingTable;
use App\Models\DiningTable;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlConcurrencyTest extends TestCase
{
    private function prepare(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires isolated MySQL test database.');
        }
        $this->assertSame('dineflow_laravel_test', DB::connection()->getDatabaseName());
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
    }

    private function race(array $jobs): array
    {
        $path = storage_path('framework/testing');
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $gate = $path.'/p2-'.bin2hex(random_bytes(8));
        $workers = [];
        try {
            foreach ($jobs as [$operation,$actor,$target]) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/concurrency-worker.php'), $operation, (string) $actor, (string) $target, $gate], base_path());
                $worker->setTimeout(20);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 12;
            do {
                $ready = count(array_filter($workers, fn ($worker) => str_contains($worker->getOutput(), 'READY')));
                if ($ready === count($workers)) {
                    break;
                }
                if (microtime(true) > $deadline) {
                    throw new \RuntimeException('Workers did not reach barrier: '.implode('', array_map(fn ($w) => $w->getErrorOutput(), $workers)));
                }
                usleep(10000);
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
            }
            if (file_exists($gate)) {
                unlink($gate);
            }
        }
    }

    public function test_two_admins_cannot_simultaneously_demote_themselves_and_leave_zero_admins(): void
    {
        $this->prepare();
        $first = User::factory()->admin()->create();
        $second = User::factory()->admin()->create();
        $results = $this->race([['admin', $first->getKey(), $first->getKey()], ['admin', $second->getKey(), $second->getKey()]]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])));
        $this->assertSame(1, User::where('role', 'admin')->where('status', 'active')->count());
        $this->assertDatabaseCount('activity_logs', 1);
    }

    public function test_booking_lock_contract_and_deactivation_cannot_both_succeed(): void
    {
        $this->prepare();
        $admin = User::factory()->admin()->create();
        $table = DiningTable::factory()->create();
        PaymentMethod::factory()->create();
        $results = $this->race([['reserve', $admin->getKey(), $table->getKey()], ['deactivate', $admin->getKey(), $table->getKey()]]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])));
        $this->assertFalse($table->fresh()->status === 'inactive' && Booking::where('booking_status', 'booked')->exists());
    }

    public function test_generated_active_assignment_unique_and_history_restrict_on_mysql(): void
    {
        $this->prepare();
        $booking = Booking::factory()->create();
        $assignment = BookingTable::factory()->create(['booking_id' => $booking->getKey()]);
        try {
            BookingTable::factory()->create(['booking_id' => $booking->getKey()]);
            $this->fail('Duplicate active table must fail');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->getCode());
        }
        $assignment->update(['released_at' => now()]);
        BookingTable::factory()->create(['booking_id' => $booking->getKey()]);
        $this->assertDatabaseCount('booking_tables', 2);
        try {
            $booking->delete();
            $this->fail('Booking history must not cascade delete');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->getCode());
        }
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_tables', 2);
    }
}
