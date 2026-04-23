<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CasoController;
use App\Http\Controllers\TareaController;
use App\Http\Controllers\UserController;

// ─────────────────────────────────────────────────────────────────
//  AUTENTICACIÓN (públicas)
// ─────────────────────────────────────────────────────────────────

Route::get('/', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ─────────────────────────────────────────────────────────────────
//  RUTAS PROTEGIDAS (requieren autenticación)
// ─────────────────────────────────────────────────────────────────

Route::middleware(['auth'])->group(function () {

    // Dashboard (todos los roles autenticados)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Usuarios API
    Route::get('/usuarios/buscar', [UserController::class, 'buscar'])->name('usuarios.buscar');

    // ─── CASOS ────────────────────────────────────────────────────

    // Listado de casos
    Route::get('/casos', [CasoController::class, 'index'])->name('casos.index');

    // Solo Administrador y Jurídica pueden crear casos
    Route::middleware(['role:Administrador,Juridica'])->group(function () {
        Route::get('/casos/crear', [CasoController::class, 'crear'])->name('casos.crear');
        Route::post('/casos', [CasoController::class, 'guardar'])->name('casos.guardar');
    });

    // Detalles del caso (Debe ir DESPUÉS de casos/crear para evitar 404)
    Route::get('/casos/{caso}', [CasoController::class, 'show'])->name('casos.show');

    // ─── TAREAS (dentro de un caso) ───────────────────────────────

    // Ver lista de tareas del caso (todos los asignados + admin/jurídica)
    Route::get('/casos/{caso}/tareas', [TareaController::class, 'index'])
        ->name('tareas.index');

    // Ver detalle de una tarea
    Route::get('/casos/{caso}/tareas/{tarea}', [TareaController::class, 'ver'])
        ->name('tareas.ver');

    // Agregar observación a una tarea (asignados + admin)
    Route::post('/casos/{caso}/tareas/{tarea}/observaciones', [TareaController::class, 'agregarObservacion'])
        ->name('tareas.observacion');

    // Cambio rápido de estado
    Route::post('/casos/{caso}/tareas/{tarea}/estado', [TareaController::class, 'cambiarEstado'])
        ->name('tareas.estado');

    // Crear, editar y eliminar tareas (solo Administrador y Jurídica)
    Route::middleware(['role:Administrador,Juridica'])->group(function () {
        Route::get('/casos/{caso}/tareas/crear', [TareaController::class, 'crear'])
            ->name('tareas.crear');
        Route::post('/casos/{caso}/tareas', [TareaController::class, 'guardar'])
            ->name('tareas.guardar');
        Route::get('/casos/{caso}/tareas/{tarea}/editar', [TareaController::class, 'editar'])
            ->name('tareas.editar');
        Route::put('/casos/{caso}/tareas/{tarea}', [TareaController::class, 'actualizar'])
            ->name('tareas.actualizar');
        Route::delete('/casos/{caso}/tareas/{tarea}', [TareaController::class, 'eliminar'])
            ->name('tareas.eliminar');
    });

});