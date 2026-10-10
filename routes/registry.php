<?php

use App\Models\Tld;
use App\Services\ZoneFileGenerator;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Registry back-office
|--------------------------------------------------------------------------
|
| Admins manage every object. Registrar operators can only browse the
| domains sponsored by their registrar, the related contacts, and edit
| their own registrar profile. Fine-grained checks live in the policies.
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('domains', 'pages::domains.index')->name('domains.index');
    Route::livewire('domains/create', 'pages::domains.form')->name('domains.create')->middleware('can:admin');
    Route::livewire('domains/{domain}', 'pages::domains.show')->name('domains.show');
    Route::livewire('domains/{domain}/edit', 'pages::domains.form')->name('domains.edit')->middleware('can:admin');

    Route::livewire('contacts', 'pages::contacts.index')->name('contacts.index');
    Route::livewire('contacts/create', 'pages::contacts.form')->name('contacts.create')->middleware('can:admin');
    Route::livewire('contacts/{contact}', 'pages::contacts.show')->name('contacts.show');
    Route::livewire('contacts/{contact}/edit', 'pages::contacts.form')->name('contacts.edit')->middleware('can:admin');

    Route::get('registrar', function () {
        $registrar = auth()->user()->registrar;

        abort_if($registrar === null, 404);

        return redirect()->route('registrars.edit', $registrar);
    })->name('registrars.mine');

    Route::livewire('registrars/{registrar}/edit', 'pages::registrars.form')->name('registrars.edit');

    Route::middleware('can:admin')->group(function () {
        Route::livewire('registrars', 'pages::registrars.index')->name('registrars.index');
        Route::livewire('registrars/create', 'pages::registrars.form')->name('registrars.create');

        Route::livewire('tlds', 'pages::tlds.index')->name('tlds.index');
        Route::livewire('tlds/create', 'pages::tlds.form')->name('tlds.create');
        Route::livewire('tlds/{tld}/edit', 'pages::tlds.form')->name('tlds.edit');
        Route::get('tlds/{tld}/zone', function (Tld $tld, ZoneFileGenerator $generator) {
            return response($generator->generate($tld), 200, [
                'Content-Type' => 'text/dns; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="'.$tld->name.'.zone"',
            ]);
        })->name('tlds.zone');

        Route::livewire('nameservers', 'pages::nameservers.index')->name('nameservers.index');
        Route::livewire('nameservers/create', 'pages::nameservers.form')->name('nameservers.create');
        Route::livewire('nameservers/{nameserver}/edit', 'pages::nameservers.form')->name('nameservers.edit');

        Route::livewire('users', 'pages::users.index')->name('users.index');
        Route::livewire('users/create', 'pages::users.form')->name('users.create');
        Route::livewire('users/{user}/edit', 'pages::users.form')->name('users.edit');
    });
});
