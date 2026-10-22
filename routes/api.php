<?php

use App\Http\Controllers\Api\Admin\AdhesionLinkController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\DossierController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PublicAdhesionController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/links/{code}', [PublicAdhesionController::class, 'showLink']);
    Route::post('/links/{code}/dossiers', [PublicAdhesionController::class, 'storeDossier']);
});

Route::get('/admin/dossiers/{dossier}/documents/{document}', [DossierController::class, 'document'])
    ->middleware(['auth:sanctum', 'permission:documents.view', 'throttle:120,1'])
    ->name('api.admin.dossiers.documents.show');

Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::prefix('admin')->group(function () {
        Route::middleware('permission:dashboard.view')->get('/dashboard', DashboardController::class);
        Route::middleware('permission:adhesions.view')->group(function () {
            Route::get('/dossiers', [DossierController::class, 'index']);
            Route::get('/dossiers/export', [DossierController::class, 'export'])->middleware('permission:adhesions.export');
            Route::get('/dossiers/{dossier}', [DossierController::class, 'show']);
        });
        Route::patch('/dossiers/{dossier}', [DossierController::class, 'update'])->middleware(['permission:adhesions.view', 'permission:adhesions.update']);
        Route::delete('/dossiers/{dossier}', [DossierController::class, 'destroy'])->middleware(['permission:adhesions.view', 'permission:adhesions.delete']);
        Route::middleware('permission:links.view')->group(function () {
            Route::get('/links', [AdhesionLinkController::class, 'index']);
            Route::post('/links', [AdhesionLinkController::class, 'store'])->middleware('permission:links.create');
            Route::patch('/links/{adhesionLink}', [AdhesionLinkController::class, 'update'])->middleware('permission:links.update');
        });
        Route::middleware('permission:users.manage')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::patch('/users/{user}', [UserController::class, 'update']);
            Route::delete('/users/{user}', [UserController::class, 'destroy']);
        });
    });
});
