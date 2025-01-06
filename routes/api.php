<?php

use App\Http\Controllers\V1\ActivationLicenseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::prefix('v1')->group(function () {
    Route::prefix('activationlicensecontroller')->group(function () {
        Route::controller(ActivationLicenseController::class)->group(function () {
            Route::post('/activate', 'activate');
            Route::get('/activate', 'activate');
        });
    });
});
