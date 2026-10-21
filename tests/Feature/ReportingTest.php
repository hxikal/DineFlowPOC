<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingTable;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\OutletSection;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function fixture(bool $admin = false): array
    {
        $this->travelTo(now()->setDate(2026, 10, 21)->startOfDay());
        $user = ($admin ? User::factory()->admin() : User::factory())->create();
        $outlet = Outlet::factory()->create(['outlet_name' => 'Outlet Laporan Ujian']);
        $section = OutletSection::factory()->create(['outlet_id' => $outlet->getKey(), 'section_name' => 'Ruang Ujian']);
        $tables = DiningTable::factory()->count(4)->sequence(['table_code' => 'A01'], ['table_code' => 'A02'], ['table_code' => 'A03'], ['table_code' => 'A04'])->create(['outlet_id' => $outlet->getKey(), 'section_id' => $section->getKey()]);
        $payment = PaymentMethod::factory()->create(['method_name' => 'Tunai Ujian']);
        $card = PaymentMethod::factory()->create(['method_name' => 'Kad Ujian']);
        $base = ['outlet_id' => $outlet->getKey(), 'created_by' => $user->getKey(), 'payment_method_id' => $payment->getKey(), 'table_id' => $tables[0]->getKey()];
        $first = Booking::factory()->create($base + ['booking_code' => 'DF-2026-0001', 'customer_name' => 'Tetamu Alpha', 'pax' => 8]);
        foreach ([0 => 3, 1 => 5] as $i => $pax) {
            BookingTable::factory()->create(['booking_id' => $first->getKey(), 'table_id' => $tables[$i]->getKey(), 'dining_date' => '2026-10-21', 'pax' => $pax]);
        }
        BookingTable::factory()->create(['booking_id' => $first->getKey(), 'table_id' => $tables[3]->getKey(), 'dining_date' => '2026-10-21', 'pax' => 100, 'released_at' => now()]);
        $second = Booking::factory()->create(array_replace($base, ['booking_code' => 'DF-2026-0002', 'customer_name' => 'Tetamu Beta', 'pax' => 3, 'table_id' => $tables[2]->getKey(), 'payment_method_id' => $card->getKey()]));
        BookingTable::factory()->create(['booking_id' => $second->getKey(), 'table_id' => $tables[2]->getKey(), 'dining_date' => '2026-10-21', 'pax' => 3]);
        $cancelled = Booking::factory()->create($base + ['booking_code' => 'DF-2026-0003', 'customer_name' => 'Tetamu Batal', 'pax' => 9, 'booking_status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'Sebab ujian']);
        BookingTable::factory()->create(['booking_id' => $cancelled->getKey(), 'table_id' => $tables[3]->getKey(), 'dining_date' => '2026-10-21', 'pax' => 9, 'released_at' => now()]);
        $tomorrow = Booking::factory()->create($base + ['booking_code' => 'DF-2026-0004', 'customer_name' => 'Tetamu Esok', 'pax' => 5, 'dining_date' => '2026-10-22']);
        BookingTable::factory()->create(['booking_id' => $tomorrow->getKey(), 'table_id' => $tables[0]->getKey(), 'dining_date' => '2026-10-22', 'pax' => 5]);
        $foreign = Booking::factory()->create(['booking_code' => 'DF-2026-9999', 'customer_name' => 'FOREIGN SECRET', 'pax' => 777]);
        $this->actingAs($user)->withSession(['outlet_id' => $outlet->getKey(), 'dining_date' => '2026-10-21']);

        return compact('user', 'outlet', 'section', 'tables', 'payment', 'card', 'first', 'second', 'cancelled', 'tomorrow', 'foreign');
    }

    private function csvRows(string $url = '/reports/csv'): array
    {
        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, substr($content, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    public function test_guests_cannot_read_any_reporting_endpoint(): void
    {
        foreach (['/workspace', '/reports', '/reports/csv', '/reports/print', '/analytics'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_dashboard_counts_bookings_and_pax_once_and_excludes_cancelled(): void
    {
        $this->fixture();
        $this->get('/workspace')->assertOk()->assertViewHas('metrics', fn ($metrics) => $metrics === ['booked' => 2, 'pax' => 11, 'available' => 1, 'busy' => 3, 'inactive_assignments' => 0]);
    }

    public function test_dashboard_only_counts_eligible_active_tables_and_flags_inactive_assignments(): void
    {
        $f = $this->fixture();
        $f['tables'][0]->update(['status' => 'inactive']);
        $this->get('/workspace')->assertOk()->assertViewHas('metrics', fn ($m) => $m['busy'] === 2 && $m['available'] === 1 && $m['inactive_assignments'] === 1);
        $f['section']->update(['status' => 'inactive']);
        $this->get('/workspace')->assertOk()->assertViewHas('metrics', fn ($m) => $m['busy'] === 0 && $m['available'] === 0 && $m['inactive_assignments'] === 3);
    }

    public function test_report_and_analytics_match_known_booking_level_totals(): void
    {
        $this->fixture();
        $expected = ['booked' => ['bookings' => 2, 'pax' => 11], 'cancelled' => ['bookings' => 1, 'pax' => 9]];
        foreach (['/reports', '/analytics'] as $url) {
            $this->get($url)->assertOk()->assertViewHas('summary', $expected)->assertDontSee('FOREIGN SECRET');
        }
        $this->get('/analytics')->assertViewHas('trend', fn ($rows) => $rows->count() === 2 && (int) $rows->sum('bookings') === 3 && (int) $rows->sum('pax') === 20)
            ->assertViewHas('payments', fn ($rows) => $rows->count() === 3 && (int) $rows->sum('bookings') === 3 && (int) $rows->sum('pax') === 20);
    }

    public function test_date_range_includes_both_endpoints_and_dashboard_respects_selected_date(): void
    {
        $this->fixture();
        $this->get('/reports?from=2026-10-21&to=2026-10-22')->assertOk()->assertViewHas('summary', ['booked' => ['bookings' => 3, 'pax' => 16], 'cancelled' => ['bookings' => 1, 'pax' => 9]]);
        $this->get('/workspace?date=2026-10-22')->assertOk()->assertViewHas('metrics', fn ($m) => $m['booked'] === 1 && $m['pax'] === 5 && $m['busy'] === 1 && $m['available'] === 3);
    }

    public function test_payment_status_search_and_assignment_filters_are_applied_consistently(): void
    {
        $f = $this->fixture();
        $params = ['from' => '2026-10-21', 'to' => '2026-10-22', 'status' => 'booked', 'payment_method_id' => $f['payment']->getKey(), 'q' => 'Alpha', 'table_id' => $f['tables'][0]->getKey(), 'section_id' => $f['section']->getKey()];
        $query = http_build_query($params);
        $this->get('/reports?'.$query)->assertOk()->assertViewHas('summary', ['booked' => ['bookings' => 1, 'pax' => 8], 'cancelled' => ['bookings' => 0, 'pax' => 0]]);
        $rows = $this->csvRows('/reports/csv?'.$query);
        $this->assertCount(2, $rows);
        $this->assertSame('DF-2026-0001', $rows[1][1]);
        $this->assertSame('8', $rows[1][6]);
        $this->get('/reports/print?'.$query)->assertOk()->assertSee('Tetamu Alpha')->assertDontSee('Tetamu Beta')->assertSee('Ruang Ujian');
        $this->get('/analytics?'.$query)->assertOk()->assertViewHas('summary', ['booked' => ['bookings' => 1, 'pax' => 8], 'cancelled' => ['bookings' => 0, 'pax' => 0]]);
    }

    public function test_released_history_is_excluded_for_booked_but_searchable_for_cancelled(): void
    {
        $f = $this->fixture();
        $this->get('/reports?table_id='.$f['tables'][3]->getKey())->assertOk()->assertViewHas('summary', ['booked' => ['bookings' => 0, 'pax' => 0], 'cancelled' => ['bookings' => 1, 'pax' => 9]])->assertDontSee('Tetamu Alpha')->assertSee('Tetamu Batal');
    }

    public function test_csv_and_print_export_all_results_beyond_page_and_preserve_order(): void
    {
        $f = $this->fixture();
        for ($i = 5; $i <= 29; $i++) {
            Booking::factory()->create(['outlet_id' => $f['outlet']->getKey(), 'created_by' => $f['user']->getKey(), 'table_id' => $f['tables'][0]->getKey(), 'payment_method_id' => $f['payment']->getKey(), 'booking_code' => 'DF-2026-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'customer_name' => 'Extra '.$i, 'pax' => 1]);
        }
        $this->get('/reports?sort=booking_code&direction=asc')->assertOk()->assertViewHas('bookings', fn ($rows) => $rows->count() === 20 && $rows->total() === 28);
        $rows = $this->csvRows('/reports/csv?sort=booking_code&direction=asc&page=2');
        $this->assertCount(29, $rows);
        $this->assertSame('DF-2026-0001', $rows[1][1]);
        $this->assertSame('DF-2026-0029', $rows[28][1]);
        $this->get('/reports/print?sort=booking_code&direction=asc&page=2')->assertOk()->assertViewHas('bookings', fn ($rows) => $rows->count() === 28)->assertSee('Extra 29')->assertDontSee('FOREIGN SECRET');
    }

    public function test_csv_formula_protection_unicode_and_quoted_newline_fields_round_trip(): void
    {
        $f = $this->fixture();
        $f['first']->update(['customer_name' => '=SUM(1,2)', 'contact_number' => '+601234', 'remark' => "  @SUM(1,1)\nTetamu, \"Ujian\" \\ 中文"]);
        $rows = $this->csvRows('/reports/csv?sort=booking_code&direction=asc');
        $this->assertSame("'=SUM(1,2)", $rows[1][3]);
        $this->assertSame("'+601234", $rows[1][4]);
        $this->assertSame("'  @SUM(1,1)\nTetamu, \"Ujian\" \\ 中文", $rows[1][9]);
        $this->assertSame('A01, A02', $rows[1][5]);
        $this->assertSame('8', $rows[1][6]);
    }

    #[DataProvider('formulaValues')]
    public function test_csv_neutralizes_dangerous_text_in_exported_customer_fields(string $value): void
    {
        $f = $this->fixture();
        $f['first']->update(['customer_name' => $value]);
        $rows = $this->csvRows('/reports/csv?q=DF-2026-0001');
        $this->assertSame("'".$value, $rows[1][3]);
    }

    public static function formulaValues(): array
    {
        return [['-1+2'], ['@SUM(A1)'], ["\t=1+1"], ["\n=1+1"], [" \r+1"], ["\u{00A0}=1"]];
    }

    public function test_csv_totals_reconcile_to_dashboard_and_cancelled_is_separate(): void
    {
        $this->fixture();
        $rows = $this->csvRows();
        array_shift($rows);
        $booked = array_values(array_filter($rows, fn ($row) => $row[7] === 'Ditempah'));
        $cancelled = array_values(array_filter($rows, fn ($row) => $row[7] === 'Dibatalkan'));
        $this->assertCount(2, $booked);
        $this->assertSame(11, array_sum(array_column($booked, 6)));
        $this->assertCount(1, $cancelled);
        $this->assertSame(9, array_sum(array_column($cancelled, 6)));
    }

    public function test_an_explicit_outlet_id_cannot_override_session_scope_and_foreign_filters_are_rejected(): void
    {
        $f = $this->fixture();
        $this->get('/reports?outlet_id='.$f['foreign']->outlet_id)->assertOk()->assertDontSee('FOREIGN SECRET');
        $rows = $this->csvRows('/reports/csv?outlet_id='.$f['foreign']->outlet_id);
        $this->assertCount(4, $rows);
        foreach (['/reports', '/reports/csv', '/reports/print', '/analytics'] as $url) {
            $this->getJson($url.'?table_id='.$f['foreign']->table_id)->assertUnprocessable()->assertJsonValidationErrors('table_id');
        }
    }

    public function test_switching_outlet_in_another_tab_prevents_exporting_under_a_different_context(): void
    {
        $f = $this->fixture();
        $this->getJson('/reports/csv?outlet_context='.$f['foreign']->outlet_id)->assertUnprocessable()->assertJsonValidationErrors('outlet_context');
        $this->from('/reports?outlet_context='.$f['foreign']->outlet_id)->get('/reports/csv?outlet_context='.$f['foreign']->outlet_id)->assertRedirect('/reports');
        $this->get('/reports')->assertOk()->assertSee('Outlet telah berubah dalam tab lain.');
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected_before_query_or_export(array $params, string $error): void
    {
        $this->fixture();
        $this->getJson('/reports/csv?'.http_build_query($params))->assertUnprocessable()->assertJsonValidationErrors($error);
    }

    public static function invalidFilters(): array
    {
        return [[['from' => '2026-02-30'], 'from'], [['from' => '2026-10-22', 'to' => '2026-10-21'], 'to'], [['from' => ['bad']], 'from'], [['sort' => 'pax desc; DROP TABLE bookings'], 'sort'], [['direction' => 'desc, booking_id'], 'direction'], [['status' => 'deleted'], 'status'], [['q' => str_repeat('x', 101)], 'q'], [['page' => 0], 'page'], [['payment_method_id' => 999999], 'payment_method_id']];
    }

    public function test_search_treats_percent_and_underscore_as_literal_characters(): void
    {
        $f = $this->fixture();
        $f['first']->update(['customer_name' => 'Literal 50%_Guest']);
        $this->get('/reports?q='.urlencode('%_'))->assertOk()->assertViewHas('bookings', fn ($rows) => $rows->total() === 1)->assertSee('Literal 50%_Guest');
        $f['first']->update(['customer_name' => 'Literal 0']);
        $this->get('/reports?q=Literal+0')->assertOk()->assertViewHas('bookings', fn ($rows) => $rows->total() === 1);
        $f['second']->update(['booking_code' => 'NOZEROS', 'contact_number' => '111111']);
        $this->get('/reports?q=0&status=booked')->assertOk()->assertViewHas('bookings', fn ($rows) => $rows->total() === 1);
    }

    public function test_report_and_print_escape_guest_data(): void
    {
        $f = $this->fixture();
        $f['first']->update(['customer_name' => '<script>alert("x")</script>']);
        foreach (['/reports', '/reports/print'] as $url) {
            $this->get($url)->assertOk()->assertSee('<script>alert("x")</script>')->assertDontSee('<script>alert("x")</script>', false);
        }
    }

    public function test_empty_or_missing_outlet_has_zero_totals_and_header_only_csv(): void
    {
        $this->fixture();
        $this->withSession(['outlet_id' => null]);
        $this->get('/workspace')->assertOk()->assertViewHas('metrics', fn ($m) => array_sum($m) === 0);
        $this->get('/reports')->assertOk()->assertSee('Pilih outlet aktif')->assertViewHas('bookings', fn ($rows) => $rows->total() === 0);
        $this->assertCount(1, $this->csvRows());
    }

    public function test_inactive_user_cannot_download_csv(): void
    {
        $f = $this->fixture();
        $f['user']->update(['status' => 'inactive']);
        $this->get('/reports/csv')->assertRedirect('/login');
    }

    public function test_english_headers_print_and_analytics_render_for_admin(): void
    {
        $this->fixture(true);
        $this->withSession(['locale' => 'en']);
        $rows = $this->csvRows();
        $this->assertSame('Guest name', $rows[0][3]);
        $this->get('/reports/print')->assertOk()->assertSee('Reservation report')->assertSee('Generated at');
        $this->get('/analytics')->assertOk()->assertSee('Daily trend')->assertSee('<title>Analytics · DineFlow</title>', false);
        $this->assertSame(array_keys(trans('report', [], 'ms')), array_keys(trans('report', [], 'en')));
    }

    public function test_sort_ties_use_booking_id_as_a_stable_tiebreaker(): void
    {
        $this->fixture();
        $this->get('/reports?sort=dining_date&direction=asc')->assertOk()->assertViewHas('bookings', fn ($rows) => $rows->pluck('booking_code')->all() === ['DF-2026-0001', 'DF-2026-0002', 'DF-2026-0003']);
    }

    public function test_csv_stream_continues_beyond_its_first_database_batch(): void
    {
        $f = $this->fixture();
        Booking::factory()->count(251)->create(['outlet_id' => $f['outlet']->getKey(), 'table_id' => $f['tables'][0]->getKey(), 'created_by' => $f['user']->getKey(), 'payment_method_id' => $f['payment']->getKey(), 'pax' => 1]);

        $rows = $this->csvRows('/reports/csv?sort=booking_code&direction=asc');
        array_shift($rows);
        $this->assertCount(254, $rows);
        $this->assertCount(254, array_unique(array_column($rows, 1)));
        $this->assertSame(271, array_sum(array_column($rows, 6)));
    }
}
