<?php

use App\Http\Controllers\Api\V1\VehicleGpsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('v1')->name('api.v1.')->group(function () {
    Route::post('vehicles/{vehicle}/gps', [VehicleGpsController::class, 'store'])->name('vehicles.gps.store');
});
