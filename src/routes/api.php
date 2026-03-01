<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\{DashboardController, FormController, FileUploadController, FormDraftController, FormResponseController};
use App\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->group(function () {    
    Route::get('/user', function (Request $request) {
        return response()->json(new UserResource($request->user()));
    });

	Route::prefix('v1')->group(function () {
        Route::get('/dashboard', DashboardController::class);
        Route::patch('/forms/{form}/response-acceptance', [FormController::class, 'responseAcceptance']);
        Route::apiResource('forms', FormController::class);
        Route::apiResource('drafts', FormDraftController::class);
        
        // Custom route should be define before the apiResource to avoid conflict
        Route::get('/forms/{form}/responses/results', [FormResponseController::class, 'showResults']);
        Route::apiResource('forms.responses', FormResponseController::class);
    });
});

Route::prefix('v1')->group(function () {
    Route::get('/forms/{form:slug}/public', [FormController::class, 'viewFormPublic']);

    Route::post('/uploads', [FileUploadController::class, 'uploads']);
    Route::delete('/revert/{folder}', [FileUploadController::class, 'revert']);
});