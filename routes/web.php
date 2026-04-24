<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CasoController;
use App\Http\Controllers\TareaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HistorialController;

//  AUTENTICACIÓN (públicas)


Route::get('/', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');


//  RUTAS PROTEGIDAS (requieren autenticación)


Route::middleware(['auth'])->group(function () {

    // Dashboard (todos los roles autenticados)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Usuarios API (búsqueda)
    Route::get('/usuarios/buscar', [UserController::class, 'buscar'])->name('usuarios.buscar');

    //  USUARIOS (Solo Administrador) 
    Route::middleware(['role:Administrador'])->group(function () {
        Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index');
        Route::get('/usuarios/crear', [UserController::class, 'crear'])->name('usuarios.crear');
        Route::post('/usuarios', [UserController::class, 'guardar'])->name('usuarios.guardar');
        Route::get('/usuarios/{usuario}/editar', [UserController::class, 'editar'])->name('usuarios.editar');
        Route::put('/usuarios/{usuario}', [UserController::class, 'actualizar'])->name('usuarios.actualizar');
        Route::post('/usuarios/{usuario}/estado', [UserController::class, 'cambiarEstado'])->name('usuarios.estado');
    });

    //  CASOS 

    // Listado de casos
    Route::get('/casos', [CasoController::class, 'index'])->name('casos.index');

    // Solo Administrador y Jurídica pueden crear casos
    Route::middleware(['role:Administrador,Juridica'])->group(function () {
        Route::get('/casos/crear', [CasoController::class, 'crear'])->name('casos.crear');
        Route::post('/casos', [CasoController::class, 'guardar'])->name('casos.guardar');
    });

    // Detalles del caso (Debe ir DESPUÉS de casos/crear para evitar 404)
    Route::get('/casos/{caso}', [CasoController::class, 'show'])->name('casos.show');

    // Chat del caso
    Route::post('/casos/{caso}/mensajes', [CasoController::class, 'enviarMensaje'])->name('casos.mensajes');

    // Gestión de usuarios asignados al caso
    Route::middleware(['role:Administrador,Juridica'])->group(function () {
        Route::post('/casos/{caso}/usuarios', [CasoController::class, 'asignarUsuario'])->name('casos.usuarios.asignar');
        Route::delete('/casos/{caso}/usuarios/{usuario}', [CasoController::class, 'removerUsuario'])->name('casos.usuarios.remover');
        Route::post('/casos/{caso}/usuarios/{usuario}/reemplazar', [CasoController::class, 'reemplazarUsuario'])->name('casos.usuarios.reemplazar');
    });

    // Crear y eliminar tareas (solo Administrador y Jurídica)
    Route::middleware(['role:Administrador,Juridica'])->group(function () {
        Route::post('/casos/{caso}/tareas', [TareaController::class, 'guardar'])
            ->name('tareas.guardar');
        Route::delete('/casos/{caso}/tareas/{tarea}', [TareaController::class, 'eliminar'])
            ->name('tareas.eliminar');
    });

    // HISTORIAL GLOBAL (Solo Administrador y Juridica)
    Route::middleware(['role:Administrador,Juridica'])->group(function () {
        Route::get('/historial', [HistorialController::class, 'index'])->name('historial.index');
        Route::get('/historial/exportar/excel', [HistorialController::class, 'exportarExcel'])->name('historial.exportar.excel');
        Route::get('/historial/exportar/pdf', [HistorialController::class, 'exportarPdf'])->name('historial.exportar.pdf');
    });

});