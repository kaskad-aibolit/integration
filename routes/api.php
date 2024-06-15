<?php

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('bitrix24')->group(function () {
    Route::prefix('kaskad')->group(function () {
        // Route::group(['middleware' => ['web']], function () {
            Route::post('/update/contact', [App\Http\Controllers\Api\KaskadController::class, 'updateContact']);
            Route::post('/update/registration', [App\Http\Controllers\Api\KaskadController::class, 'updateRegistration']);
            Route::post('/update/sug-registration', [App\Http\Controllers\Api\KaskadController::class, 'updateSuggestedRegistration']);
            Route::post('/update/doctors', [App\Http\Controllers\Api\KaskadController::class, 'updateDoctors']);
            Route::post('/update/speciality', [App\Http\Controllers\Api\KaskadController::class, 'updateSpeciality']);
            Route::post('/update/cabinet', [App\Http\Controllers\Api\KaskadController::class, 'updateCabinet']);
        // });
      });

});
