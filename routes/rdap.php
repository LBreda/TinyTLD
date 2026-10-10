<?php

use App\Http\Controllers\RdapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RDAP routes
|--------------------------------------------------------------------------
|
| Loaded from bootstrap/app.php with the "rdap" prefix and "rdap." name
| prefix, outside the "web" middleware group (no session, no CSRF).
|
*/

Route::get('domain/{name}', [RdapController::class, 'domain'])->name('domain');
Route::get('nameserver/{name}', [RdapController::class, 'nameserver'])->name('nameserver');
Route::get('entity/{handle}', [RdapController::class, 'entity'])->name('entity');
Route::get('domains', [RdapController::class, 'domains'])->name('domains');
Route::get('nameservers', [RdapController::class, 'nameservers'])->name('nameservers');
Route::get('entities', [RdapController::class, 'entities'])->name('entities');
Route::get('help', [RdapController::class, 'help'])->name('help');
Route::get('{any}', [RdapController::class, 'fallback'])->where('any', '.*')->name('fallback');
