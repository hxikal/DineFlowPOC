@php
$pageKey = match(true) {
    request()->routeIs('workspace') => 'dashboard',
    request()->routeIs('reports.*') => 'reports',
    request()->routeIs('analytics') => 'analytics',
    request()->routeIs('bookings.create') => 'new_booking',
    request()->routeIs('bookings.edit') => 'edit_booking',
    request()->routeIs('bookings.show') => 'booking_detail',
    request()->routeIs('bookings.*') => 'bookings',
    request()->routeIs('table-board') => 'table_board',
    request()->routeIs('users.*') => 'users',
    request()->routeIs('configuration.*') => request()->route('type'),
    request()->routeIs('audit.index') => 'audit',
    request()->routeIs('password.*') => 'change_password',
    default => 'workspace',
};
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ __( (in_array($pageKey,['dashboard','reports','analytics']) ? 'report.' : 'admin.').$pageKey) }} · DineFlow</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body data-locale-url="{{ route('locale') }}" class="admin-body" data-page-key="{{ $pageKey }}">
<a href="#main" class="skip-link">{{ __('ui.skip') }}</a>
<aside class="sidebar" id="sidebar"><a class="brand" href="{{ route('workspace') }}"><span class="brand-mark">d<span>.</span></span><span>DineFlow<small><x-adminword key="environment" /></small></span></a><p class="nav-caption"><x-adminword key="workspace" /></p><nav>
<a class="nav-link {{ request()->routeIs('workspace') ? 'active' : '' }}" href="{{ route('workspace') }}"><x-icon /><x-reportword key="dashboard" /></a>
<a class="nav-link {{ request()->routeIs('bookings.*') ? 'active' : '' }}" href="{{ route('bookings.index') }}"><x-icon name="calendar" /><x-bookword key="bookings" /></a>
<a class="nav-link {{ request()->routeIs('table-board') ? 'active' : '' }}" href="{{ route('table-board') }}"><x-icon name="table" /><x-bookword key="table_board" /></a>
<a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><x-icon name="calendar" /><x-reportword key="reports" /></a>
<a class="nav-link {{ request()->routeIs('analytics') ? 'active' : '' }}" href="{{ route('analytics') }}"><x-icon /><x-reportword key="analytics" /></a>
@can('administer')
<a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><x-icon name="users" /><x-adminword key="users" /></a>
@foreach(['outlets','sections','tables','payments'] as $type)<a class="nav-link {{ request()->route('type') === $type ? 'active' : '' }}" href="{{ route('configuration.index',$type) }}"><x-icon name="table" /><x-adminword :key="$type" /></a>@endforeach
<a class="nav-link {{ request()->routeIs('audit.index') ? 'active' : '' }}" href="{{ route('audit.index') }}"><x-icon name="calendar" /><x-adminword key="audit" /></a>
@endcan
<a class="nav-link {{ request()->routeIs('password.*') ? 'active' : '' }}" href="{{ route('password.edit') }}"><x-adminword key="change_password" /></a>
</nav><form method="POST" action="{{ route('logout') }}" class="sidebar-bottom">@csrf<button class="text-button"><x-adminword key="logout" /></button></form></aside>
<div class="shell"><header class="topbar"><button id="menu-toggle" class="icon-button mobile-menu" aria-controls="sidebar" aria-expanded="false" aria-label="Menu"><x-icon name="menu" /></button><span class="breadcrumb">DineFlow</span><div class="topbar-right"><div class="languages" role="group" aria-label="{{ __('admin.language') }}">@foreach(['ms'=>'BM','en'=>'EN'] as $locale=>$label)<button type="button" data-locale="{{ $locale }}" aria-pressed="{{ app()->getLocale()===$locale ? 'true':'false' }}">{{ $label }}</button>@endforeach</div><div class="profile"><span class="avatar">DF</span><span><strong>{{ auth()->user()->name }}</strong><small><x-adminword :key="auth()->user()->role" /></small></span></div></div></header>
<main id="main" tabindex="-1">
<div id="language-error" class="alert" role="alert" hidden></div>
@if(session('success'))<div class="success-message" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert" role="alert"><strong><x-adminword key="errors" /></strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if(request()->routeIs('bookings.create','bookings.edit'))<div class="context-bar"><strong>{{ $outlet->outlet_name }}</strong><span class="muted"><x-bookword key="form_context" /></span></div>@else
<div class="context-bar admin-context"><form method="POST" action="{{ route('context') }}">@csrf<label class="context-field"><x-adminword key="outlets" /><select name="outlet_id" required><option value="" data-admin="select_outlet">{{ __('admin.select_outlet') }}</option>@foreach($activeOutlets as $item)<option value="{{ $item->getKey() }}" @selected((int) session('outlet_id')===$item->getKey())>{{ $item->outlet_name }}</option>@endforeach</select></label><label class="context-field"><x-adminword key="dining_date" /><input type="date" name="dining_date" value="{{ session('dining_date',now()->toDateString()) }}"></label><button class="button" type="submit"><x-adminword key="select" /></button></form></div>
@endif
@yield('content')
<footer><x-adminword key="environment" /><span>Asia/Kuala_Lumpur</span></footer></main></div>
<script id="admin-translations" type="application/json">{!! json_encode(['ms'=>trans('admin',[],'ms'),'en'=>trans('admin',[],'en')], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
<script id="booking-translations" type="application/json">{!! json_encode(['ms'=>trans('booking',[],'ms'),'en'=>trans('booking',[],'en')], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
<script id="report-translations" type="application/json">{!! json_encode(['ms'=>trans('report',[],'ms'),'en'=>trans('report',[],'en')], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script></body></html>
