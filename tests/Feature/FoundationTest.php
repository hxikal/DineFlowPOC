<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_all_three_previews_render_without_any_database_query(): void
    {
        DB::listen(fn () => $this->fail('P1 preview must not query a database.'));
        foreach (['dashboard', 'booking', 'tables'] as $screen) {
            $this->get('/preview/'.$screen)->assertOk()->assertSee('PRATONTON P1')->assertSee('lang="ms"', false);
        }
    }

    public function test_locale_persists_in_session_and_renders_english_on_navigation(): void
    {
        $this->postJson('/locale', ['locale' => 'en'])->assertOk()->assertSessionHas('locale', 'en');
        $this->get('/preview/booking')->assertOk()->assertSee('Guest details')->assertSee('lang="en"', false);
    }

    public function test_invalid_locale_is_rejected_without_changing_the_session(): void
    {
        $this->withSession(['locale' => 'ms'])->postJson('/locale', ['locale' => 'fr'])
            ->assertUnprocessable()->assertJsonValidationErrors('locale')->assertSessionHas('locale', 'ms');
    }

    public function test_only_the_three_known_screens_exist(): void
    {
        $this->get('/preview/users')->assertNotFound();
        $this->post('/preview/booking')->assertStatus(405);
    }

    public function test_named_source_connection_is_blocked_before_queries(): void
    {
        config(['database.connections.legacy' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'dineflow', 'username' => 'not-used', 'password' => '']]);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                DB::connection('legacy')->select('SELECT 1');
                $this->fail('Unsafe cached connections must remain blocked.');
            } catch (LogicException $e) {
                $this->assertStringContainsString('safety guard', $e->getMessage());
            }
        }
    }

    public function test_translation_catalogues_have_matching_keys(): void
    {
        $this->assertSame(array_keys(trans('ui', [], 'ms')), array_keys(trans('ui', [], 'en')));
        $this->assertSame('Asia/Kuala_Lumpur', config('app.timezone'));
        $this->assertSame('dineflow_laravel_session', config('session.cookie'));
    }
}
