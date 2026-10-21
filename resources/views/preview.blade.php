<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="DineFlow P1 application preview with sample data">
    <title>{{ __('ui.'.$screen) }} · DineFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-screen="{{ $screen }}" data-today="{{ now()->toDateString() }}" data-locale-url="{{ route('locale') }}">
<a class="skip-link" href="#main"><x-word key="skip" /></a>
<aside class="sidebar" id="sidebar" aria-label="DineFlow">
    <a class="brand" href="{{ route('preview', 'dashboard') }}"><span class="brand-mark">d<span>.</span></span><span>DineFlow<small><x-word key="subtitle" /></small></span></a>
    <p class="nav-caption"><x-word key="workspace" /></p>
    <nav>
        @foreach(['dashboard' => 'grid', 'booking' => 'plus', 'tables' => 'table'] as $page => $icon)
        <a href="{{ route('preview', $page) }}" @class(['nav-link', 'active' => $screen === $page]) @if($screen === $page) aria-current="page" @endif>
            <x-icon :name="$icon" /><x-word :key="$page" />@if($screen === $page)<span class="nav-dot"></span>@endif
        </a>
        @endforeach
    </nav>
    <div class="sidebar-bottom"><span class="mini-label">P1 / FOUNDATION</span><p><x-word key="all_day" /></p><small><x-word key="whole_day_note" /></small></div>
</aside>
<div class="shell">
    <header class="topbar">
        <button id="menu-toggle" class="icon-button mobile-menu" aria-controls="sidebar" aria-expanded="false" data-i18n-aria="menu" aria-label="{{ __('ui.menu') }}"><x-icon name="menu" /></button>
        <div class="breadcrumb">DineFlow <span>/</span> <strong><x-word :key="$screen" /></strong></div>
        <div class="topbar-right">
            <div class="languages" role="group" data-i18n-aria="language" aria-label="{{ __('ui.language') }}">
                @foreach(['ms' => 'BM', 'en' => 'EN'] as $locale => $label)<button type="button" data-locale="{{ $locale }}" aria-pressed="{{ app()->getLocale() === $locale ? 'true' : 'false' }}">{{ $label }}</button>@endforeach
            </div>
            <span class="topbar-divider"></span>
            <div class="profile"><span class="avatar">DF</span><span><strong><x-word key="profile" /></strong><small><x-word key="role" /></small></span></div>
        </div>
    </header>
    <main id="main" tabindex="-1">
        <div class="demo-banner"><span class="demo-tag"><x-word key="demo" /></span><span><x-word key="demo_notice" /></span></div>
        <div id="language-error" class="alert" role="alert" hidden></div>
        <section class="page-heading">
            <div><p class="eyebrow"><x-word key="today" /></p><h1><x-word :key="$screen === 'dashboard' ? 'welcome' : $screen" /></h1><p class="muted"><x-word :key="match($screen) {'dashboard' => 'welcome_sub', 'booking' => 'booking_intro', default => 'board_intro'}" /></p></div>
            @if($screen === 'dashboard')<a class="button primary" href="{{ route('preview', 'booking') }}"><x-icon name="plus" /><x-word key="new_booking" /></a>@endif
        </section>
        <div class="context-bar">
            <label class="context-field"><span><x-word key="outlet" /></span><select id="outlet"><option value="restaurant" data-i18n="demo_outlet">{{ __('ui.demo_outlet') }}</option><option value="terrace" data-i18n="demo_terrace">{{ __('ui.demo_terrace') }}</option></select></label>
            <label class="context-field"><span><x-word key="date" /></span><input id="dining-date" type="date" value="{{ now()->toDateString() }}" @if($screen === 'booking') min="{{ now()->toDateString() }}" @endif required></label>
            <div class="context-day"><span class="status-dot"></span><x-word key="all_day" /></div>
        </div>

        @if($screen === 'dashboard')
        <section class="stats" aria-label="{{ __('ui.overview') }}" data-i18n-aria="overview">
            @foreach([['total_bookings',3,'calendar'],['total_pax',18,'users'],['occupied',4,'table'],['available',8,'grid']] as [$label,$value,$icon])
            <article class="stat"><span class="stat-icon"><x-icon :name="$icon" /></span><p><x-word :key="$label" /></p><strong>{{ $value }}</strong><small><x-word key="demo" /></small></article>
            @endforeach
        </section>
        <section class="panel reservation-panel"><div class="panel-heading"><div><p class="eyebrow">01 / <x-word key="overview" /></p><h2><x-word key="recent" /></h2></div><a class="text-link" href="{{ route('preview', 'tables') }}"><x-word key="view_tables" /><x-icon name="arrow" /></a></div>
            <div class="table-wrap"><table><thead><tr>@foreach(['guest','code','table','pax','status'] as $label)<th scope="col"><x-word :key="$label" /></th>@endforeach</tr></thead><tbody>
            @foreach($bookings as $booking)<tr><td><span class="guest-avatar">{{ chr(65 + $loop->index) }}</span><strong>{{ $booking['name'] }}</strong></td><td class="muted code">{{ $booking['code'] }}</td><td>{{ $booking['tables'] }}</td><td>{{ $booking['pax'] }}</td><td><span class="badge"><span class="status-dot"></span><x-word key="booked" /></span></td></tr>@endforeach
            </tbody></table></div>
        </section>
        <section class="bottom-note"><span class="note-icon"><x-icon name="table" /></span><div><h2><x-word key="all_day" /></h2><p><x-word key="whole_day_note" /></p></div><a href="{{ route('preview', 'tables') }}" class="icon-button" data-i18n-aria="view_tables" aria-label="{{ __('ui.view_tables') }}"><x-icon name="arrow" /></a></section>
        @else
        @if($screen === 'booking')
        <form id="booking-form" novalidate>
        <div class="booking-layout"><div class="booking-main">
            <section class="panel form-panel"><div class="section-title"><span class="step">01</span><h2><x-word key="guest_details" /></h2></div>
                <div class="form-grid">
                    <label><x-word key="name" /><input id="guest-name" name="name" maxlength="150" autocomplete="off" required data-i18n-placeholder="name_hint" placeholder="{{ __('ui.name_hint') }}"></label>
                    <label><x-word key="contact" /><input id="guest-contact" name="contact" type="tel" maxlength="30" autocomplete="off" required data-i18n-placeholder="contact_hint" placeholder="{{ __('ui.contact_hint') }}"></label>
                    <label><x-word key="payment" /><select id="payment" name="payment" required><option value="" data-i18n="choose_payment">{{ __('ui.choose_payment') }}</option><option value="cash" data-i18n="cash">{{ __('ui.cash') }}</option><option value="card" data-i18n="card">{{ __('ui.card') }}</option></select></label>
                    <label class="full-width"><x-word key="remark" /><textarea id="remark" name="remark" rows="3" maxlength="500" data-i18n-placeholder="remark_hint" placeholder="{{ __('ui.remark_hint') }}"></textarea></label>
                </div>
            </section>
        @endif
            <section class="panel table-panel"><div class="panel-heading"><div class="section-title">@if($screen === 'booking')<span class="step">02</span>@endif<div><h2><x-word :key="$screen === 'booking' ? 'select_tables' : 'tables'" /></h2><p class="muted"><x-word :key="$screen === 'booking' ? 'table_hint' : 'board_note'" /></p></div></div></div>
                <div class="board-toolbar"><strong><x-word key="main_hall" /></strong><div class="legend" data-i18n-aria="legend" aria-label="{{ __('ui.legend') }}"><span><i class="legend-dot free"></i><x-word key="available" /></span><span><i class="legend-dot reserved"></i><x-word key="booked" /></span><span><i class="legend-dot chosen"></i><x-word key="selected" /></span></div></div>
                <div class="table-grid">
                    @for($i=1;$i<=12;$i++)
                    @php($reserved = in_array($i, [1,2,5,8]))
                    <button type="button" @class(['dining-table', 'reserved' => $reserved]) data-table="A{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}" data-reserved="{{ $reserved ? 'true' : 'false' }}" aria-pressed="false"><x-icon name="table" /><strong>A{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}</strong><small data-table-status data-i18n="{{ $reserved ? 'booked' : 'available' }}">{{ __('ui.'.($reserved ? 'booked' : 'available')) }}</small></button>
                    @endfor
                </div>
                <p id="table-detail" class="table-detail" role="status" hidden></p>
            </section>
        @if($screen === 'booking')
        </div><aside class="panel summary-panel"><p class="eyebrow">03 / <x-word key="summary" /></p><h2><x-word key="summary" /></h2><div id="selected-tables" class="selected-tables"></div><p id="no-selection" class="empty-state"><x-word key="no_selection" /></p><div class="summary-total"><span><x-word key="total" /></span><strong id="total-pax">0</strong></div><button class="button primary" type="submit"><x-word key="review" /><x-icon name="arrow" /></button><button id="clear-tables" class="text-button" type="button"><x-word key="clear" /></button><p class="draft-note"><x-word key="draft" /></p><div id="form-error" class="alert" role="alert" hidden></div></aside></div>
        <section id="review" class="panel review" tabindex="-1" hidden><h2><x-word key="review_title" /></h2><p id="review-values"></p><p class="muted"><x-word key="review_note" /></p></section>
        </form>
        @endif
        @endif
        <footer><x-word key="footer" /><span>ms / en · Asia/Kuala_Lumpur</span></footer>
    </main>
</div>
<script id="translations" type="application/json">{!! json_encode(['ms' => trans('ui', [], 'ms'), 'en' => trans('ui', [], 'en')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<noscript><p class="noscript-note">Pratonton interaktif memerlukan JavaScript lokal. / This preview requires local JavaScript.</p></noscript>
</body>
</html>
