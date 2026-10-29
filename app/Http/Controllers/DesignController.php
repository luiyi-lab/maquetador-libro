<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\TemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Elección de plantilla y configuración de diseño (§11) — nunca hardcodeada.
 */
class DesignController extends Controller
{
    public function __construct(
        private readonly TemplateService $templates,
    ) {
    }

    public function update(Request $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        $slugs = array_column($this->templates->listTemplates(), 'slug');

        $validated = $request->validate([
            'template_slug' => ['required', Rule::in($slugs)],
            'design' => ['nullable', 'array'],
        ]);

        $clean = $this->templates->sanitizeDesignConfig($validated['design'] ?? [], $validated['template_slug']);

        $book->update([
            'template_slug' => $validated['template_slug'],
            'design_config' => $clean ?: null,
        ]);

        return back()->with('status', 'Diseño actualizado.');
    }
}
