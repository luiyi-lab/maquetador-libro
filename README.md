# Maquetador de Libros

Plataforma web donde el usuario sube un manuscrito (.md / .txt) y obtiene un libro maquetado y exportable a PDF, sin depender de IA para funcionar. Implementa el MVP del pipeline definido en `AGENTS.md` (§22):

```
Subir Markdown/TXT → Detectar estructura → Mostrar estructura → Editar estructura
  → Elegir plantilla → Previsualizar → Generar PDF
```

## Stack

- **Backend:** PHP 8.3 + Laravel 13 + SQLite (dev) / MySQL (producción)
- **Frontend:** Blade + CSS + JS vanilla (sin frameworks, sin Node)
- **Motor de render PDF:** mPDF (PHP puro, corre en hosting compartido cPanel — §4 AGENTS.md)
- **Inline markdown:** league/commonmark (determinista, HTML de usuario escapado)

## Capturas

| Dashboard | Editor de estructura |
|-----------|----------------------|
| ![Dashboard del maquetador](docs/capturas/dashboard.png) | ![Editor de estructura](docs/capturas/editor.png) |

<p align="center"><img src="docs/capturas/dashboard_mobile.png" width="280" alt="Vista móvil del dashboard"></p>

## Requisitos

- PHP >= 8.3 con extensiones: `mbstring`, `pdo_sqlite` (dev) o `pdo_mysql`, `gd`, `zip`, `intl`, `dom`, `curl`
- Composer

## Instalación (local Windows / Laragon)

```bat
php composer.phar install        # o composer install
copy .env.example .env           # ya configurado con SQLite para dev
php artisan migrate
php artisan serve
```

Con Laragon: `C:\laragon\bin\php\php-8.3.x\php.exe` y `C:\laragon\bin\composer\composer.phar`.

## Generación de PDF (cola)

Las exportaciones van por Laravel Queue con el driver `database` (§15):

```bat
php artisan queue:work           # worker en primer plano (dev)
```

En cPanel (sin daemon, §17): programar `php artisan schedule:run` cada minuto;
`routes/console.php` ya registra `queue:work --stop-when-empty` cada minuto sin solaparse.

## Verificación (§19)

```bat
php artisan test                              # suite completa (36 tests)
php scripts\verificar_demo.php                # import → PDF → validación estructural
php scripts\verificar_http.php [base-url]     # flujo HTTP: registro → dashboard
```

La validación del PDF comprueba: existencia, cabecera `%PDF`, `%%EOF`, número de
páginas, tamaño exacto 6×9 in (MediaBox) y fuente embebida. Nunca declarar un PDF
correcto sin estas comprobaciones.

## Arquitectura (§5, §13)

```
app/
  DTOs/            BookDocument, ChapterData, BlockData, ImportWarning, ChapterCandidate
  Importers/       BookImporter (interfaz), MarkdownImporter, TxtImporter
  Services/        BookImportService, BookNormalizationService, BookStructureAnalyzer,
                   BookRenderService, PdfExportService, TemplateService
  Jobs/            ProcessExportJob (cola: pending → processing → completed | failed)
  Policies/        BookPolicy (cada usuario accede solo a sus libros)
  Http/Controllers/  Auth, Dashboard, Book, Structure, Design, Export
templates/classic/  template.html · style.css (dialecto mPDF) · metadata.json
```

**Separación estricta:** contenido (modelo interno + BD) independiente del diseño
(plantillas). La plantilla se aplica después de la extracción, nunca durante.

## Reglas de oro implementadas

- **Nunca adivinar en silencio (§8):** los capítulos detectados con confianza
  60–84 % se muestran marcados con su score y el usuario los confirma o rechaza
  (`[Es un capítulo]` / `[Dejar como texto]`).
- **Fidelidad del contenido (§10):** la maquetación no reescribe nada. La
  normalización es solo técnica (BOM, invisibles, saltos, espacios) y queda
  registrada por operación en `book_versions.normalization`.
- **Original intacto (§13):** el manuscrito subido se guarda en
  `storage/app/books/u{user}/original/` y nunca se sobrescribe.
- **Preview = export (§11):** el preview usa exactamente el mismo pipeline
  `BookRenderService → PdfExportService` que el PDF final.
- **TOC con números reales (§11):** mPDF rellena los números tras paginar
  (`tocpagebreak` + `tocentry`); nunca se escriben a mano.
- **Numeración:** sin números en portada y preliminares; arábiga desde el
  capítulo 1 (`resetpagenum`), con arquitectura lista para romanos después.

## Sintaxis personalizada de Markdown (bloques explícitos)

```markdown
:::capitulo num=1 titulo="La vida que parecía perfecta"
Texto del capítulo…
:::

:::epigrafe autor="Julio Cortázar" fuente="Rayuela"
Yo no hago cosas para que duren.
:::

:::salto      ← salto de página
:::separador  ← ornamento tipográfico
```

## Estado

- **IMPLEMENTADO y PROBADO:** MVP completo (Fases 1–5 del §22), 36 tests PASS,
  PDF validado end-to-end.
- **PENDIENTE (Fases 6+):** import DOCX, export EPUB/DOCX, más plantillas,
  portadas con imagen, versionado con UI, IA opcional (Fase 8).
- **Pendiente de verificación visual:** revisar el PDF generado
  (`storage/app/demo_export.pdf`) a ojo en el lector de PDF de tu preferencia.

## Licencia

[MIT](LICENSE) © luiyi-lab
