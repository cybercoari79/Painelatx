<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/atx.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

try {
    $credential = trim(
        (string) ($_GET['URL'] ?? $_GET['token'] ?? '')
    );

    if ($credential === '') {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Credencial ATX não informada.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $user = findUserByAtxCredential($pdo, $credential);

    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Credencial ATX inválida ou usuário inativo.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $userId = (int) $user['id'];
    $cfg = getAtxConfig($pdo, $userId);

    $message = trim((string) ($cfg['release_notes'] ?? ''));

    // Sem mensagem publicada: não gerar uma nova notificação.
    if ($message === '') {
        echo json_encode([
            'SendMessage' => 0,
            'MyMessage' => ''
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Identificador estável: muda quando o texto muda.
    // Limitado a um inteiro positivo compatível com o Android.
    $messageId = hexdec(substr(hash('sha256', $message), 0, 7));
    if ($messageId === 0) {
        $messageId = 1;
    }

    echo json_encode([
        'SendMessage' => $messageId,
        'MyMessage' => $message
    ], JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Throwable $e) {
    error_log('SMS ATX: ' . $e->getMessage());
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Erro ao consultar a mensagem ATX.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}