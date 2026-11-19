<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestLoginSeeder;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('zeigt die Anmeldeseite des Panels', function () {
    $this->get('/admin/login')->assertOk();
});

it('leitet ohne Anmeldung von /admin zur Anmeldeseite', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('meldet den Benutzer aus dem Seeder an und zeigt /admin', function () {
    $this->seed(TestLoginSeeder::class);

    Livewire::test(Login::class)
        ->fillForm(['email' => 'admin@example.test', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs(User::where('email', 'admin@example.test')->firstOrFail());

    // Filament lässt Benutzer ohne FilamentUser-Vertrag nur bei config('app.env') === 'local' ins Panel.
    config(['app.env' => 'local']);
    $this->get('/admin')->assertOk();
});

it('lehnt ein falsches Passwort ab', function () {
    $this->seed(TestLoginSeeder::class);

    Livewire::test(Login::class)
        ->fillForm(['email' => 'admin@example.test', 'password' => 'falsch'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('legt den Benutzer bei zwei Seeder-Läufen nur einmal an', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'admin@example.test')->count())->toBe(1);
});

it('legt in production keinen Benutzer an', function () {
    app()->detectEnvironment(fn () => 'production');

    // In production verlangt db:seed --force, sonst fragt der Befehl nach.
    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

    expect(User::count())->toBe(0);
});
