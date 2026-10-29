<?php
// Captura de páginas autenticadas para verificación visual (solo presentación).
// Hace login real, guarda el HTML y reescribe los assets a URLs absolutas
// para poder renderizarlos sin sesión en un navegador headless.
$base = $argv[1] ?? 'http://127.0.0.1:8000';
$outDir = __DIR__ . '/../storage/shots';
$cookieJar = sys_get_temp_dir() . '/maquetador_captura.txt';
@mkdir($outDir, 0775, true);

function request(string $url, array $options = [], array $headers = []): array
{
    global $cookieJar;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
    ]);
    if ($options) {
        curl_setopt_array($ch, $options);
    }
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return [$status, substr($response, 0, $headerSize), substr($response, $headerSize)];
}

function login(string $base): void
{
    [$status, $headers, $body] = request("$base/login");
    preg_match('/name="_token" value="([^"]+)"/', $body, $m);
    request("$base/login", [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            '_token' => $m[1] ?? '',
            'email' => 'demo@local.test',
            'password' => 'password1234',
        ]),
    ], ['Content-Type: application/x-www-form-urlencoded']);
}

function savePage(string $base, string $url, string $file): string
{
    [$status, $headers, $body] = request($url, [], ['Accept: text/html']);
    if ($status !== 200) {
        echo "AVISO: $url devolvió $status\n";
        return '';
    }
    // Reescribir assets locales a absolutos para renderizarlos desde file://
    $body = str_replace('href="/css/app.css"', "href=\"$base/css/app.css\"", $body);
    $body = str_replace('src="/js/app.js"', "src=\"$base/js/app.js\"", $body);
    // Neutralizar formularios (solo se captura el aspecto, no se envía nada)
    $body = str_replace('<form', '<form onsubmit="return false"', $body);
    file_put_contents($file, $body);
    echo "Guardado: $file (" . strlen($body) . " bytes)\n";

    return $file;
}

login($base);

$dashboardFile = savePage($base, "$base/", "$outDir/dashboard.html");

// Buscar el primer libro del usuario demo en el dashboard
$dashboardHtml = file_get_contents($dashboardFile);
if (preg_match('/href="' . preg_quote($base, '/') . '\/libros\/(\d+)"/', $dashboardHtml, $m)) {
    $bookId = $m[1];
    savePage($base, "$base/libros/$bookId", "$outDir/editor.html");
    savePage($base, "$base/libros/nuevo", "$outDir/create.html");
} else {
    echo "AVISO: no se encontró ningún libro en el dashboard\n";
}
