<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingTable;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\AuditWriter;
use App\Services\BookingManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookingWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function fixture(bool $admin = false): array
    {
        $this->freezeTime();
        $user = ($admin ? User::factory()->admin() : User::factory())->create()->fresh();
        $outlet = Outlet::factory()->create();
        $tables = DiningTable::factory()->count(3)->sequence(['table_code' => 'A01'], ['table_code' => 'A02'], ['table_code' => 'A03'])->create(['outlet_id' => $outlet->getKey()]);
        $payment = PaymentMethod::factory()->create();
        $this->actingAs($user)->withSession(['outlet_id' => $outlet->getKey()]);

        return compact('user', 'outlet', 'tables', 'payment');
    }

    private function data(array $f, array $changes = []): array
    {
        return array_replace(['operation_key' => (string) Str::uuid(), 'dining_date' => now()->toDateString(), 'customer_name' => 'Tetamu Ujian P4', 'contact_number' => '+60010-0000000', 'payment_method_id' => $f['payment']->getKey(), 'remark' => 'Catatan rekaan', 'tables' => [['table_id' => $f['tables'][0]->getKey(), 'pax' => 3], ['table_id' => $f['tables'][1]->getKey(), 'pax' => 5]]], $changes);
    }

    private function createBooking(array $f, ?array $data = null): Booking
    {
        return app(BookingManager::class)->create($f['user'], $f['outlet']->getKey(), $data ?? $this->data($f));
    }

    public function test_guest_cannot_access_booking_or_availability(): void
    {
        $this->get('/bookings')->assertRedirect('/login');
        $this->get('/table-availability?date=2026-10-21')->assertRedirect('/login');
    }

    public function test_create_list_detail_edit_board_and_print_render(): void
    {
        $f = $this->fixture();
        $booking = $this->createBooking($f);
        foreach (['/bookings', '/bookings/create', '/bookings/'.$booking->getKey(), '/bookings/'.$booking->getKey().'/edit', '/bookings/'.$booking->getKey().'/print', '/table-board'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/bookings')->assertSee($booking->booking_code)->assertSee('Tetamu Ujian P4');
    }

    public function test_post_creates_multi_table_booking_and_ignores_client_total_status_actor(): void
    {
        $f = $this->fixture();
        $data = $this->data($f, ['pax' => 99999, 'booking_status' => 'cancelled', 'created_by' => 9999]);
        $this->post('/bookings', $data)->assertRedirect();
        $booking = Booking::firstOrFail();
        $this->assertSame(8, $booking->pax);
        $this->assertSame('booked', $booking->booking_status);
        $this->assertSame($f['user']->getKey(), $booking->created_by);
        $this->assertSame('+60010-0000000', $booking->contact_number);
        $this->assertSame(1, $booking->version);
        $this->assertDatabaseCount('booking_tables', 2);
        $this->assertDatabaseCount('operation_keys', 1);
        $audit = ActivityLog::where('action', 'CREATE_BOOKING')->firstOrFail();
        $this->assertSame(8, $audit->after_values['pax']);
        $this->assertCount(2, $audit->after_values['assignments']);
    }

    public function test_create_replay_does_not_duplicate_booking_audit_or_sequence(): void
    {
        $f = $this->fixture();
        $data = $this->data($f);
        $first = $this->createBooking($f, $data);
        $again = $this->createBooking($f, $data);
        $this->assertSame($first->getKey(), $again->getKey());
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseHas('booking_sequences', ['last_number' => 1]);
    }

    public function test_same_token_with_different_details_is_rejected(): void
    {
        $f = $this->fixture();
        $data = $this->data($f);
        $this->createBooking($f, $data);
        $data['customer_name'] = 'Different';
        $this->postJson('/bookings', $data)->assertUnprocessable()->assertJsonValidationErrors('operation_key');
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('activity_logs', 1);
    }

    public function test_occupied_table_conflict_rolls_back_entire_second_booking(): void
    {
        $f = $this->fixture();
        $this->createBooking($f);
        $this->post('/bookings', $this->data($f))->assertSessionHasErrors('tables');
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_tables', 2);
        $this->assertDatabaseCount('operation_keys', 1);
        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseHas('booking_sequences', ['last_number' => 1]);
    }

    public function test_audit_failure_rolls_back_booking_assignments_sequence_and_operation_key(): void
    {
        $f = $this->fixture();
        $this->mock(AuditWriter::class, fn (MockInterface $mock) => $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Synthetic audit failure')));
        try {
            $this->createBooking($f);
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic audit failure', $e->getMessage());
        }
        foreach (['bookings', 'booking_tables', 'booking_sequences', 'operation_keys', 'activity_logs'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_create_past_and_move_future_booking_into_past_are_rejected_for_admin(): void
    {
        $f = $this->fixture(true);
        $this->post('/bookings', $this->data($f, ['dining_date' => now()->subDay()->toDateString()]))->assertSessionHasErrors('dining_date');
        $booking = $this->createBooking($f);
        $data = $this->data($f, ['version' => 1, 'dining_date' => now()->subDay()->toDateString(), 'correction_reason' => 'Cannot bypass create rule']);
        $this->put('/bookings/'.$booking->getKey(), $data)->assertSessionHasErrors('dining_date');
        $this->assertSame(now()->toDateString(), $booking->fresh()->dining_date->toDateString());
    }

    public function test_duplicate_table_ids_zero_or_excessive_pax_and_missing_fields_are_rejected(): void
    {
        $f = $this->fixture();
        $id = $f['tables'][0]->getKey();
        $this->postJson('/bookings', [])->assertUnprocessable()->assertJsonValidationErrors(['operation_key', 'dining_date', 'customer_name', 'contact_number', 'payment_method_id', 'tables']);
        foreach ([0, 1000] as $pax) {
            $this->postJson('/bookings', $this->data($f, ['tables' => [['table_id' => $id, 'pax' => $pax]]]))->assertUnprocessable()->assertJsonValidationErrors('tables.0.pax');
        }
        $this->postJson('/bookings', $this->data($f, ['tables' => [['table_id' => $id, 'pax' => 1], ['table_id' => $id, 'pax' => 2]]]))->assertUnprocessable()->assertJsonValidationErrors('tables.0.table_id');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_inactive_or_foreign_table_and_inactive_payment_are_rejected(): void
    {
        $f = $this->fixture();
        $other = DiningTable::factory()->create();
        $this->post('/bookings', $this->data($f, ['tables' => [['table_id' => $other->getKey(), 'pax' => 2]]]))->assertSessionHasErrors('tables');
        $f['tables'][0]->update(['status' => 'inactive']);
        $this->post('/bookings', $this->data($f))->assertSessionHasErrors('tables');
        $f['tables'][0]->update(['status' => 'active']);
        $f['payment']->update(['status' => 'inactive']);
        $this->post('/bookings', $this->data($f))->assertSessionHasErrors('payment_method_id');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_edit_releases_old_assignments_preserves_history_and_recalculates_pax(): void
    {
        $f = $this->fixture();
        $booking = $this->createBooking($f);
        $data = $this->data($f, ['version' => 1, 'tables' => [['table_id' => $f['tables'][2]->getKey(), 'pax' => 6]], 'dining_date' => now()->addDay()->toDateString()]);
        $this->put('/bookings/'.$booking->getKey(), $data)->assertRedirect();
        $this->assertSame(6, $booking->fresh()->pax);
        $this->assertSame(2, $booking->fresh()->version);
        $this->assertDatabaseCount('booking_tables', 3);
        $this->assertSame(2, BookingTable::whereNotNull('released_at')->count());
        $this->assertSame(1, BookingTable::whereNull('released_at')->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'UPDATE_BOOKING']);
        $this->put('/bookings/'.$booking->getKey(), $data)->assertRedirect();
        $this->assertSame(2, $booking->fresh()->version);
        $this->assertDatabaseCount('booking_tables', 3);
    }

    public function test_pax_change_creates_a_historical_assignment_rather_than_overwriting_it(): void
    {
        $f = $this->fixture();
        $booking = $this->createBooking($f);
        $data = $this->data($f, ['version' => 1]);
        $data['tables'][0]['pax'] = 4;
        $this->put('/bookings/'.$booking->getKey(), $data)->assertRedirect();
        $this->assertDatabaseCount('booking_tables', 3);
        $this->assertDatabaseHas('booking_tables', ['booking_id' => $booking->getKey(), 'table_id' => $f['tables'][0]->getKey(), 'pax' => 3]);
        $this->assertSame(9, $booking->fresh()->pax);
    }

    public function test_stale_edit_does_not_overwrite_a_newer_version(): void
    {
        $f = $this->fixture();
        $booking = $this->createBooking($f);
        $this->put('/bookings/'.$booking->getKey(), $this->data($f, ['version' => 1, 'customer_name' => 'First edit']))->assertRedirect();
        $this->putJson('/bookings/'.$booking->getKey(), $this->data($f, ['version' => 1, 'customer_name' => 'Stale edit']))->assertStatus(409)->assertJsonValidationErrors('version');
        $this->assertSame('First edit', $booking->fresh()->customer_name);
        $this->assertSame(2, $booking->fresh()->version);
    }

    public function test_staff_can_edit_another_staff_booking_in_current_outlet(): void
    {
        $f = $this->fixture();
        $booking = $this->createBooking($f);
        $other = User::factory()->create()->fresh();
        $this->actingAs($other)->put('/bookings/'.$booking->getKey(), $this->data($f, ['version' => 1, 'customer_name' => 'Other staff edit']))->assertRedirect();
        $this->assertSame($other->getKey(), $booking->fresh()->updated_by);
    }

    public function test_cancel_requires_reason_releases_tables_and_same_token_replays_once(): void
    {
        $f = $this->fixture();
        $booking = $this->createBooking($f);
        $this->postJson('/bookings/'.$booking->getKey().'/cancel', ['operation_key' => (string) Str::uuid(), 'version' => 1])->assertUnprocessable()->assertJsonValidationErrors('cancellation_reason');
        $data = ['operation_key' => (string) Str::uuid(), 'version' => 1, 'cancellation_reason' => 'Tetamu membatalkan'];
        $this->post('/bookings/'.$booking->getKey().'/cancel', $data)->assertRedirect();
        $this->post('/bookings/'.$booking->getKey().'/cancel', $data)->assertRedirect();
        $this->assertSame('cancelled', $booking->fresh()->booking_status);
        $this->assertSame(2, $booking->fresh()->version);
        $this->assertSame(0, BookingTable::whereNull('released_at')->count());
        $this->assertSame(1, ActivityLog::where('action', 'CANCEL_BOOKING')->count());
        $this->post('/bookings', $this->data($f))->assertRedirect();
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_cancelled_records_are_read_only_for_new_mutations(): void
    {
        $f = $this->fixture(true);
        $booking = $this->createBooking($f);
        app(BookingManager::class)->cancel($f['user'], $f['outlet']->getKey(), $booking->getKey(), ['operation_key' => (string) Str::uuid(), 'version' => 1, 'cancellation_reason' => 'Test']);
        $this->get('/bookings/'.$booking->getKey().'/edit')->assertForbidden();
        $this->put('/bookings/'.$booking->getKey(), $this->data($f, ['version' => 2]))->assertForbidden();
        $this->assertDatabaseCount('activity_logs', 2);
    }

    public function test_cross_outlet_detail_edit_cancel_print_and_availability_are_hidden(): void
    {
        $f = $this->fixture();
        $booking = $this->createBooking($f);
        $other = Outlet::factory()->create();
        $this->withSession(['outlet_id' => $other->getKey()]);
        foreach (['/bookings/'.$booking->getKey(), '/bookings/'.$booking->getKey().'/edit', '/bookings/'.$booking->getKey().'/print', '/table-availability?date='.now()->toDateString().'&except='.$booking->getKey()] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->put('/bookings/'.$booking->getKey(), $this->data($f, ['version' => 1]))->assertNotFound();
        $this->post('/bookings/'.$booking->getKey().'/cancel', ['operation_key' => (string) Str::uuid(), 'version' => 1, 'cancellation_reason' => 'Forged'])->assertNotFound();
    }

    public function test_availability_and_search_are_scoped_and_escape_customer_input(): void
    {
        $f = $this->fixture();
        $booking = $this->createBooking($f, $this->data($f, ['customer_name' => '<script>alert(1)</script>']));
        $this->get('/bookings?date='.now()->toDateString().'&q='.urlencode('alert(1)'))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/bookings/'.$booking->getKey().'/print')->assertOk()->assertDontSee('<script>alert(1)</script>', false);
        $this->getJson('/table-availability?date='.now()->toDateString())->assertOk()->assertJsonPath('tables.0.available', false);
        $this->getJson('/table-availability?date='.now()->toDateString().'&except='.$booking->getKey())->assertOk()->assertJsonPath('tables.0.available', true);
    }

    public function test_staff_cannot_modify_past_booking_but_can_view_and_print(): void
    {
        $f = $this->fixture();
        $booking = $this->createBooking($f);
        $this->travel(1)->days();
        $this->get('/bookings/'.$booking->getKey())->assertOk();
        $this->get('/bookings/'.$booking->getKey().'/print')->assertOk();
        $this->put('/bookings/'.$booking->getKey(), $this->data($f, ['version' => 1]))->assertForbidden();
        $this->post('/bookings/'.$booking->getKey().'/cancel', ['operation_key' => (string) Str::uuid(), 'version' => 1, 'cancellation_reason' => 'Not permitted'])->assertForbidden();
    }

    public function test_admin_past_correction_requires_reason_and_can_retain_inactive_historical_table(): void
    {
        $f = $this->fixture(true);
        $booking = $this->createBooking($f);
        $this->travel(1)->days();
        $f['tables'][0]->update(['status' => 'inactive']);
        $data = $this->data($f, ['dining_date' => $booking->dining_date->toDateString(), 'version' => 1]);
        $this->put('/bookings/'.$booking->getKey(), $data)->assertSessionHasErrors('correction_reason');
        $data['correction_reason'] = 'Pembetulan rekod';
        $this->put('/bookings/'.$booking->getKey(), $data)->assertRedirect();
        $audit = ActivityLog::where('action', 'CORRECT_PAST_BOOKING')->firstOrFail();
        $this->assertSame('Pembetulan rekod', $audit->after_values['correction_reason']);
        $this->assertDatabaseCount('booking_tables', 2);
    }

    public static function policyCases(): array
    {
        return ['staff current' => ['user', 'booked', 0, true], 'staff past' => ['user', 'booked', -1, false], 'admin past' => ['admin', 'booked', -1, true], 'admin cancelled' => ['admin', 'cancelled', 0, false], 'staff cancelled' => ['user', 'cancelled', 0, false]];
    }

    #[DataProvider('policyCases')]
    public function test_booking_policy_matrix(string $role, string $status, int $offset, bool $allowed): void
    {
        $f = $this->fixture($role === 'admin');
        $record = Booking::factory()->create(['table_id' => $f['tables'][0]->getKey(), 'outlet_id' => $f['outlet']->getKey(), 'booking_status' => $status, 'dining_date' => now()->addDays($offset)->toDateString()]);
        $this->assertTrue(Gate::forUser($f['user'])->allows('view', [$record, $f['outlet']->getKey()]));
        $this->assertSame($allowed, Gate::forUser($f['user'])->allows('update', [$record, $f['outlet']->getKey()]));
        $this->assertSame($allowed, Gate::forUser($f['user'])->allows('cancel', [$record, $f['outlet']->getKey()]));
    }

    public function test_form_outlet_snapshot_rejects_a_context_changed_in_another_tab(): void
    {
        $f = $this->fixture();
        $data = $this->data($f, ['form_outlet_id' => $f['outlet']->getKey()]);
        $other = Outlet::factory()->create();
        $this->withSession(['outlet_id' => $other->getKey()])->postJson('/bookings', $data)->assertUnprocessable()->assertJsonValidationErrors('form_outlet_id');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_invalid_array_input_can_redisplay_the_form_without_a_server_error(): void
    {
        $f = $this->fixture();
        $data = $this->data($f, ['customer_name' => ['bad'], 'contact_number' => ['bad'], 'remark' => ['bad'], 'dining_date' => ['bad'], 'tables' => 'bad']);
        $this->from('/bookings/create')->post('/bookings', $data)->assertSessionHasErrors(['customer_name', 'contact_number', 'remark', 'dining_date', 'tables']);
        $this->get('/bookings/create')->assertOk();
    }

    public function test_conflicting_edit_leaves_original_assignments_and_audit_unchanged(): void
    {
        $f = $this->fixture();
        $booking = $this->createBooking($f);
        $third = [['table_id' => $f['tables'][2]->getKey(), 'pax' => 2]];
        $this->createBooking($f, $this->data($f, ['tables' => $third]));
        $this->put('/bookings/'.$booking->getKey(), $this->data($f, ['version' => 1, 'tables' => $third]))->assertSessionHasErrors('tables');
        $this->assertSame(1, $booking->fresh()->version);
        $this->assertSame(8, $booking->fresh()->pax);
        $this->assertSame(0, BookingTable::whereNotNull('released_at')->count());
        $this->assertDatabaseCount('activity_logs', 2);
    }

    public function test_admin_can_cancel_a_past_booked_record_with_a_reason(): void
    {
        $f = $this->fixture(true);
        $booking = $this->createBooking($f);
        $this->travel(1)->days();
        $this->post('/bookings/'.$booking->getKey().'/cancel', ['operation_key' => (string) Str::uuid(), 'version' => 1, 'cancellation_reason' => 'Pembatalan pembetulan lampau'])->assertRedirect();
        $this->assertSame('cancelled', $booking->fresh()->booking_status);
        $this->assertSame('Pembatalan pembetulan lampau', $booking->fresh()->cancellation_reason);
        $this->get('/bookings/'.$booking->getKey().'/print')->assertOk()->assertSee('Pembatalan pembetulan lampau')->assertSee($f['tables'][0]->table_code);
    }
}
