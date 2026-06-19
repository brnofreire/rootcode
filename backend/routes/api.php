<?php

use App\Http\Controllers\Api\QuoteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/status', function () {
    return response()->json([
        'status' => 'Conectado com sucesso!',
        'framework' => 'Laravel 12'
    ]);
});

// Motor de cotação de seguro viagem
Route::post('/quotes', [QuoteController::class, 'store']);
