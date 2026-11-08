<?php

use App\Models\User;
use App\Services\Customers;
use App\Services\Operations;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('hive:admin', function () {
    if (User::where('is_admin', true)->exists()) {
        $this->error('An admin already exists. This setup command cannot overwrite it.');

        return 1;
    }
    $name = $this->ask('Admin name');
    $email = $this->ask('Admin email');
    $password = $this->secret('Password (at least 12 characters)');
    $confirmation = $this->secret('Confirm password');
    $data = validator(compact('name', 'email', 'password'), ['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:12'])->validate();
    if (! hash_equals((string) $password, (string) $confirmation)) {
        $this->error('Passwords do not match.');

        return 1;
    }
    app(Operations::class)->transaction(function () use ($data) {
        if (User::where('is_admin', true)->exists()) {
            throw new RuntimeException('Admin already provisioned.');
        }
        $user = new User($data);
        $user->is_admin = true;
        $user->save();
    });
    $this->info('Admin created. Sign in at /login.');
})->purpose('Securely provision the single Hive’s Best administrator');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('hive:backfill-customers', function () {
    $this->line(json_encode(app(Customers::class)->backfill(), JSON_PRETTY_PRINT));
})->purpose('Link unlinked orders to conservatively matched customer profiles');
