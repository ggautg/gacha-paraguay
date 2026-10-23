<?php

use App\Http\Controllers\AlbumController;
use App\Http\Controllers\GachaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GachaController::class, 'index'])->name('home');
Route::post('/tirar', [GachaController::class, 'pull'])->name('pull');
Route::post('/bonificacion', [GachaController::class, 'bonus'])->name('bonus');
Route::get('/coleccion', [AlbumController::class, 'index'])->name('album');
