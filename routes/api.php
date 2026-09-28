<?php

use App\Http\Controllers\Api\SensorController;
use Illuminate\Support\Facades\Route;

Route::post('/sensores/ocupacion', [SensorController::class, 'registrarOcupacion'])->middleware(['auth:sanctum', \App\Http\Middleware\RequireStaffAccess::class]);

Route::get('/espacios/estado', [SensorController::class, 'estadoActual'])->middleware(['auth:sanctum', \App\Http\Middleware\RequireStaffAccess::class]);

Route::post('/iot/sensores/{sensor:codigo_sensor}/lecturas', [\App\Http\Controllers\Api\LecturaSensorController::class, 'store'])->middleware('throttle:sensor-readings')->name('iot.lecturas');
