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
// Route::get('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);
Route::post('/example', [App\Http\Controllers\Api\KaskadController::class, 'example']);

Route::group(['middleware' => ['auth.api_token']], function () {
    Route::prefix('bitrix24')->group(function () {
        Route::post('/setup', [App\Http\Controllers\Api\KaskadController::class, 'setupBitrix']);
        Route::post('/update/contact', [App\Http\Controllers\Api\KaskadController::class, 'updateContactRequest']);
        Route::post('/update/visit', [App\Http\Controllers\Api\KaskadController::class, 'updateVisit']);
        Route::post('/update/sug-visit', [App\Http\Controllers\Api\KaskadController::class, 'updateSuggestedVisitRequest']);
        Route::post('/update/doctors', [App\Http\Controllers\Api\KaskadController::class, 'updateDoctorRequest']);
        Route::post('/update/speciality', [App\Http\Controllers\Api\KaskadController::class, 'updateSpecialityRequest']);
        Route::post('/update/cabinet', [App\Http\Controllers\Api\KaskadController::class, 'updateCabinetRequest']);
        Route::post('/update/service', [App\Http\Controllers\Api\KaskadController::class, 'updateServiceRequest']);
    });
});
