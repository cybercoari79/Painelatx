<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/atx.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

try {
    $credential = trim((string)($_GET['URL'] ?? $_GET['token'] ?? ''));
    if ($credential === '') {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Credencial ATX não informada.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $user = findUserByAtxCredential($pdo, $credential);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Credencial ATX inválida ou usuário inativo.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $userId = (int)$user['id'];
    $cfg = getAtxConfig($pdo, $userId);
    $servers = array_map('buildAtxServerFromRow', getAtxServers($pdo, $userId));
    $output = [
        'Version' => (string)($cfg['version'] ?? '0'),
        'AppName' => (string)($cfg['app_name'] ?? 'ATXMOD'),
        'ReleaseNotes' => !empty($cfg['release_notes']) ? $cfg['release_notes'] : null,
        'UrlContato' => (string)($cfg['contato'] ?? ''),
        'UrlUpdate' => (string)($cfg['update_url'] ?? ''),
        'Logo' => (string)($cfg['logo'] ?? ''),
        'Fundo' => (string)($cfg['fundo'] ?? ''),
        'CheckPop' => (string)($cfg['check_pop'] ?? 'true'),
        'CorMenu' => (string)($cfg['cor_menu'] ?? '#000000'),
        'CorBtcu' => (string)($cfg['cor_btcu'] ?? '#ff0000'),
        'WebView' => (string)($cfg['webview'] ?? ''),
        'Roteador' => (string)($cfg['roteador'] ?? 'true'),
        'StatusIf' => (string)($cfg['status_if'] ?? 'false'),
        'IconCor' => (string)($cfg['icon_cor'] ?? '#ffffff'),
        'Html' => ['WebView' => (string)($cfg['html_webview'] ?? ''), 'HtmlUse' => (string)($cfg['html_use'] ?? 'false'), 'HtmlAPP' => (string)($cfg['html_app'] ?? '')],
        'Background' => ['Cor' => (string)($cfg['background_cor'] ?? '#000000'), 'Borda' => (int)($cfg['background_borda'] ?? 35)],
        'Border' => ['Cor' => (string)($cfg['border_cor'] ?? '#ff0000'), 'Borda' => (int)($cfg['border_borda'] ?? 14)],
        'Servers' => $servers,
    ];
    echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('API ATX: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao gerar a configuração ATX.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}