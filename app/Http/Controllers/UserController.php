<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\UserManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users', ['users' => User::orderBy('user_id')->paginate(20), 'editing' => null]);
    }

    public function edit(int $user): View
    {
        return view('admin.users', ['users' => User::orderBy('user_id')->paginate(20), 'editing' => User::findOrFail($user)]);
    }

    public function store(UserRequest $request, UserManager $manager): RedirectResponse
    {
        $manager->save($request->user(), null, $request->validated());

        return redirect()->route('users.index')->with('success', __('admin.saved'));
    }

    public function update(UserRequest $request, int $user, UserManager $manager): RedirectResponse
    {
        $manager->save($request->user(), $user, $request->validated());

        return redirect()->route('users.index')->with('success', __('admin.saved'));
    }

    public function reset(Request $request, int $user, UserManager $manager): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:12', 'max:255', 'confirmed']]);
        $manager->save($request->user(), $user, $data);

        return redirect()->route('users.edit', $user)->with('success', __('admin.saved'));
    }
}
