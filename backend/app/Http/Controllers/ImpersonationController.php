<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function start(User $user): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin !== null && $admin->canWrite(), 403);

        session(['impersonator_admin_id' => $admin->id]);
        Auth::guard('web')->login($user);

        return redirect()->route('dashboard');
    }

    public function stop(): RedirectResponse
    {
        abort_unless(session()->has('impersonator_admin_id'), 403);

        $userId = Auth::guard('web')->id();
        Auth::guard('web')->logout();
        session()->forget('impersonator_admin_id');

        return $userId
            ? redirect()->route('backoffice.users.show', $userId)
            : redirect()->route('backoffice.users.index');
    }
}
