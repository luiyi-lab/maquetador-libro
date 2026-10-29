<?php
// Prueba HTTP end-to-end: registro real con cookies + CSRF y dashboard autenticado.
// Uso: iniciar `php artisan serve` y luego `php scripts/verificar_http.php [base-url]`
$base = $argv[1] ?? 'http://127.0.0.1:8010';
$cookieJar = sys_get_temp_dir() . '/maquetador_cookies.txt';

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

// 0. Registrar un usuario vía HTTP
[$status, $headers, $body] = request("$base/register");
preg_match('/name="_token" value="([^"]+)"/', $body, $m);
$token = $m[1] ?? '';
echo "GET /register: $status (CSRF: " . ($token !== '' ? 'OK' : 'NO') . ")\n";

$uniq = uniqid();
[$status] = request("$base/register", [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_token' => $token,
        'name' => "Usuario $uniq",
        'email' => "user_$uniq@test.local",
        'password' => 'password1234',
        'password_confirmation' => 'password1234',
    ]),
], ['Content-Type: application/x-www-form-urlencoded']);
echo "POST /register: $status (redirect esperado: 302)\n";

// 1. Dashboard autenticado
[$status, $headers, $body] = request("$base/", [], ['Accept: text/html']);
echo "GET /: $status\n";
echo "Dashboard renderiza \"Mis libros\": " . (str_contains($body, 'Mis libros') ? 'SÍ' : 'NO') . "\n";
echo "Botón nuevo libro: " . (str_contains($body, 'Nuevo libro') ? 'SÍ' : 'NO') . "\n";
