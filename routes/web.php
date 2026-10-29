<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\StructureController;
use Illuminate\Support\Facades\Route;

// Autenticación
Route::middleware('guest')->group(function (): void {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Libros: subir e importar
    Route::get('/libros/nuevo', [BookController::class, 'create'])->name('books.create');
    Route::post('/libros', [BookController::class, 'store'])->name('books.store');

    // Revisión, edición de estructura, diseño y exportación
    Route::get('/libros/{book}', [BookController::class, 'show'])->name('books.show');
    Route::delete('/libros/{book}', [BookController::class, 'destroy'])->name('books.destroy');

    // Metadatos
    Route::patch('/libros/{book}/metadatos', [StructureController::class, 'updateBook'])
        ->name('books.metadata.update');

    // Capítulos
    Route::post('/libros/{book}/capitulos', [StructureController::class, 'storeChapter'])
        ->name('chapters.store');
    Route::patch('/libros/{book}/capitulos/{chapter}', [StructureController::class, 'updateChapter'])
        ->name('chapters.update');
    Route::delete('/libros/{book}/capitulos/{chapter}', [StructureController::class, 'destroyChapter'])
        ->name('chapters.destroy');
    Route::post('/libros/{book}/capitulos/{chapter}/fusionar', [StructureController::class, 'mergeChapter'])
        ->name('chapters.merge');
    Route::post('/libros/{book}/capitulos/{chapter}/convertir-texto', [StructureController::class, 'convertChapterToText'])
        ->name('chapters.convert-to-text');
    Route::post('/libros/{book}/capitulos/{chapter}/dividir/{block}', [StructureController::class, 'splitChapter'])
        ->name('chapters.split');
    Route::post('/libros/{book}/capitulos/{chapter}/mover', [StructureController::class, 'moveChapter'])
        ->name('chapters.move');
    Route::post('/libros/{book}/capitulos/reordenar', [StructureController::class, 'reorderChapters'])
        ->name('chapters.reorder');

    // Bloques
    Route::post('/libros/{book}/capitulos/{chapter}/bloques', [StructureController::class, 'storeBlock'])
        ->name('blocks.store');
    Route::patch('/libros/{book}/bloques/{block}', [StructureController::class, 'updateBlock'])
        ->name('blocks.update');
    Route::delete('/libros/{book}/bloques/{block}', [StructureController::class, 'destroyBlock'])
        ->name('blocks.destroy');
    Route::post('/libros/{book}/bloques/{block}/convertir-capitulo', [StructureController::class, 'convertBlockToChapter'])
        ->name('blocks.convert-to-chapter');
    Route::post('/libros/{book}/bloques/{block}/mover', [StructureController::class, 'moveBlock'])
        ->name('blocks.move');

    // Diseño
    Route::patch('/libros/{book}/diseno', [DesignController::class, 'update'])
        ->name('books.design.update');

    // Exportación
    Route::get('/libros/{book}/preview.pdf', [ExportController::class, 'preview'])
        ->name('exports.preview');
    Route::post('/libros/{book}/exportar', [ExportController::class, 'store'])
        ->name('exports.store');
    Route::get('/libros/{book}/exportaciones/{export}/estado', [ExportController::class, 'status'])
        ->name('exports.status');
    Route::get('/libros/{book}/exportaciones/{export}/descargar', [ExportController::class, 'download'])
        ->name('exports.download');
});
