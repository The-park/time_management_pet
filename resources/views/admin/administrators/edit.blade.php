@extends('layouts.admin')

@section('page_title', 'Edit admin')

@section('content')
    <div class="mb-6 text-xs uppercase tracking-[0.2em] text-slate-500">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-slate-300">Admin</a>
        <span class="mx-1.5 text-slate-700">/</span>
        <a href="{{ route('admin.administrators.index') }}" class="hover:text-slate-300">Admins</a>
        <span class="mx-1.5 text-slate-700">/</span>
        <span class="text-slate-300">Edit</span>
    </div>

    <h1 class="font-display text-2xl md:text-3xl tracking-[0.2em] uppercase text-slate-100">
        Edit {{ $isSelf ? 'your account' : 'admin' }}
    </h1>
    <p class="text-sm text-slate-400 mt-1 mb-6">
        @if ($isSelf)
            You're editing your own account. Leave password fields blank to keep your current password.
        @else
            Leave password fields blank to keep the current password unchanged.
        @endif
    </p>

    <form method="POST" action="{{ route('admin.administrators.update', $admin->id) }}" class="space-y-4 max-w-lg">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1.5" for="name">Name</label>
            <input id="name" name="name" type="text" required maxlength="255"
                value="{{ old('name', $admin->name) }}"
                class="w-full rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-slate-100 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
            @error('name')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1.5" for="email">Email</label>
            <input id="email" name="email" type="email" required maxlength="255" autocomplete="email"
                value="{{ old('email', $admin->email) }}"
                class="w-full rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-slate-100 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
            @error('email')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
        </div>

        <div class="rounded-lg border border-slate-800/60 bg-slate-950/30 p-4 space-y-3">
            <div class="text-[0.65rem] uppercase tracking-[0.2em] text-slate-500">Change password (optional)</div>
            <div>
                <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1.5" for="password">New password</label>
                <input id="password" name="password" type="password" autocomplete="new-password"
                    class="w-full rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-slate-100 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
                @error('password')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                <p class="mt-1 text-[0.65rem] text-slate-500">Min 10 chars, letters + digits. Leave blank to keep current.</p>
            </div>
            <div>
                <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1.5" for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                    class="w-full rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-slate-100 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
            </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit"
                class="rounded-lg bg-rose-500 hover:bg-rose-400 text-white font-semibold px-4 py-2 text-sm transition-colors">
                Save changes
            </button>
            <a href="{{ route('admin.administrators.index') }}" class="text-sm text-slate-400 hover:text-slate-200">Cancel</a>
        </div>
    </form>
@endsection
