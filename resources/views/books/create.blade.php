@extends('layouts.app')

@section('title', 'Nuevo libro')

@section('content')
<div class="auth-wrap" style="align-items:start;">
    <div class="auth-card" style="width:min(640px, 100%);">
        <h1 class="text-serif">Nuevo libro</h1>
        <p class="auth-sub">Arrastra tu manuscrito y el sistema detecta la estructura. Luego tú la corriges.</p>

        <form method="POST" action="{{ route('books.store') }}" enctype="multipart/form-data" id="upload-form">
            @csrf

            <div class="dropzone" id="dropzone" role="button" tabindex="0"
                 aria-label="Arrastra tu archivo aquí o pulsa para elegirlo">
                <span class="dz-icon"><i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i></span>
                <p class="dz-title"><strong>Arrastra tu archivo aquí</strong></p>
                <p class="muted small" style="margin:0;">o haz clic para elegirlo desde tu equipo</p>
                <p class="dz-file" id="dz-file">Ningún archivo seleccionado</p>
                <input type="file" name="file" id="file-input" accept=".md,.txt" hidden>
            </div>
            @error('file')
                <p class="field-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> {{ $message }}</p>
            @enderror

            <div class="formats-hint" aria-hidden="true">
                <span class="badge badge-neutral"><i class="fa-brands fa-markdown"></i> Markdown (.md)</span>
                <span class="badge badge-neutral"><i class="fa-solid fa-file-lines"></i> Texto (.txt)</span>
                <span class="badge badge-neutral"><i class="fa-solid fa-weight-hanging"></i> máx. 10 MB</span>
            </div>

            <div class="design-section" style="border-top:none; padding-top:0; margin-top:0;">
                <h3><i class="fa-solid fa-feather" aria-hidden="true"></i> Metadatos (opcional)</h3>
                <div class="form-grid cols-2">
                    <div class="field">
                        <label for="title">Título del libro</label>
                        <input id="title" name="title" type="text" value="{{ old('title') }}"
                               placeholder="Se detecta del manuscrito si lo dejas vacío">
                    </div>
                    <div class="field">
                        <label for="author">Autor</label>
                        <input id="author" name="author" type="text" value="{{ old('author') }}">
                    </div>
                </div>
            </div>

            <div class="form-foot">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Analizar manuscrito
                </button>
                <p class="field-hint" style="text-align:center;">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    Tu manuscrito se procesa en el servidor y nunca sale de él.
                </p>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    initDropzone(
        document.getElementById('dropzone'),
        document.getElementById('file-input'),
        document.getElementById('dz-file'),
        document.getElementById('upload-form')
    );
</script>
@endpush
@endsection
