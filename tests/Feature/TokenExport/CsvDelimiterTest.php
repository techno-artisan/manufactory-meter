<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('exportiert mit Komma als Trennzeichen', function (): void {
    $pfad = str_replace('\\', '/', sys_get_temp_dir()).'/'.uniqid('export-', true).'.csv';

    Artisan::call('tokens:export '.$pfad);
    $ersteZeile = (string) strtok((string) file_get_contents($pfad), "\r\n");
    unlink($pfad);

    expect($ersteZeile)->toContain(',')->not->toContain(';');
})->skip(fn (): bool => ! array_key_exists('tokens:export', Artisan::all()), 'Befehl fehlt noch');
