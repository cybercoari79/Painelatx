<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/atx.php';
requireLogin();
if (!isAdmin()) { http_response_code(403); exit('Acesso permitido somente ao administrador.'); }

global $pdo;
$pageTitle = 'Cadastrar cliente';
$activePage = 'cadastro';
$erro = '';
$sucesso = '';
$credencialGerada = '';
$usuarioCriado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        http_response_code(403);
        exit('Token de segurança inválido. Atualize a página e tente novamente.');
    }
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $status = (string)($_POST['status'] ?? 'active');
    if (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $username)) {
        $erro = 'O usuário deve ter de 3 a 100 caracteres: letras, números, ponto, hífen ou sublinhado.';
    } elseif (strtolower($username) === 'admin') {
        $erro = 'Esse nome é reservado para a conta administrativa.';
    } elseif (strlen($password) < 8) {
        $erro = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
        $erro = 'Status inválido.';
    } else {
        try {
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $check->execute([$username]);
            if ($check->fetchColumn()) {
                $erro = 'Esse nome de usuário já existe.';
            } else {
                // Criações/alterações de tabelas podem encerrar transações no MariaDB.
                // Garanta as tabelas antes de iniciar a transação do cadastro.
                ensureAtxTables($pdo);
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, status) VALUES (?, ?, 'user', ?)");
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $status]);
                $newId = (int)$pdo->lastInsertId();
                // Cria a configuração ATX inicial vinculada exclusivamente ao novo cliente.
                $cfg = [
                    'version'=>'0','app_name'=>'ATXMOD','release_notes'=>'','update_url'=>'','contato'=>'','logo'=>'','fundo'=>'',
                    'check_pop'=>'true','cor_menu'=>'#000000','cor_btcu'=>'#ff0000','webview'=>'','roteador'=>'true','status_if'=>'false',
                    'icon_cor'=>'#ffffff','html_webview'=>'','html_use'=>'false','html_app'=>'','background_cor'=>'#000000',
                    'background_borda'=>35,'border_cor'=>'#ff0000','border_borda'=>14
                ];
                if (!saveAtxConfig($pdo, $newId, $cfg)) throw new RuntimeException('Não foi possível criar a configuração ATX inicial.');
                $pdo->commit();
                if ($status === 'active') {
                    $credencialGerada = rotateAtxCredential($pdo, $newId);
                }
                $usuarioCriado = $username;
                $sucesso = 'Cliente cadastrado como usuário comum. Ele não recebeu permissão administrativa.';
                logActivity($pdo, 'Cadastrou cliente ATX', 'Usuários', 'Cliente ' . $username . ' cadastrado.', currentUserId());
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Cadastro ATX: ' . $e->getMessage());
            $erro = 'Não foi possível cadastrar o cliente. Verifique o log do servidor.';
        }
    }
}
function cadEsc(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= cadEsc($pageTitle) ?></title><link rel="stylesheet" href="<?= cadEsc(appUrl('/assets/css/style.css')) ?>"></head>
<body><div class="dashboard"><?php require __DIR__ . '/../includes/menu.php'; ?><main class="main-content"><?php require __DIR__ . '/../includes/header.php'; ?><div class="container"><div class="card"><div class="card-header"><h2>Novo cliente ATX</h2></div><div class="card-body">
<?php if ($erro !== ''): ?><div class="error"><?= cadEsc($erro) ?></div><?php endif; ?>
<?php if ($sucesso !== ''): ?><div class="success"><?= cadEsc($sucesso) ?></div><p><strong>Usuário:</strong> <?= cadEsc($usuarioCriado) ?></p><?php if ($credencialGerada !== ''): ?><p><strong>Credencial ATX:</strong> copie agora; ela não será exibida novamente.</p><textarea id="credencial" class="form-control" rows="3" readonly><?= cadEsc($credencialGerada) ?></textarea><button type="button" class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('credencial').value).then(()=>alert('Credencial copiada.'))">Copiar credencial</button><p>Coloque esse valor no arquivo <code>assets/config/credential.txt</code> do APK do cliente.</p><?php else: ?><p>O cliente está inativo/suspenso; nenhuma credencial foi gerada.</p><?php endif; ?><hr><?php endif; ?>
<form method="post" autocomplete="off"><?= csrfField() ?><div class="form-group"><label for="username">Nome de usuário</label><input class="form-control" id="username" name="username" minlength="3" maxlength="100" pattern="[A-Za-z0-9_.-]+" required></div><div class="form-group"><label for="password">Senha inicial (mínimo 8 caracteres)</label><input class="form-control" type="password" id="password" name="password" minlength="8" required autocomplete="new-password"></div><div class="form-group"><label for="status">Status</label><select class="form-control" id="status" name="status"><option value="active">Ativo</option><option value="inactive">Inativo</option><option value="suspended">Suspenso</option></select></div><button type="submit" class="btn btn-primary">Cadastrar cliente</button></form>
<p class="text-muted">A página só pode ser acessada pela conta administrativa <strong>admin</strong>. Clientes sempre são criados com perfil <code>user</code>.</p></div></div></div></main></div><script src="<?= cadEsc(appUrl('/assets/js/script.js')) ?>"></script></body></html>