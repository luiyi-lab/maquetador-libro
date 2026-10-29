@extends('layouts.app')

@section('title', 'Mis libros')

@section('content')
<div class="page-head">
    <div>
        <h1 class="text-serif">Mis libros</h1>
        <p class="subtitle">Tu editorial personal: importa, corrige y maqueta.</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('books.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Nuevo libro
        </a>
    </div>
</div>

@if ($books->isEmpty())
    <div class="empty-state">
        <i class="fa-solid fa-book empty-icon" aria-hidden="true"></i>
        <p style="margin:0; font-weight:600;">Aún no tienes libros</p>
        <p class="muted" style="margin:0;">
            Sube tu manuscrito en formato <strong>MD · TXT</strong> y el sistema detecta
            la estructura por ti.
        </p>
        <a href="{{ route('books.create') }}" class="btn btn-primary" style="margin-top: var(--space-2);">
            <i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i> Subir mi primer manuscrito
        </a>
    </div>
@else
    <div class="stats-row" aria-hidden="true">
        <div class="stat-card">
            <span class="stat-icon"><i class="fa-solid fa-book"></i></span>
            <div>
                <div class="stat-value">{{ $books->count() }}</div>
                <div class="stat-label">Libros</div>
            </div>
        </div>
        <div class="stat-card">
            <span class="stat-icon"><i class="fa-solid fa-layer-group"></i></span>
            <div>
                <div class="stat-value">{{ $books->sum('chapters_count') }}</div>
                <div class="stat-label">Capítulos</div>
            </div>
        </div>
        <div class="stat-card">
            <span class="stat-icon"><i class="fa-solid fa-file-pdf"></i></span>
            <div>
                <div class="stat-value">{{ $books->where('status', 'exported')->count() }}</div>
                <div class="stat-label">Exportados</div>
            </div>
        </div>
    </div>

    <div class="book-list">
        @foreach ($books as $book)
            <article class="card card-hover book-item">
                <div>
                    <a class="book-title" href="{{ route('books.show', $book) }}">{{ $book->title }}</a>
                    <div class="book-meta">
                        @if ($book->author)
                            <span><i class="fa-solid fa-user-pen" aria-hidden="true"></i> {{ $book->author }}</span>
                        @endif
                        <span><i class="fa-solid fa-layer-group" aria-hidden="true"></i> {{ $book->chapters_count }} capítulos</span>
                        <span><i class="fa-regular fa-clock" aria-hidden="true"></i> {{ $book->updated_at->format('d/m/Y H:i') }}</span>
                        @php
                            [$statusClass, $statusIcon] = match ($book->status) {
                                'exported' => ['badge-success', 'fa-check'],
                                'imported' => ['badge-warning', 'fa-pen-ruler'],
                                default => ['badge-neutral', 'fa-circle-half-stroke'],
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">
                            <i class="fa-solid {{ $statusIcon }}" aria-hidden="true"></i>
                            {{ $book->status }}
                        </span>
                    </div>
                </div>
                <div class="book-actions">
                    <a class="btn btn-sm" href="{{ route('books.show', $book) }}">
                        <i class="fa-solid fa-pen-ruler" aria-hidden="true"></i> Abrir
                    </a>
                    <form method="POST" action="{{ route('books.destroy', $book) }}" class="inline"
                          data-confirm="¿Eliminar el libro «{{ $book->title }}»? Esta acción no se puede deshacer.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" aria-label="Eliminar {{ $book->title }}">
                            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                            <span class="hide-sm-inline">Eliminar</span>
                        </button>
                    </form>
                </div>
            </article>
        @endforeach
    </div>
@endif
@endsection
