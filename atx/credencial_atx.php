<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/atx.php';
requireLogin();

global $pdo;
$pageTitle = 'Credencial ATX';
$activePage = 'atx';

function atxCredentialEscape(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$sessionUserId = currentUserId();
ensureAtxCredentialTable($pdo);
$users = isAdmin() ? getUsers($pdo) : [getUser($pdo, $sessionUserId)];
$selectedId = isAdmin()
    ? (int)($_POST['user_id'] ?? $_GET['user'] ?? $sessionUserId)
    : $sessionUserId;
if ($selectedId <= 0) $selectedId = $sessionUserId;
$selectedUser = getUser($pdo, $selectedId);
if (!$selectedUser || ($selectedUser['status'] ?? '') !== 'active' || (!isAdmin() && $selectedId !== $sessionUserId)) {
    http_response_code(403);
    exit('Usuário inválido ou acesso negado.');
}

$credencial = '';
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) { http_response_code(403); exit('Token CSRF inválido. Atualize a página e tente novamente.'); }
    try {
        $credencial = rotateAtxCredential($pdo, $selectedId);
        logActivity($pdo, 'Gerou credencial ATX', 'ATX', 'Credencial ATX rotacionada para usuário ID ' . $selectedId . '.', $sessionUserId);
    } catch (Throwable $e) {
        error_log('Credencial ATX: ' . $e->getMessage());
        $erro = 'Não foi possível gerar a credencial ATX.';
    }
}

$stmt = $pdo->prepare('SELECT credential_preview, updated_at FROM atx_credentials WHERE user_id = ? LIMIT 1');
$stmt->execute([$selectedId]);
$credentialInfo = $stmt->fetch() ?: null;
$apiUrl = rtrim(SITE_URL, '/') . '/api/atx.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= atxCredentialEscape($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= atxCredentialEscape(appUrl('/assets/css/style.css?v=1')) ?>">
</head>
<body>
<div class="dashboard">
    <?php require __DIR__ . '/../includes/menu.php'; ?>
    <main class="main-content">
        <?php require __DIR__ . '/../includes/header.php'; ?>
        <div class="container">
            <div class="card">
                <div class="card-header"><h2><?= atxCredentialEscape($pageTitle) ?></h2></div>
                <div class="card-body">
                    <p>Gere uma credencial exclusiva para o usuário selecionado. O texto gerado deve ser colocado em <code>assets/config/credential.txt</code> do APK ATX. Gerar outra credencial invalida a anterior para esse usuário.</p>
                    <?php if ($erro !== ''): ?><div class="error"><?= atxCredentialEscape($erro) ?></div><?php endif; ?>

                    <form method="post">
<?= csrfField() ?>
                        <?php if (function_exists('csrfField')) echo csrfField(); ?>
                        <?php if (isAdmin()): ?>
                            <div class="form-group">
                                <label for="user_id">Cliente</label>
                                <select id="user_id" name="user_id" class="form-control" onchange="location.href='?user='+this.value" required>
                                    <?php foreach ($users as $u): if (!$u) continue; ?>
                                        <option value="<?= (int)$u['id'] ?>" <?= $selectedId === (int)$u['id'] ? 'selected' : '' ?>><?= atxCredentialEscape($u['username'] ?? ('ID ' . $u['id'])) ?> (ID <?= (int)$u['id'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <p><strong>Cliente:</strong> <?= atxCredentialEscape($selectedUser['username']) ?></p>
                            <input type="hidden" name="user_id" value="<?= $selectedId ?>">
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">Gerar / renovar credencial ATX</button>
                    </form>

                    <?php if ($credentialInfo): ?>
                        <hr>
                        <p><strong>Credencial cadastrada:</strong> <?= atxCredentialEscape($credentialInfo['credential_preview']) ?></p>
                        <p><strong>Última alteração:</strong> <?= atxCredentialEscape($credentialInfo['updated_at']) ?></p>
                    <?php else: ?>
                        <p class="text-muted">Este usuário ainda não possui uma credencial ATX.</p>
                    <?php endif; ?>

                    <?php if ($credencial !== ''): ?>
                        <hr>
                        <div class="form-group">
                            <label for="resultado">Conteúdo de credential.txt — copie agora, pois o token puro não é armazenado no banco</label>
                            <textarea id="resultado" class="form-control" rows="3" readonly><?= atxCredentialEscape($credencial . PHP_EOL) ?></textarea>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="baixarCredential()">Baixar credential.txt</button>
                        <button type="button" class="btn btn-info" onclick="copiarCredential()">Copiar credencial</button>
                        <script>
                        function baixarCredential() {
                            const blob = new Blob([document.getElementById('resultado').value], {type:'text/plain;charset=utf-8'});
                            const url = URL.createObjectURL(blob);
                            const a = document.createElement('a'); a.href = url; a.download = 'credential.txt';
                            document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
                        }
                        function copiarCredential() {
                            const valor = document.getElementById('resultado').value.trim();
                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                navigator.clipboard.writeText(valor).then(() => alert('Credencial copiada.'));
                            } else {
                                const campo = document.getElementById('resultado'); campo.select();
                                document.execCommand('copy'); alert('Credencial copiada.');
                            }
                        }
                        </script>
                    <?php endif; ?>
                    <hr>
                    <p><strong>Endpoint ATX:</strong> <code><?= atxCredentialEscape($apiUrl) ?></code></p>
                    <p class="text-muted">O APK envia o conteúdo de credential.txt pelo parâmetro legado <code>?URL=</code>. A API deve validar a credencial e não utilizar usuário padrão como fallback.</p>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="<?= atxCredentialEscape(appUrl('/assets/js/script.js')) ?>"></script>
</body>
</html>