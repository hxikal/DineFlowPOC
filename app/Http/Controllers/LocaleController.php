<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocaleController extends Controller
{
    public function __invoke(Request $request, AuditWriter $audit): JsonResponse
    {
        $data = $request->validate(['locale' => 'required|in:ms,en']);
        if ($request->user()) {
            DB::transaction(function () use ($request, $data, $audit) {
                $user = User::whereKey($request->user()->getKey())->lockForUpdate()->firstOrFail();
                abort_unless($user->status === 'active' && (int) $request->session()->get('auth_version', $user->auth_version) === $user->auth_version, 401);
                $before = $user->toArray();
                if ($user->preferred_locale !== $data['locale']) {
                    $user->preferred_locale = $data['locale'];
                    $user->save();
                    $audit->record($user, 'CHANGE_LOCALE', 'user', $user->getKey(), $before, $user->toArray());
                }
            });
        }
        $request->session()->put('locale', $data['locale']);
        app()->setLocale($data['locale']);

        return response()->json(['locale' => $data['locale'], 'messages' => __('ui')]);
    }
}
