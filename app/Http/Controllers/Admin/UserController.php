<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();

        return view('admin.users.index', [
            'users' => User::query()
                ->when(! $actor->isOwner(), fn ($query) => $query->where(function ($query) use ($actor): void {
                    $query->whereKey($actor->id)->orWhere('admin_id', $actor->id);
                }))
                ->orderBy('role')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        if ($actor->isAdmin()) {
            $request->merge(['role' => $request->input('role', 'operator')]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => [
                'required',
                Rule::in($actor->isOwner() ? ['admin', 'temp_admin'] : ['operator']),
            ],
            'password' => ['nullable', 'string', 'confirmed', 'min:12'],
        ]);

        $newUser = DB::transaction(function () use ($validated, $actor): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => isset($validated['password'])
                    ? Hash::make($validated['password'])
                    : Str::random(64),
                'role' => $validated['role'],
                'expires_at' => $validated['role'] === 'temp_admin'
                    ? now('Asia/Yangon')->addDays(7)
                    : null,
                'status' => true,
                'admin_id' => $actor->isAdmin() ? $actor->id : null,
            ]);

            if ($user->isAdmin()) {
                app(InitializeAdminBusiness::class)->initialize($user);
            }

            return $user;
        });

        $label = $newUser->isTempAdmin() ? 'Temp Admin (7-day)' : ucfirst($newUser->role);
        $expiry = $newUser->isTempAdmin()
            ? ', expiring '.$newUser->expires_at->format('Y-m-d H:i')
            : '';

        if (! empty($validated['password'])) {
            return redirect()->route('admin.users.index')
                ->with('status', $label.' account created'.$expiry.'. Share the password with the user through a secure channel.');
        }

        $status = Password::sendResetLink([
            'email' => $newUser->email,
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            report("Could not send {$newUser->role} password setup email: {$status}");

            return back()->withErrors([
                'email' => "The {$newUser->role} account was created, but the password setup email could not be sent. Check the mail configuration and resend the reset link.",
            ]);
        }

        return redirect()->route('admin.users.index')
            ->with('status', $label.' account created'.$expiry.'. A password setup link has been emailed.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isOwner(), 404);
        abort_if($user->isAdmin() && ! $request->user()->isOwner(), 403);
        abort_if(
            $user->isOperator()
            && ! $request->user()->isOwner()
            && $user->admin_id !== $request->user()->id,
            404
        );

        $user->forceFill(['status' => ! $user->status])->save();

        return back()->with(
            'status',
            ucfirst($user->role).' account '.($user->status ? 'activated.' : 'deactivated.')
        );
    }
}
