@extends('layouts.app')
@section('content')
<section class="page-heading"><div><p class="eyebrow">P2 / ACCOUNT</p><h1><x-adminword key="change_password" /></h1></div></section><section class="panel form-panel"><form method="POST" class="admin-form" action="{{ route('password.update') }}">@csrf @method('PUT')<div class="form-grid"><x-field name="current_password" type="password" required /><x-field name="password" type="password" required /><x-field name="password_confirmation" type="password" required /></div><p class="draft-note"><x-adminword key="password_note" /></p><button class="button primary"><x-adminword key="save" /></button></form></section>
@endsection
