<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PermissionsController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\UsersController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return ['message' => 'API is working...'];
});

Route::post('permission/generate', [PermissionsController::class, 'generate']);
// Route::post('roles/save', [RolesController::class, 'save']);
// Route::post('roles/assign', [RolesController::class, 'assign']);
// Route::post('roles/unassign', [RolesController::class, 'unassign']);
// Route::post('users/assign', [UsersController::class, 'assign']);

Route::group(['prefix' => 'auth'], function () {
	Route::post('signup', [AuthController::class, 'signup']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');;



});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::middleware(['auth:sanctum', 'checkPermission'])->group(function () {
    Route::match(['get', 'post'], '/{controllerName}/{methodName?}/{id?}', [RouteController::class, 'initiate']);
});

// Route::group(['middleware' => 'auth:sanctum'], function () {
//     Route::group(['middleware' => 'checkPermission'], function () {
//         Route::get('{controllerName}/{methodName?}/{id?}', [RouteController::class, 'initiate']);
//         Route::post('{controllerName}/{methodName?}/{id?}', [RouteController::class, 'initiate']);
//     });
// });
