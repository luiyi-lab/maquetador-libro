@extends('layouts.app')

@section('title', $book->title)

@section('content')
<div class="page-head">
    <div>
        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-ghost" style="margin-bottom: var(--space-2); padding-inline: 0.4rem;">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Mis libros
        </a>
        <h1 class="text-serif">{{ $book->title }}</h1>
        <p class="subtitle">
            <i class="fa-solid fa-layer-group" aria-hidden="true"></i> {{ $book->chapters->count() }} capítulos
            · <i class="fa-regular fa-file-lines" aria-hidden="true"></i> {{ $book->versions->first()?->original_name }}
        </p>
    </div>
    <div class="page-actions">
        <a class="btn" href="{{ route('exports.preview', $book) }}" target="_blank" rel="noopener">
            <i class="fa-regular fa-eye" aria-hidden="true"></i> Previsualizar
        </a>
        <form method="POST" action="{{ route('exports.store', $book) }}" class="inline" id="export-form">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Generar PDF
            </button>
        </form>
    </div>
</div>

<div class="tabs" id="book-tabs" role="tablist">
    <button class="tab active" data-tab="tab-structure" role="tab">
        <i class="fa-solid fa-sitemap" aria-hidden="true"></i> Estructura
    </button>
    <button class="tab" data-tab="tab-design" role="tab">
        <i class="fa-solid fa-palette" aria-hidden="true"></i> Diseño
    </button>
    <button class="tab" data-tab="tab-exports" role="tab">
        <i class="fa-solid fa-file-export" aria-hidden="true"></i> Exportaciones
    </button>
</div>

{{-- ==================== PESTAÑA: ESTRUCTURA ==================== --}}
<section id="tab-structure" class="tab-panel active">

    {{-- Advertencias y elementos ambiguos (§8, §9: nunca adivinar en silencio) --}}
    @if ($warnings || $book->chapters->contains(fn ($c) => $c->needs_confirmation))
        <div class="card card-pad warnings-card">
            <h2><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Revisión necesaria</h2>
            <ul class="warnings">
                @foreach ($warnings as $warning)
                    @if (($warning['level'] ?? '') !== 'info')
                        <li>
                            <i class="fa-solid {{ ($warning['level'] ?? '') === 'error' ? 'fa-circle-exclamation warn-icon-error' : 'fa-triangle-exclamation warn-icon-warning' }}" aria-hidden="true"></i>
                            <span class="warn-text">
                                {{ $warning['message'] }}
                                @if (isset($warning['context']['score']))
                                    <span class="chip chip-warn">confianza {{ $warning['context']['score'] }}%</span>
                                @endif
                            </span>
                        </li>
                    @endif
                @endforeach
                @foreach ($book->chapters->where('needs_confirmation', true) as $chapter)
                    <li>
                        <i class="fa-solid fa-circle-question warn-icon-warning" aria-hidden="true"></i>
                        <span class="warn-text">
                            ¿Es capítulo «{{ $chapter->title ?? 'sin título' }}»?
                            <span class="chip chip-warn">confianza {{ $chapter->confidence ?? '?' }}%</span>
                        </span>
                        <form method="POST" action="{{ route('chapters.update', [$book, $chapter]) }}" class="inline">
                            @csrf
                            <input type="hidden" name="confirm" value="1">
                            <button type="submit" class="btn btn-sm">
                                <i class="fa-solid fa-check" aria-hidden="true"></i> Es un capítulo
                            </button>
                        </form>
                        <form method="POST" action="{{ route('chapters.convert-to-text', [$book, $chapter]) }}" class="inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-ghost">
                                <i class="fa-solid fa-align-left" aria-hidden="true"></i> Dejar como texto
                            </button>
                        </form>
                    </li>
                @endforeach
                @if (collect($warnings)->contains(fn ($w) => ($w['level'] ?? '') === 'info'))
                    <details>
                        <summary><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Mensajes informativos ({{ collect($warnings)->where('level', 'info')->count() }})</summary>
                        <ul class="warnings" style="margin-top: var(--space-2);">
                            @foreach ($warnings as $warning)
                                @if (($warning['level'] ?? '') === 'info')
                                    <li><span class="warn-text">{{ $warning['message'] }}</span></li>
                                @endif
                            @endforeach
                        </ul>
                    </details>
                @endif
            </ul>
        </div>
    @endif

    {{-- Metadatos --}}
    <div class="card card-pad panel">
        <h2><i class="fa-solid fa-feather-pointed" aria-hidden="true"></i> Metadatos</h2>
        <form method="POST" action="{{ route('books.metadata.update', $book) }}" style="margin-top: var(--space-3);">
            @csrf
            @method('PATCH')
            <div class="form-grid cols-3">
                <div class="field">
                    <label for="meta-title">Título</label>
                    <input id="meta-title" type="text" name="title" value="{{ old('title', $book->title) }}" required>
                </div>
                <div class="field">
                    <label for="meta-subtitle">Subtítulo</label>
                    <input id="meta-subtitle" type="text" name="subtitle" value="{{ old('subtitle', $book->subtitle) }}">
                </div>
                <div class="field">
                    <label for="meta-author">Autor</label>
                    <input id="meta-author" type="text" name="author" value="{{ old('author', $book->author) }}">
                </div>
            </div>
            <button class="btn btn-sm" type="submit">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar metadatos
            </button>
        </form>
    </div>

    {{-- Capítulos --}}
    <div class="card card-pad panel">
        <h2><i class="fa-solid fa-sitemap" aria-hidden="true"></i> Capítulos</h2>
        <p class="muted small">Arrastra los capítulos con <i class="fa-solid fa-grip-dots-vertical" aria-hidden="true"></i> para reordenar. Pulsa «Editar» para ver y corregir sus bloques.</p>

        <div id="chapter-list" data-reorder-url="{{ route('chapters.reorder', $book) }}" style="margin-top: var(--space-4);">
            @foreach ($book->chapters as $chapter)
                <article class="chapter-card {{ $chapter->needs_confirmation ? 'needs-confirm' : '' }}" draggable="true" data-chapter-id="{{ $chapter->id }}">
                    <div class="chapter-head">
                        <span class="drag-handle" title="Arrastrar para reordenar" aria-hidden="true"><i class="fa-solid fa-grip-dots-vertical"></i></span>
                        @if ($chapter->number !== null)
                            <span class="chip">Cap. {{ $chapter->number }}</span>
                        @endif
                        <span class="chapter-name">{{ $chapter->title ?? 'Sin título' }}</span>
                        @if ($chapter->needs_confirmation)
                            <span class="chip chip-warn" title="Confianza {{ $chapter->confidence ?? '?' }}%">
                                <i class="fa-solid fa-circle-question" aria-hidden="true"></i> ambiguo {{ $chapter->confidence ?? '?' }}%
                            </span>
                        @endif
                        <span class="chapter-count"><i class="fa-solid fa-cubes-stacked" aria-hidden="true"></i> {{ $chapter->blocks->count() }}</span>
                        <button type="button" class="btn btn-sm toggle-blocks" data-toggle="blocks-{{ $chapter->id }}">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i> <span class="toggle-label">Editar</span>
                        </button>
                        <div class="chapter-actions">
                            <form method="POST" action="{{ route('chapters.move', [$book, $chapter]) }}" class="inline">
                                @csrf
                                <input type="hidden" name="direction" value="up">
                                <button type="submit" class="icon-btn" title="Subir capítulo" aria-label="Subir capítulo"><i class="fa-solid fa-arrow-up"></i></button>
                            </form>
                            <form method="POST" action="{{ route('chapters.move', [$book, $chapter]) }}" class="inline">
                                @csrf
                                <input type="hidden" name="direction" value="down">
                                <button type="submit" class="icon-btn" title="Bajar capítulo" aria-label="Bajar capítulo"><i class="fa-solid fa-arrow-down"></i></button>
                            </form>
                            <form method="POST" action="{{ route('chapters.destroy', [$book, $chapter]) }}" class="inline"
                                  data-confirm="¿Eliminar el capítulo y todo su contenido?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="icon-btn danger" title="Eliminar capítulo" aria-label="Eliminar capítulo"><i class="fa-solid fa-trash-can"></i></button>
                            </form>
                        </div>
                    </div>

                    <div class="blocks-panel" id="blocks-{{ $chapter->id }}" hidden>
                        {{-- Edición del capítulo --}}
                        <form method="POST" action="{{ route('chapters.update', [$book, $chapter]) }}">
                            @csrf
                            @method('PATCH')
                            <div class="form-grid cols-3">
                                <div class="field">
                                    <label for="ch-title-{{ $chapter->id }}">Título</label>
                                    <input id="ch-title-{{ $chapter->id }}" type="text" name="title" value="{{ $chapter->title }}">
                                </div>
                                <div class="field">
                                    <label for="ch-number-{{ $chapter->id }}">Número</label>
                                    <input id="ch-number-{{ $chapter->id }}" type="number" name="number" min="1" value="{{ $chapter->number }}">
                                </div>
                                <div class="field">
                                    <label for="ch-section-{{ $chapter->id }}">Sección</label>
                                    <select id="ch-section-{{ $chapter->id }}" name="section">
                                        @foreach (['front' => 'Preliminares', 'body' => 'Cuerpo', 'back' => 'Sección final'] as $value => $label)
                                            <option value="{{ $value }}" @selected($chapter->section === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <button class="btn btn-sm" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar capítulo</button>
                        </form>

                        {{-- Bloques --}}
                        <div class="blocks">
                            @foreach ($chapter->blocks as $block)
                                <div class="block-row">
                                    <span class="chip block-type-chip">{{ $block->type }}</span>
                                    <form method="POST" action="{{ route('blocks.update', [$book, $block]) }}" class="block-edit">
                                        @csrf
                                        @method('PATCH')
                                        <textarea name="content" rows="2" aria-label="Contenido del bloque {{ $block->type }}">{{ $block->content }}</textarea>
                                        <div class="block-actions">
                                            <select name="type" aria-label="Tipo de bloque">
                                                @foreach (\App\Models\BookBlock::TYPES as $type)
                                                    <option value="{{ $type }}" @selected($block->type === $type)>{{ $type }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-sm" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar</button>
                                        </div>
                                    </form>
                                    <div class="block-actions">
                                        <form method="POST" action="{{ route('blocks.move', [$book, $block]) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="direction" value="up">
                                            <button type="submit" class="icon-btn" title="Subir bloque" aria-label="Subir bloque"><i class="fa-solid fa-arrow-up"></i></button>
                                        </form>
                                        <form method="POST" action="{{ route('blocks.move', [$book, $block]) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="direction" value="down">
                                            <button type="submit" class="icon-btn" title="Bajar bloque" aria-label="Bajar bloque"><i class="fa-solid fa-arrow-down"></i></button>
                                        </form>
                                        <form method="POST" action="{{ route('blocks.convert-to-chapter', [$book, $block]) }}" class="inline"
                                              data-confirm="¿Convertir este bloque en capítulo?">
                                            @csrf
                                            <button type="submit" class="icon-btn" title="Convertir en capítulo" aria-label="Convertir en capítulo"><i class="fa-solid fa-heading"></i></button>
                                        </form>
                                        <form method="POST" action="{{ route('chapters.split', [$book, $chapter, $block]) }}" class="inline"
                                              data-confirm="¿Dividir el capítulo aquí? Este bloque y los siguientes pasarán a un capítulo nuevo.">
                                            @csrf
                                            <button type="submit" class="icon-btn" title="Dividir capítulo aquí" aria-label="Dividir capítulo aquí"><i class="fa-solid fa-scissors"></i></button>
                                        </form>
                                        <form method="POST" action="{{ route('blocks.destroy', [$book, $block]) }}" class="inline"
                                              data-confirm="¿Eliminar este bloque?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="icon-btn danger" title="Eliminar bloque" aria-label="Eliminar bloque"><i class="fa-solid fa-trash-can"></i></button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Agregar bloque --}}
                        <form method="POST" action="{{ route('blocks.store', [$book, $chapter]) }}" class="add-block-form">
                            @csrf
                            <label class="sr-only" for="new-block-type-{{ $chapter->id }}">Tipo de bloque</label>
                            <select id="new-block-type-{{ $chapter->id }}" name="type">
                                <option value="paragraph">Párrafo</option>
                                <option value="page_break">Salto de página</option>
                                <option value="separator">Separador</option>
                                <option value="epigraph">Epígrafe</option>
                            </select>
                            <textarea name="content" rows="2" placeholder="Texto del bloque (vacío para salto/separador)"></textarea>
                            <button class="btn btn-sm" type="submit"><i class="fa-solid fa-plus" aria-hidden="true"></i> Agregar</button>
                        </form>

                        {{-- Operaciones del capítulo --}}
                        <div class="chapter-ops">
                            <form method="POST" action="{{ route('chapters.merge', [$book, $chapter]) }}" class="inline"
                                  data-confirm="¿Unir este capítulo con el siguiente?">
                                @csrf
                                <button type="submit" class="btn btn-sm"><i class="fa-solid fa-down-left-and-up-right-to-center" aria-hidden="true"></i> Unir con el siguiente</button>
                            </form>
                            <form method="POST" action="{{ route('chapters.convert-to-text', [$book, $chapter]) }}" class="inline"
                                  data-confirm="¿Convertir este capítulo en texto dentro del capítulo anterior/siguiente?">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-ghost"><i class="fa-solid fa-align-left" aria-hidden="true"></i> Convertir en texto</button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <form method="POST" action="{{ route('chapters.store', $book) }}" class="add-chapter-form">
            @csrf
            <input type="text" name="title" placeholder="Título del nuevo capítulo" aria-label="Título del nuevo capítulo">
            <input type="number" name="number" placeholder="Nº" min="1" aria-label="Número del nuevo capítulo">
            <button class="btn" type="submit"><i class="fa-solid fa-plus" aria-hidden="true"></i> Agregar capítulo</button>
        </form>
    </div>
</section>

{{-- ==================== PESTAÑA: DISEÑO ==================== --}}
<section id="tab-design" class="tab-panel">
    <div class="card card-pad">
        <h2><i class="fa-solid fa-palette" aria-hidden="true"></i> Plantilla y diseño</h2>
        <form method="POST" action="{{ route('books.design.update', $book) }}" style="margin-top: var(--space-3);">
            @csrf
            @method('PATCH')

            <div class="design-section" style="border-top: none; padding-top: 0; margin-top: 0;">
                <h3><i class="fa-solid fa-clone" aria-hidden="true"></i> Plantilla</h3>
                <div class="template-grid">
                    @foreach ($templates as $tpl)
                        <label class="template-card">
                            <input type="radio" name="template_slug" value="{{ $tpl['slug'] }}" @checked($tpl['slug'] === $book->template_slug)>
                            <span class="tpl-check"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
                            <strong class="text-serif">{{ $tpl['name'] }}</strong>
                            <span>{{ $tpl['description'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="design-section">
                <h3><i class="fa-solid fa-font" aria-hidden="true"></i> Tipografía y página</h3>
                <div class="form-grid cols-3">
                    <div class="field">
                        <label for="d-page-size">Tamaño de página</label>
                        <select id="d-page-size" name="design[page_size]">
                            @foreach ($pageSizes as $size => $label)
                                <option value="{{ $size }}" @selected(($design['page_size'] ?? '') === $size)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="d-font">Fuente</label>
                        <select id="d-font" name="design[font_family]">
                            @foreach ($fonts as $font)
                                <option value="{{ $font['id'] }}" @selected(($design['font_family'] ?? '') === $font['id'])>{{ $font['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="d-font-size">Tamaño de fuente (pt)</label>
                        <input id="d-font-size" type="number" name="design[font_size]" min="7" max="20" value="{{ $design['font_size'] ?? 11 }}">
                    </div>
                    <div class="field">
                        <label for="d-line-height">Interlineado</label>
                        <input id="d-line-height" type="number" name="design[line_height]" step="0.1" min="1" max="2.5" value="{{ $design['line_height'] ?? 1.5 }}">
                    </div>
                    <div class="field">
                        <label for="d-indent">Sangría de párrafo</label>
                        <input id="d-indent" type="text" name="design[paragraph_indent]" value="{{ $design['paragraph_indent'] ?? '1.2em' }}">
                    </div>
                    <div class="field">
                        <label for="d-spacing">Espaciado entre párrafos</label>
                        <input id="d-spacing" type="text" name="design[paragraph_spacing]" value="{{ $design['paragraph_spacing'] ?? '0' }}">
                    </div>
                </div>
            </div>

            <div class="design-section">
                <h3><i class="fa-solid fa-vector-square" aria-hidden="true"></i> Márgenes (mm)</h3>
                <div class="form-grid cols-4">
                    <div class="field">
                        <label for="d-m-top">Superior</label>
                        <input id="d-m-top" type="number" name="design[margin_top]" min="10" max="60" value="{{ $design['margin_top'] ?? 20 }}">
                    </div>
                    <div class="field">
                        <label for="d-m-bottom">Inferior</label>
                        <input id="d-m-bottom" type="number" name="design[margin_bottom]" min="10" max="60" value="{{ $design['margin_bottom'] ?? 20 }}">
                    </div>
                    <div class="field">
                        <label for="d-m-inner">Interior</label>
                        <input id="d-m-inner" type="number" name="design[margin_inner]" min="8" max="60" value="{{ $design['margin_inner'] ?? 18 }}">
                    </div>
                    <div class="field">
                        <label for="d-m-outer">Exterior</label>
                        <input id="d-m-outer" type="number" name="design[margin_outer]" min="8" max="60" value="{{ $design['margin_outer'] ?? 18 }}">
                    </div>
                </div>
            </div>

            <div class="design-section">
                <h3><i class="fa-solid fa-sliders" aria-hidden="true"></i> Opciones</h3>
                <div class="form-grid cols-2">
                    <label class="checkbox"><input type="checkbox" name="design[show_page_numbers]" value="1" @checked($design['show_page_numbers'] ?? true)> Mostrar números de página</label>
                    <label class="checkbox"><input type="checkbox" name="design[header_enabled]" value="1" @checked($design['header_enabled'] ?? false)> Encabezado con el título</label>
                    <label class="checkbox"><input type="checkbox" name="design[footer_enabled]" value="1" @checked($design['footer_enabled'] ?? true)> Pie de página</label>
                    <label class="checkbox"><input type="checkbox" name="design[toc_enabled]" value="1" @checked($design['toc_enabled'] ?? false)> Incluir índice (TOC)</label>
                </div>
            </div>

            <div class="form-foot">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar diseño</button>
            </div>
        </form>
    </div>
</section>

{{-- ==================== PESTAÑA: EXPORTACIONES ==================== --}}
<section id="tab-exports" class="tab-panel">
    <div class="card card-pad">
        <h2><i class="fa-solid fa-file-export" aria-hidden="true"></i> Exportaciones</h2>

        @if ($book->exports->isEmpty())
            <div class="empty-state" style="border: none; background: transparent; padding-block: var(--space-5);">
                <i class="fa-solid fa-file-pdf empty-icon" aria-hidden="true"></i>
                <p style="margin:0;">Aún no has generado ningún PDF para este libro.</p>
                <p class="muted small" style="margin:0;">Usa el botón «Generar PDF» de la parte superior.</p>
            </div>
        @else
            <div class="table-wrap" style="box-shadow:none;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Formato</th>
                            <th>Estado</th>
                            <th>Páginas</th>
                            <th>Tamaño</th>
                            <th>Fecha</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($book->exports as $export)
                            @php
                                [$expClass, $expIcon] = match ($export->status) {
                                    'completed' => ['badge-success', 'fa-check'],
                                    'failed' => ['badge-error', 'fa-xmark'],
                                    'processing' => ['badge-info', 'fa-spinner fa-spin-pulse'],
                                    default => ['badge-warning', 'fa-clock'],
                                };
                            @endphp
                            <tr data-export-row="{{ $export->id }}">
                                <td><i class="fa-regular fa-file-pdf" aria-hidden="true"></i> {{ strtoupper($export->format) }}</td>
                                <td><span class="badge {{ $expClass }}"><i class="fa-solid {{ $expIcon }}" aria-hidden="true"></i> {{ $export->status }}</span></td>
                                <td>{{ $export->page_count ?? '—' }}</td>
                                <td>{{ $export->file_size ? number_format($export->file_size / 1024) . ' KB' : '—' }}</td>
                                <td>{{ $export->created_at->format('d/m/Y H:i') }}</td>
                                <td class="row-actions">
                                    @if ($export->status === 'completed')
                                        <a class="btn btn-sm" href="{{ route('exports.download', [$book, $export]) }}">
                                            <i class="fa-solid fa-download" aria-hidden="true"></i> Descargar
                                        </a>
                                    @elseif ($export->status === 'failed')
                                        <span class="muted small" title="{{ $export->error }}">{{ Str::limit($export->error, 60) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if (session('export_id'))
            <div class="progress-banner" data-status-url="{{ route('exports.status', [$book, session('export_id')]) }}" hidden>
                <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                Generando PDF… la página se actualizará al terminar.
            </div>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script>
    initTabs(document.getElementById('book-tabs'));
    initDragReorder(document.getElementById('chapter-list'));
    initConfirmForms();
    initBlockToggles();
@if (session('export_id'))
    pollExport(document.querySelector('.progress-banner'));
@endif
</script>
@endpush
