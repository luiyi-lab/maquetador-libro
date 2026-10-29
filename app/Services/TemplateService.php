<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookTemplate;
use Illuminate\Support\Facades\File;

/**
 * Carga las plantillas desde /templates (§11) y registra sus filas en la
 * tabla `templates`. Resuelve la configuración de diseño del usuario sobre
 * los valores por defecto del template (nunca hardcodeada en controladores).
 */
class TemplateService
{
    public function templatesPath(): string
    {
        return base_path('templates');
    }

    /**
     * Devuelve las plantillas disponibles, registrándolas en BD si faltan.
     * @return array<int, array{slug:string, name:string, description:string}>
     */
    public function listTemplates(): array
    {
        $list = [];
        foreach (File::directories($this->templatesPath()) as $dir) {
            $metaFile = $dir . DIRECTORY_SEPARATOR . 'metadata.json';
            if (!File::exists($metaFile)) {
                continue;
            }
            $meta = json_decode(File::get($metaFile), true);
            if (!is_array($meta) || !isset($meta['slug'], $meta['name'])) {
                continue;
            }

            BookTemplate::updateOrCreate(
                ['slug' => $meta['slug']],
                ['name' => $meta['name'], 'folder' => basename($dir), 'is_active' => true],
            );

            $list[] = [
                'slug' => $meta['slug'],
                'name' => $meta['name'],
                'description' => $meta['description'] ?? '',
            ];
        }

        return $list;
    }

    public function getTemplate(string $slug): array
    {
        $path = $this->templatesPath() . DIRECTORY_SEPARATOR . basename($slug);
        $metaFile = $path . DIRECTORY_SEPARATOR . 'metadata.json';
        if (!is_dir($path) || !File::exists($metaFile)) {
            // Fallback determinista: primera plantilla disponible
            $first = File::directories($this->templatesPath())[0] ?? null;
            if ($first === null) {
                throw new \RuntimeException('No hay plantillas disponibles en ' . $this->templatesPath());
            }
            $path = $first;
            $metaFile = $path . DIRECTORY_SEPARATOR . 'metadata.json';
        }

        $meta = json_decode(File::get($metaFile), true);
        $meta['path'] = $path;
        $meta['style'] = File::exists($path . '/style.css') ? File::get($path . '/style.css') : '';
        $meta['wrapper'] = File::exists($path . '/template.html') ? File::get($path . '/template.html') : '{{content}}';

        return $meta;
    }

    /**
     * Configuración de diseño final: defaults de la plantilla + overrides del usuario (§11).
     */
    public function resolveDesignConfig(Book $book): array
    {
        $template = $this->getTemplate($book->template_slug);
        $defaults = $template['defaults'] ?? [];

        return array_merge($defaults, $book->design_config ?? []);
    }

    /**
     * Filtra y sanea los ajustes de diseño que el usuario puede cambiar (§11).
     */
    public function sanitizeDesignConfig(array $input, string $slug): array
    {
        $template = $this->getTemplate($slug);
        $allowed = $template['defaults'] ?? [];

        $clean = [];
        foreach ($allowed as $key => $default) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = $input[$key];
            if (is_int($default)) {
                $clean[$key] = max(1, (int) $value);
            } elseif (is_bool($default)) {
                $clean[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } else {
                $clean[$key] = mb_substr(trim((string) $value), 0, 60);
            }
        }

        return $clean;
    }

    /**
     * CSS final de la plantilla con los placeholders sustituidos (dialecto mPDF).
     */
    public function renderCss(string $slug, array $config): string
    {
        $template = $this->getTemplate($slug);
        $css = $template['style'];

        $replacements = [
            '{{font_family}}' => (string) ($config['font_family'] ?? 'dejavuserif'),
            '{{font_size}}' => (string) ($config['font_size'] ?? 11),
            '{{font_size_t}}' => (string) max(18, (int) ($config['font_size'] ?? 11) + 10),
            '{{font_size_epigraph}}' => (string) max(8, (int) ($config['font_size'] ?? 11) - 1),
            '{{line_height}}' => (string) ($config['line_height'] ?? 1.5),
            '{{paragraph_indent}}' => (string) ($config['paragraph_indent'] ?? '1.2em'),
            '{{paragraph_spacing}}' => (string) ($config['paragraph_spacing'] ?? '0'),
            '{{chapter_alignment}}' => (string) ($config['chapter_alignment'] ?? 'center'),
            '{{chapter_spacing}}' => (string) ($config['chapter_spacing'] ?? '2.5em'),
            '{{language}}' => '',
            '{{meta_title}}' => '',
            '{{content}}' => '',
            '{{style}}' => '',
        ];

        return strtr($css, $replacements);
    }
}
