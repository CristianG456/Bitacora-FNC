<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CasoController;
use App\Http\Controllers\TareaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\TipoProcesoController;

//  AUTENTICACIÓN (públicas)


Route::middleware(['guest'])->group(function () {
    Route::get('/', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
});

Route::match(['get', 'post'], '/logout', [LoginController::class, 'logout'])->name('logout');


//  RUTAS PROTEGIDAS (requieren autenticación)


Route::middleware(['auth'])->group(function () {

    // Cambio de contraseña obligatorio
    Route::get('/cambiar-password', [\App\Http\Controllers\Auth\PasswordChangeController::class, 'show'])->name('password.change.form');
    Route::post('/cambiar-password', [\App\Http\Controllers\Auth\PasswordChangeController::class, 'update'])->name('password.change.update');

    Route::middleware(['force_password_change'])->group(function () {

    // Dashboard (todos los roles autenticados)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Notificaciones
    Route::get('/notificaciones/recientes', [\App\Http\Controllers\NotificacionController::class, 'getRecientes'])->name('notificaciones.recientes');
    Route::post('/notificaciones/leidas', [\App\Http\Controllers\NotificacionController::class, 'marcarLeidas'])->name('notificaciones.marcar_leidas');

    // Usuarios API (búsqueda)
    Route::get('/usuarios/buscar', [UserController::class, 'buscar'])->name('usuarios.buscar');

    //  USUARIOS (Administrador y Jurídica solo vista)
    Route::middleware(['role:Administrador,Juridica'])->group(function () {
        Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index');
    });

    Route::middleware(['role:Administrador'])->group(function () {
        Route::get('/usuarios/crear', [UserController::class, 'crear'])->name('usuarios.crear');
        Route::post('/usuarios', [UserController::class, 'guardar'])->name('usuarios.guardar');
        Route::get('/usuarios/{usuario}/editar', [UserController::class, 'editar'])->name('usuarios.editar');
        Route::put('/usuarios/{usuario}', [UserController::class, 'actualizar'])->name('usuarios.actualizar');
        Route::post('/usuarios/{usuario}/estado', [UserController::class, 'cambiarEstado'])->name('usuarios.estado');
        Route::delete('/usuarios/{usuario}', [UserController::class, 'eliminar'])->name('usuarios.eliminar');
    });

    //  TIPOS DE PROCESO (Solo Administrador y Juridica)
    Route::middleware(['role:Administrador,Juridica'])->group(function () {
        Route::get('/tipos', [TipoProcesoController::class, 'index'])->name('tipos.index');
        Route::post('/tipos', [TipoProcesoController::class, 'store'])->name('tipos.store');
        Route::put('/tipos/{tipo}', [TipoProcesoController::class, 'update'])->name('tipos.update');
        Route::post('/tipos/{tipo}/estado', [TipoProcesoController::class, 'toggleEstado'])->name('tipos.estado');
        
        Route::post('/tipos/{tipo}/subtipos', [TipoProcesoController::class, 'storeSubtipo'])->name('tipos.subtipos.store');
        Route::put('/tipos/{tipo}/subtipos/{subtipo}', [TipoProcesoController::class, 'updateSubtipo'])->name('tipos.subtipos.update');
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
        Route::post('/casos/{caso}/finalizar', [CasoController::class, 'finalizar'])->name('casos.finalizar');
    });

    // Completar tarea (Cualquier usuario asignado)
    Route::post('/casos/{caso}/tareas/{tarea}/completar', [TareaController::class, 'completar'])
        ->name('tareas.completar');

    // Crear y eliminar tareas (solo Administrador y Jurídica)
    Route::middleware(['role:Administrador,Juridica'])->group(function () {
        Route::post('/casos/{caso}/tareas', [TareaController::class, 'guardar'])
            ->name('tareas.guardar');
        Route::delete('/casos/{caso}/tareas/{tarea}', [TareaController::class, 'eliminar'])
            ->name('tareas.eliminar');
    });

    // HISTORIAL GLOBAL (Solo Administrador, Juridica y Consultor)
    Route::middleware(['role:Administrador,Juridica,Consultor'])->group(function () {
        Route::get('/historial', [HistorialController::class, 'index'])->name('historial.index');
        Route::get('/historial/exportar/excel', [HistorialController::class, 'exportarExcel'])->name('historial.exportar.excel');
        Route::get('/historial/exportar/pdf', [HistorialController::class, 'exportarPdf'])->name('historial.exportar.pdf');
        Route::get('/historial/{caso}', [HistorialController::class, 'show'])->name('historial.show');
    });

    }); // Cierre de force_password_change

});