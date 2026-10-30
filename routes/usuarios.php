<?php

use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
| Gestão de usuários da equipe (doc §2.3), sob `usuario.gerenciar`.
|
| Não há rota DELETE, de propósito: D-18 / D-08 proíbem exclusão física de quem assinou
| ato clínico. A saída de um profissional é desativação (`usuarios.situacao`).
*/
Route::middleware(['auth'])->group(function () {
    Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::get('usuarios/novo', [UsuarioController::class, 'create'])->name('usuarios.create');
    Route::post('usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
    Route::get('usuarios/{usuario}', [UsuarioController::class, 'edit'])->name('usuarios.edit');
    Route::put('usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
    Route::patch('usuarios/{usuario}/situacao', [UsuarioController::class, 'situacao'])->name('usuarios.situacao');
    Route::put('usuarios/{usuario}/senha', [UsuarioController::class, 'senha'])->name('usuarios.senha');
});
