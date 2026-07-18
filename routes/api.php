<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SensorController;

Route::post('/sensores/ocupacion', [SensorController::class, 'registrarOcupacion']);

Route::get('/espacios/estado', [SensorController::class, 'estadoActual']);