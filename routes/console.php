<?php

use App\Models\Admin;
use App\Services\DisposableEmailImporter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:create {email}', function () {
    $email = (string) $this->argument('email');

    if (Admin::where('email', $email)->exists()) {
        $this->error('An admin with that email already exists.');
        return;
    }

    $name = $this->ask('Name (optional)') ?: null;
    $password = $this->secret('Password (min 10 chars, must contain letters + digits)');
    $confirm = $this->secret('Confirm password');

    if (! $password || $password !== $confirm) {
        $this->error('Passwords do not match.');
        return;
    }

    Admin::create([
        'name' => $name,
        'email' => $email,
        'password' => $password,
    ]);

    $this->info('Admin created. 2FA setup will be required on first login.');
})->purpose('Create a new admin account');

Artisan::command('disposable:refresh', function () {
    $url = config('services.disposable_domains.url');
    $source = config('services.disposable_domains.source');

    $count = app(DisposableEmailImporter::class)->importFromUrl($url, $source);

    $this->info("Imported {$count} disposable email domains.");
})->purpose('Refresh disposable email domain list');
