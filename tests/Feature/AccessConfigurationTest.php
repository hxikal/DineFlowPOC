<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingTable;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\OutletSection;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\AuditWriter;
use App\Services\ConfigurationManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

class AccessConfigurationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function userData(array $extra = []): array
    {
        return array_merge(['name' => 'Staf Ujian', 'username' => 'staf.ujian', 'role' => 'user', 'department' => 'Hostess', 'status' => 'active', 'password' => 'new-test-passphrase', 'password_confirmation' => 'new-test-passphrase'], $extra);
    }

    private function outletData(array $extra = []): array
    {
        return array_merge(['outlet_code' => 'NEW', 'outlet_name' => 'Outlet Baharu', 'sort_order' => 0, 'status' => 'active'], $extra);
    }

    public function test_guest_is_redirected_and_staff_cannot_access_admin_endpoints(): void
    {
        $this->get('/workspace')->assertRedirect('/login');
        $staff = User::factory()->create();
        foreach (['/admin/users', '/admin/audit', '/admin/configuration/outlets', '/admin/configuration/payments'] as $url) {
            $this->actingAs($staff)->get($url)->assertForbidden();
        }
        $this->actingAs($staff)->post('/admin/users', $this->userData())->assertForbidden();
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_gate_uses_role_and_status_rather_than_department(): void
    {
        $admin = User::factory()->admin()->create(['department' => 'Hostess']);
        $staff = User::factory()->create(['department' => 'Marcom']);
        $inactive = User::factory()->admin()->inactive()->create();
        $this->assertTrue(Gate::forUser($admin)->allows('administer'));
        $this->assertFalse(Gate::forUser($staff)->allows('administer'));
        $this->assertFalse(Gate::forUser($inactive)->allows('administer'));
    }

    public function test_login_records_last_login_and_audit_without_password(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['username' => 'login.staff']);
        $this->post('/login', ['username' => $user->username, 'password' => 'test-passphrase-123'])->assertRedirect('/workspace')->assertSessionHas('auth_version', 1);
        $this->assertAuthenticatedAs($user);
        $this->assertSame(now()->toDateTimeString(), $user->fresh()->last_login_at->toDateTimeString());
        $this->assertDatabaseHas('activity_logs', ['action' => 'LOGIN', 'user_id' => $user->getKey()]);
        $this->assertStringNotContainsString('password', ActivityLog::first()->toJson());
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create();
        $this->post('/login', ['username' => $user->username, 'password' => 'test-passphrase-123'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'throttle.test', 'password' => 'wrong-password'])->assertSessionHasErrors('username');
        }
        $this->post('/login', ['username' => 'throttle.test', 'password' => 'wrong-password'])->assertSessionHasErrors(['username' => __('admin.throttled')]);
    }

    public function test_deactivation_invalidates_an_existing_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/workspace')->assertOk();
        $user->update(['status' => 'inactive']);
        $this->get('/workspace')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_role_change_takes_effect_on_the_next_request(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $admin->update(['role' => 'user']);
        $this->get('/admin/users')->assertForbidden();
    }

    public function test_admin_creates_a_hashed_user_and_audit_atomically(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/users', $this->userData(['user_id' => 999, 'auth_version' => 99]))->assertRedirect('/admin/users');
        $user = User::where('username', 'staf.ujian')->firstOrFail();
        $this->assertTrue(Hash::check('new-test-passphrase', $user->password));
        $this->assertNotSame(999, $user->getKey());
        $this->assertSame(1, $user->auth_version);
        $this->assertDatabaseHas('activity_logs', ['action' => 'CREATE_USER', 'reference_id' => $user->getKey()]);
        $this->assertStringNotContainsString($user->password, ActivityLog::first()->toJson());
    }

    public function test_last_active_admin_cannot_be_demoted_or_disabled(): void
    {
        $admin = User::factory()->admin()->create();
        $data = $this->userData(['username' => $admin->username, 'password' => '', 'password_confirmation' => '', 'status' => 'inactive']);
        $this->actingAs($admin)->put('/admin/users/'.$admin->getKey(), $data)->assertSessionHasErrors('status');
        $this->assertTrue($admin->fresh()->isAdmin());
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_another_admin_can_be_demoted_when_one_active_admin_remains(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->admin()->create();
        $data = $this->userData(['username' => $target->username, 'password' => '', 'password_confirmation' => '']);
        $this->actingAs($actor)->put('/admin/users/'.$target->getKey(), $data)->assertRedirect('/admin/users');
        $this->assertSame('user', $target->fresh()->role);
        $this->assertTrue($actor->fresh()->isAdmin());
    }

    public function test_admin_reset_revokes_old_sessions_and_keeps_passwords_out_of_audit(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $this->actingAs($admin)->put('/admin/users/'.$target->getKey().'/password', ['password' => 'replacement-password', 'password_confirmation' => 'replacement-password'])->assertRedirect();
        $this->assertTrue(Hash::check('replacement-password', $target->fresh()->password));
        $this->assertSame(2, $target->fresh()->auth_version);
        $this->actingAs($target)->withSession(['auth_version' => 1])->get('/workspace')->assertRedirect('/login');
        $this->assertStringNotContainsString('replacement-password', ActivityLog::first()->toJson());
    }

    public function test_self_password_change_requires_the_current_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/account/password', ['current_password' => 'wrong', 'password' => 'replacement-password', 'password_confirmation' => 'replacement-password'])->assertSessionHasErrors('current_password');
        $this->assertSame(1, $user->fresh()->auth_version);
        $this->put('/account/password', ['current_password' => 'test-passphrase-123', 'password' => 'replacement-password', 'password_confirmation' => 'replacement-password'])->assertRedirect()->assertSessionHas('auth_version', 2);
        $this->assertTrue(Hash::check('replacement-password', $user->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['action' => 'CHANGE_PASSWORD']);
    }

    public function test_admin_cannot_bypass_current_password_for_self_reset(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put('/admin/users/'.$admin->getKey().'/password', ['password' => 'replacement-password', 'password_confirmation' => 'replacement-password'])->assertSessionHasErrors('password');
    }

    public function test_user_validation_rejects_duplicate_username_and_short_password(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/users', $this->userData(['username' => $admin->username, 'password' => 'short', 'password_confirmation' => 'different']))->assertSessionHasErrors(['username', 'password']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_selected_outlet_must_be_active_and_is_saved_server_side(): void
    {
        $staff = User::factory()->create();
        $outlet = Outlet::factory()->create();
        $inactive = Outlet::factory()->create(['status' => 'inactive']);
        $this->actingAs($staff)->post('/context', ['outlet_id' => $inactive->getKey()])->assertNotFound();
        $this->post('/context', ['outlet_id' => $outlet->getKey(), 'dining_date' => '2026-11-01'])->assertRedirect()->assertSessionHas('outlet_id', $outlet->getKey());
    }

    public function test_forged_outlet_id_in_section_body_cannot_override_session_context(): void
    {
        $admin = User::factory()->admin()->create();
        $current = Outlet::factory()->create();
        $other = Outlet::factory()->create();
        $this->actingAs($admin)->withSession(['outlet_id' => $current->getKey()])->post('/admin/configuration/sections', ['outlet_id' => $other->getKey(), 'section_code' => 'S1', 'section_name' => 'Section One', 'sort_order' => 0, 'status' => 'active'])->assertRedirect();
        $this->assertDatabaseHas('outlet_sections', ['section_code' => 'S1', 'outlet_id' => $current->getKey()]);
    }

    public function test_cross_outlet_record_ids_and_section_ids_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $current = Outlet::factory()->create();
        $other = OutletSection::factory()->create();
        $this->actingAs($admin)->withSession(['outlet_id' => $current->getKey()])->get('/admin/configuration/sections/'.$other->getKey().'/edit')->assertNotFound();
        $this->put('/admin/configuration/sections/'.$other->getKey(), ['section_code' => 'UNIQUE', 'section_name' => 'Forged', 'sort_order' => 0, 'status' => 'active'])->assertNotFound();
        $this->post('/admin/configuration/tables', ['table_code' => 'T1', 'table_number' => 1, 'section_id' => $other->getKey(), 'sort_order' => 0, 'status' => 'active'])->assertSessionHasErrors('section_id');
    }

    public function test_booked_today_blocks_deactivation_of_outlet_section_and_table(): void
    {
        $this->freezeTime();
        $admin = User::factory()->admin()->create();
        $section = OutletSection::factory()->create();
        $table = DiningTable::factory()->create(['outlet_id' => $section->outlet_id, 'section_id' => $section->getKey()]);
        $booking = Booking::factory()->create(['outlet_id' => $section->outlet_id, 'table_id' => $table->getKey(), 'dining_date' => now()->toDateString()]);
        BookingTable::factory()->create(['booking_id' => $booking->getKey()]);
        $this->actingAs($admin)->withSession(['outlet_id' => $section->outlet_id]);
        $this->put('/admin/configuration/tables/'.$table->getKey(), ['table_code' => $table->table_code, 'table_number' => 1, 'section_id' => $section->getKey(), 'sort_order' => 0, 'status' => 'inactive'])->assertSessionHasErrors('status');
        $this->put('/admin/configuration/sections/'.$section->getKey(), ['section_code' => $section->section_code, 'section_name' => $section->section_name, 'sort_order' => 0, 'status' => 'inactive'])->assertSessionHasErrors('status');
        $outlet = Outlet::findOrFail($section->outlet_id);
        $this->put('/admin/configuration/outlets/'.$outlet->getKey(), $this->outletData(['outlet_code' => $outlet->outlet_code, 'outlet_name' => $outlet->outlet_name, 'status' => 'inactive']))->assertSessionHasErrors('status');
        $this->assertSame('active', $table->fresh()->status);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_past_and_cancelled_bookings_do_not_block_deactivation_or_lose_history(): void
    {
        $this->freezeTime();
        $admin = User::factory()->admin()->create();
        $table = DiningTable::factory()->create();
        Booking::factory()->create(['table_id' => $table->getKey(), 'outlet_id' => $table->outlet_id, 'dining_date' => now()->subDay()->toDateString()]);
        Booking::factory()->create(['table_id' => $table->getKey(), 'outlet_id' => $table->outlet_id, 'booking_status' => 'cancelled', 'dining_date' => now()->addDay()->toDateString()]);
        $this->actingAs($admin)->withSession(['outlet_id' => $table->outlet_id])->put('/admin/configuration/tables/'.$table->getKey(), ['table_code' => $table->table_code, 'table_number' => 1, 'section_id' => '', 'sort_order' => 0, 'status' => 'inactive'])->assertRedirect();
        $this->assertSame('inactive', $table->fresh()->status);
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_payment_method_can_be_edited_and_deactivated_without_deleting_history(): void
    {
        $admin = User::factory()->admin()->create();
        $method = PaymentMethod::factory()->create();
        $this->actingAs($admin)->put('/admin/configuration/payments/'.$method->getKey(), ['method_name' => 'Tunai Ujian', 'status' => 'inactive'])->assertRedirect();
        $this->assertDatabaseHas('payment_methods', ['payment_method_id' => $method->getKey(), 'status' => 'inactive']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'UPDATE_CONFIG', 'reference_type' => 'payments']);
    }

    public function test_audit_failure_rolls_back_configuration_mutation(): void
    {
        $admin = User::factory()->admin()->create();
        $this->mock(AuditWriter::class, function (MockInterface $mock) {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Synthetic audit failure'));
        });
        try {
            app(ConfigurationManager::class)->save($admin, 'outlets', null, $this->outletData(), null);
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic audit failure', $e->getMessage());
        }
        $this->assertDatabaseCount('outlets', 0);
    }

    public function test_database_rejects_audit_update_and_deletion(): void
    {
        $log = ActivityLog::factory()->create();
        foreach (['update', 'delete'] as $operation) {
            try {
                $query = DB::table('activity_logs')->where('activity_id', $log->getKey());
                $operation === 'update' ? $query->update(['description' => 'tamper']) : $query->delete();
                $this->fail('Audit mutation must fail');
            } catch (QueryException $e) {
                $this->assertStringContainsString('append-only', $e->getMessage());
            }
        }
        $this->assertDatabaseHas('activity_logs', ['activity_id' => $log->getKey(), 'description' => 'Synthetic test event']);
    }

    public function test_locale_preference_and_translation_do_not_translate_stored_names(): void
    {
        $user = User::factory()->create(['name' => 'Nama Kekal']);
        $this->actingAs($user)->postJson('/locale', ['locale' => 'en'])->assertOk();
        $this->get('/workspace')->assertOk()->assertSee('Nama Kekal')->assertSee('Your workspace is ready.');
        $this->assertSame('en', $user->fresh()->preferred_locale);
    }

    public function test_logout_invalidates_authentication(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', ['action' => 'LOGOUT']);
    }

    public function test_status_changes_revoke_old_sessions_even_after_reactivation(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $data = $this->userData(['username' => $target->username, 'password' => '', 'password_confirmation' => '', 'status' => 'inactive']);
        $this->actingAs($admin)->put('/admin/users/'.$target->getKey(), $data)->assertRedirect();
        $this->assertSame(2, $target->fresh()->auth_version);
        $data['status'] = 'active';
        $this->put('/admin/users/'.$target->getKey(), $data)->assertRedirect();
        $this->assertSame(3, $target->fresh()->auth_version);
        $this->actingAs($target)->withSession(['auth_version' => 1])->get('/workspace')->assertRedirect('/login');
    }

    public function test_active_table_cannot_use_an_inactive_section(): void
    {
        $admin = User::factory()->admin()->create();
        $section = OutletSection::factory()->create(['status' => 'inactive']);
        $this->actingAs($admin)->withSession(['outlet_id' => $section->outlet_id])->post('/admin/configuration/tables', ['table_code' => 'T-BLOCK', 'table_number' => 1, 'sort_order' => 0, 'section_id' => $section->getKey(), 'status' => 'active'])->assertSessionHasErrors('section_id');
        $this->assertDatabaseCount('dining_tables', 0);
    }

    public function test_validation_failure_never_flashes_passwords_and_names_are_escaped(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/users', $this->userData(['username' => '', 'password' => 'not-for-session', 'password_confirmation' => 'mismatch']))->assertSessionHasErrors(['username', 'password'])->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        $this->post('/admin/users', $this->userData(['name' => '<script>alert(1)</script>']))->assertRedirect();
        $this->get('/admin/users')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
