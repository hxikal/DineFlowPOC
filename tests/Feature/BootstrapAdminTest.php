<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BootstrapAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_bootstrap_creates_the_first_admin_with_a_secret_prompt(): void
    {
        $this->artisan('dineflow:bootstrap-admin', ['--username' => 'first.admin', '--name' => 'Admin Ujian'])
            ->expectsQuestion('Password (minimum 12 characters)', 'bootstrap-test-pass')
            ->expectsQuestion('Confirm password', 'bootstrap-test-pass')->assertSuccessful();
        $user = User::where('username', 'first.admin')->firstOrFail();
        $this->assertTrue($user->isAdmin());
        $this->assertTrue(Hash::check('bootstrap-test-pass', $user->password));
        $this->assertDatabaseHas('activity_logs', ['action' => 'BOOTSTRAP_ADMIN', 'user_id' => $user->getKey()]);
    }

    public function test_bootstrap_refuses_an_initialized_user_database(): void
    {
        User::factory()->create();
        $this->artisan('dineflow:bootstrap-admin', ['--username' => 'another.admin', '--name' => 'Admin Ujian'])
            ->expectsQuestion('Password (minimum 12 characters)', 'bootstrap-test-pass')
            ->expectsQuestion('Confirm password', 'bootstrap-test-pass')->assertFailed();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('activity_logs', 0);
    }
}
