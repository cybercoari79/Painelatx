<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/atx.php';

requireLogin();

global $pdo;

$activePage = 'atx';
$pageTitle  = 'Servidores ATXMOD';
$uid        = currentUserId();

function atxe(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function atxurl(string $v, string $label): string
{
    $v = trim($v);
    if ($v !== '' && !filter_var($v, FILTER_VALIDATE_URL)) {
        throw new RuntimeException("URL inválida: {$label}.");
    }
    return $v;
}

function atxcolor(string $v, string $label): string
{
    $v = strtoupper(trim($v));
    if (!preg_match('/^#[0-9A-F]{6}(?:[0-9A-F]{2})?$/', $v)) {
        throw new RuntimeException("Cor inválida em {$label}. Use #RRGGBB ou #AARRGGBB.");
    }
    return $v;
}

ensureAtxTables($pdo);

$users = isAdmin() ? getUsers($pdo) : [getUser($pdo, $uid)];
$selectedId = isAdmin()
    ? (int)($_GET['user'] ?? $_POST['user_id'] ?? $uid)
    : $uid;

if ($selectedId <= 0) {
    $selectedId = $uid;
}

$user = getUser($pdo, $selectedId);
if (!$user || ($user['status'] ?? '') !== 'active' || (!isAdmin() && $selectedId !== $uid)) {
    http_response_code(403);
    exit('Usuário inválido ou acesso negado.');
}

$error = '';
$editId = (int)($_GET['edit_server'] ?? 0);
$editServer = null;

if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM atx_servers WHERE id = ? AND user_id = ? LIMIT 1');
    $st->execute([$editId, $selectedId]);
    $editServer = $st->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string)($_POST['action'] ?? 'save_server');

        if ($action === 'delete_server') {
            $id = (int)($_POST['server_id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Servidor inválido.');
            }

            $st = $pdo->prepare('DELETE FROM atx_servers WHERE id = ? AND user_id = ?');
            $st->execute([$id, $selectedId]);

            logActivity($pdo, 'Excluiu servidor ATX', 'ATX', 'Servidor ATX ID ' . $id . ' excluído.', $uid);
            header('Location: ' . appUrl('/atx/servers.php?user=' . $selectedId . '&saved=1'));
            exit;
        }

        if ($action === 'save_server') {
            $id = (int)($_POST['server_id'] ?? 0);
            $data = [
                trim((string)($_POST['name'] ?? '')),
                trim((string)($_POST['type'] ?? 'ssh')),
                atxurl((string)($_POST['flag_url'] ?? ''), 'Flag URL'),
                trim((string)($_POST['server_ip'] ?? '')),
                trim((string)($_POST['check_user'] ?? '')),
                trim((string)($_POST['server_port'] ?? '')),
                trim((string)($_POST['ssl_port'] ?? '')),
                trim((string)($_POST['username'] ?? '')),
                trim((string)($_POST['password'] ?? '')),
                trim((string)($_POST['payload'] ?? '')),
                trim((string)($_POST['sni'] ?? '')),
                trim((string)($_POST['tls_ip'] ?? '')),
                trim((string)($_POST['proxy_ip'] ?? '')),
                trim((string)($_POST['proxy_port'] ?? '')),
                trim((string)($_POST['info'] ?? 'Tlsws')),
                atxcolor((string)($_POST['color'] ?? '#ffffff'), 'Cor'),
            ];

            if ($data[0] === '' || $data[3] === '') {
                throw new RuntimeException('Nome e IP do servidor são obrigatórios.');
            }

            if ($id > 0) {
                $st = $pdo->prepare(
                    'UPDATE atx_servers
                        SET name=?, type=?, flag_url=?, server_ip=?, check_user=?,
                            server_port=?, ssl_port=?, username=?, password=?,
                            payload=?, sni=?, tls_ip=?, proxy_ip=?, proxy_port=?,
                            info=?, color=?
                      WHERE id=? AND user_id=?'
                );
                $st->execute([...$data, $id, $selectedId]);
                $msg = 'Editou servidor ATX';
            } else {
                $st = $pdo->prepare(
                    'INSERT INTO atx_servers
                        (name, type, flag_url, server_ip, check_user,
                         server_port, ssl_port, username, password,
                         payload, sni, tls_ip, proxy_ip, proxy_port,
                         info, color, user_id)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $st->execute([...$data, $selectedId]);
                $msg = 'Criou servidor ATX';
            }

            logActivity($pdo, $msg, 'ATX', 'Servidor ATX salvo.', $uid);
            header('Location: ' . appUrl('/atx/servers.php?user=' . $selectedId . '&saved=1'));
            exit;
        }
    } catch (Throwable $e) {
        error_log('ATX SERVERS: ' . $e->getMessage());
        $error = $e->getMessage();
        if ($editId > 0) {
            $st = $pdo->prepare('SELECT * FROM atx_servers WHERE id = ? AND user_id = ? LIMIT 1');
            $st->execute([$editId, $selectedId]);
            $editServer = $st->fetch() ?: null;
        }
    }
}

$servers = getAtxServers($pdo, $selectedId);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= atxe($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= atxe(appUrl('/assets/css/style.css?v=1')) ?>">
    <style>
        .atx-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
        .atx-full{grid-column:1/-1}
        .atx-section{margin-bottom:24px}
        .atx-section h2{margin:0 0 16px;color:#fff;font-size:20px}
        .atx-note{color:#8899aa;font-size:13px;margin:-6px 0 14px}
        .atx-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
        .atx-table{width:100%;border-collapse:collapse}
        .atx-table th,.atx-table td{padding:10px 8px;border-bottom:1px solid rgba(255,255,255,.08);text-align:left;vertical-align:top}
        .atx-table th{color:#aebdcc;font-size:12px;text-transform:uppercase}
        .atx-table td{color:#fff;font-size:13px}
        .atx-table-wrap{overflow-x:auto}
        .atx-color{display:grid;grid-template-columns:60px 1fr;gap:10px;align-items:center}
        .atx-color input[type=color]{width:60px;height:44px;margin:0;padding:3px}
        .atx-danger{color:#fff!important;background:#b42318!important;border:0}
        .atx-message{padding:12px 14px;border-radius:8px;margin-bottom:16px;background:var(--surface,#111827);border:1px solid var(--border,rgba(255,255,255,.1))}
        .atx-message.error{border-color:#b42318;color:#b42318}
        .atx-message.success{border-color:#16803c;color:#16803c}
        @media(max-width:768px){.atx-grid{grid-template-columns:1fr}.atx-full{grid-column:auto}.atx-table{min-width:900px}}
    </style>
</head>
<body>
<div class="dashboard">
    <?php require __DIR__ . '/../includes/menu.php'; ?>
    <main class="main-content">
        <?php require __DIR__ . '/../includes/header.php'; ?>

        <?php if (!empty($_GET['saved'])): ?>
            <div class="atx-message success">Servidor ATX salvo com sucesso.</div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="atx-message error"><?= atxe($error) ?></div>
        <?php endif; ?>

        <div class="container-form" style="max-width:1150px">
            <h1>Servidores <span>ATXMOD</span></h1>
            <p class="subtitle">Gerenciamento separado dos servidores do aplicativo ATXMOD.</p>

            <div class="atx-section">
                <h2>Cliente</h2>
                <div class="atx-grid">
                    <div>
                        <label>Usuário</label>
                        <select name="user_id" <?= !isAdmin() ? 'disabled' : '' ?> onchange="location.href='<?= atxe(appUrl('/atx/servers.php')) ?>?user='+this.value">
                            <?php foreach ($users as $u): if (!$u) continue; ?>
                                <option value="<?= (int)$u['id'] ?>" <?= $selectedId === (int)$u['id'] ? 'selected' : '' ?>><?= atxe($u['username'] ?? ('ID ' . $u['id'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Configuração</label>
                        <div class="actions">
                            <a class="btn btn-secondary" href="<?= atxe(appUrl('/atx/index.php?user=' . $selectedId)) ?>">Voltar para configuração</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="atx-section">
                <h2><?= $editServer ? 'Editar servidor ATX' : 'Adicionar servidor ATX' ?></h2>
                <p class="atx-note">Este formulário altera somente a tabela <code>atx_servers</code>.</p>
                <form method="post">
<?= csrfField() ?>
                    <input type="hidden" name="action" value="save_server">
                    <input type="hidden" name="user_id" value="<?= (int)$selectedId ?>">
                    <input type="hidden" name="server_id" value="<?= (int)($editServer['id'] ?? 0) ?>">
                    <div class="atx-grid">
                        <div><label>Nome</label><input name="name" value="<?= atxe($editServer['name'] ?? '') ?>" required></div>
                        <div><label>Tipo</label><input name="type" value="<?= atxe($editServer['type'] ?? 'ssh') ?>"></div>
                        <div><label>IP do servidor</label><input name="server_ip" value="<?= atxe($editServer['server_ip'] ?? '') ?>" required></div>
                        <div><label>Flag URL</label><input type="url" name="flag_url" value="<?= atxe($editServer['flag_url'] ?? '') ?>"></div>
                        <div><label>CheckUser</label><input name="check_user" value="<?= atxe($editServer['check_user'] ?? '') ?>"></div>
                        <div><label>Server Port</label><input name="server_port" value="<?= atxe($editServer['server_port'] ?? '') ?>"></div>
                        <div><label>SSL Port</label><input name="ssl_port" value="<?= atxe($editServer['ssl_port'] ?? '') ?>"></div>
                        <div><label>Usuário</label><input name="username" value="<?= atxe($editServer['username'] ?? '') ?>"></div>
                        <div><label>Senha</label><input type="password" name="password" value="<?= atxe($editServer['password'] ?? '') ?>"></div>
                        <div class="atx-full"><label>Payload</label><textarea name="payload" rows="4"><?= atxe($editServer['payload'] ?? '') ?></textarea></div>
                        <div><label>SNI</label><input name="sni" value="<?= atxe($editServer['sni'] ?? '') ?>"></div>
                        <div><label>TLS IP</label><input name="tls_ip" value="<?= atxe($editServer['tls_ip'] ?? '') ?>"></div>
                        <div><label>Proxy IP</label><input name="proxy_ip" value="<?= atxe($editServer['proxy_ip'] ?? '') ?>"></div>
                        <div><label>Proxy Port</label><input name="proxy_port" value="<?= atxe($editServer['proxy_port'] ?? '') ?>"></div>
                        <div><label>Info</label><input name="info" value="<?= atxe($editServer['info'] ?? 'Tlsws') ?>"></div>
                        <div>
                            <label>Cor</label>
                            <div class="atx-color">
                                <?php $serverColor=(string)($editServer['color'] ?? '#ffffff'); $serverPicker=preg_match('/^#[0-9A-Fa-f]{6}$/',$serverColor)?strtoupper($serverColor):'#FFFFFF'; ?>
                                <input type="color" value="<?= atxe($serverPicker) ?>" oninput="this.nextElementSibling.value=this.value.toUpperCase()">
                                <input type="text" name="color" value="<?= atxe($serverColor) ?>" maxlength="9">
                            </div>
                        </div>
                    </div>
                    <div class="actions" style="margin-top:16px">
                        <button type="submit" class="btn btn-save"><?= $editServer ? 'Salvar alteração' : 'Adicionar servidor' ?></button>
                        <?php if ($editServer): ?>
                            <a class="btn btn-secondary" href="<?= atxe(appUrl('/atx/servers.php?user=' . $selectedId)) ?>">Cancelar edição</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="atx-section">
                <h2>Servidores cadastrados</h2>
                <div class="atx-table-wrap">
                    <table class="atx-table">
                        <thead><tr><th>ID</th><th>Nome</th><th>Tipo</th><th>IP</th><th>Porta</th><th>SSL</th><th>Info</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php if (!$servers): ?>
                            <tr><td colspan="8">Nenhum servidor ATX cadastrado.</td></tr>
                        <?php else: foreach ($servers as $server): ?>
                            <tr>
                                <td><?= (int)$server['id'] ?></td>
                                <td><?= atxe($server['name']) ?></td>
                                <td><?= atxe($server['type']) ?></td>
                                <td><?= atxe($server['server_ip']) ?></td>
                                <td><?= atxe($server['server_port']) ?></td>
                                <td><?= atxe($server['ssl_port']) ?></td>
                                <td><?= atxe($server['info']) ?></td>
                                <td>
                                    <div class="atx-actions">
                                        <a class="btn btn-sm btn-edit" href="<?= atxe(appUrl('/atx/servers.php?user=' . $selectedId . '&edit_server=' . (int)$server['id'])) ?>">Editar</a>
                                        <form method="post" onsubmit="return confirm('Excluir este servidor ATX?');" style="display:inline">
<?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete_server">
                                            <input type="hidden" name="user_id" value="<?= (int)$selectedId ?>">
                                            <input type="hidden" name="server_id" value="<?= (int)$server['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-delete">Excluir</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="<?= atxe(appUrl('/assets/js/script.js')) ?>"></script>
</body>
</html>