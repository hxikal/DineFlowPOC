@extends('layouts.app')
@section('content')
<section class="page-heading"><div><p class="eyebrow">P2 / DineFlow</p><h1><x-adminword key="setup_title" /></h1><p class="muted"><x-adminword key="setup_note" /></p></div></section>
<section class="panel form-panel"><h2>{{ $outlet?->outlet_name ?? __('admin.select_outlet') }}</h2><p class="muted spaced"><x-adminword :key="$outlet ? 'all_outlets' : 'no_outlet'" /></p></section>
@can('administer')<section class="stats spaced">@foreach(['users','outlets','sections','tables'] as $page)<a class="stat" href="{{ $page==='users' ? route('users.index') : route('configuration.index',$page) }}"><span class="stat-icon"><x-icon name="{{ $page==='users' ? 'users':'table' }}" /></span><h2><x-adminword :key="$page" /></h2><p class="spaced"><x-adminword key="edit" /> →</p></a>@endforeach</section>@endcan
@endsection
