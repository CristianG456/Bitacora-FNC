<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CasoController;
use App\Http\Controllers\ProfileController;

//  LOGIN
Route::get('/', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');

//  LOGOUT
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

//  RUTAS PROTEGIDAS
Route::middleware('auth')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // CASOS
    Route::get('/casos/crear', [CasoController::class, 'crear'])
        ->name('casos.crear');

    Route::post('/casos', [CasoController::class, 'guardar'])
        ->name('casos.guardar');

    // PERFIL
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

});