@extends('layouts.app', ['title' => 'User access'])

@section('content')
<main class="main">
    <div class="between">
        <div>
            <div class="eyebrow">Administration</div>
            <h1 class="page-title" style="margin-top:8px">User access</h1>
            <p class="subtitle">Manage accounts and their access.</p>
        </div>
        <a class="button secondary" href="{{ route('dashboard') }}">Dashboard</a>
    </div>

    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif

    <section class="card" style="margin-bottom:20px">
        <h2>Create an account</h2>
        <p class="muted">
            @if(auth()->user()->isOwner())
                Create an Admin account, or a <strong>Temp Admin (7-day)</strong> account for temporary field testing — it behaves like an Admin but automatically expires 7 days after creation. Only Admins can create Operators; Operators are always linked to the Admin who created them.
            @else
                Create an Operator account under your Admin.
            @endif
            Set an initial password below and share it with the user through a secure channel. They should change it after first sign-in.
        </p>
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="grid">
                <div>
                    <label for="name">Full name</label>
                    <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required>
                </div>
                <div>
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                </div>
                @if(auth()->user()->isOwner())
                    <div>
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <option value="admin" selected>Admin</option>
                            <option value="temp_admin">Temp Admin (7-day)</option>
                        </select>
                    </div>
                @else
                    <input type="hidden" name="role" value="operator">
                @endif
                <div>
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required minlength="12">
                    <span class="muted" style="font-size:12px">At least 12 characters.</span>
                </div>
                <div>
                    <label for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="12">
                </div>
            </div>
            <button class="button" style="margin-top:18px" type="submit">Create account</button>
        </form>
    </section>

    <section class="card">
        <h2>Accounts</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            {{ $user->isTempAdmin() ? 'Temp Admin' : ucfirst($user->role) }}
                            @if($user->expires_at)
                                <small class="muted" style="display:block">{{ $user->isExpired() ? 'Expired' : 'Expires' }} {{ $user->expires_at->format('Y-m-d H:i') }}</small>
                            @endif
                        </td>
                        <td><span class="pill {{ $user->isExpired() || ! $user->status ? 'off' : '' }}">{{ $user->isExpired() ? 'Expired' : ($user->status ? 'Active' : 'Inactive') }}</span></td>
                        <td>
                            @if(!$user->isOwner() && ($user->isOperator() || auth()->user()->isOwner()))
                                <form method="POST" action="{{ route('admin.users.status', $user) }}" class="inline-form">
                                    @csrf
                                    @method('PATCH')
                                    <button class="button {{ $user->status ? 'danger' : 'secondary' }}" type="submit">
                                        {{ $user->status ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            @else
                                <span class="muted">{{ $user->isOwner() ? 'Owner protected' : 'Owner only' }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No user accounts found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:18px">{{ $users->links() }}</div>
    </section>
</main>
@endsection
