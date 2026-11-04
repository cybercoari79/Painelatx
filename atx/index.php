<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/atx.php';

requireLogin();

global $pdo;

$activePage = 'atx';
$pageTitle  = 'Configurações ATX';
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

$cfg     = getAtxConfig($pdo, $selectedId);
$servers = getAtxServers($pdo, $selectedId);
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string)($_POST['action'] ?? 'save_config');

        $post = static fn(string $key, string $default = ''): string => trim((string)($_POST[$key] ?? $default));

        $bd = (int)$post('background_borda', (string)($cfg['background_borda'] ?? 35));
        $br = (int)$post('border_borda', (string)($cfg['border_borda'] ?? 14));
        if ($bd < 0 || $bd > 100 || $br < 0 || $br > 100) {
            throw new RuntimeException('Os valores de borda devem ficar entre 0 e 100.');
        }

        $data = [
            'version'          => $post('version', '0'),
            'app_name'         => $post('app_name', 'ATXMOD'),
            'release_notes'    => $post('release_notes'),
            'update_url'       => atxurl($post('update_url'), 'UrlUpdate'),
            'contato'          => atxurl($post('contato'), 'UrlContato'),
            'logo'             => atxurl($post('logo'), 'Logo'),
            'fundo'            => atxurl($post('fundo'), 'Fundo'),
            'check_pop'        => $post('check_pop', 'true') === 'false' ? 'false' : 'true',
            'cor_menu'         => atxcolor($post('cor_menu', '#000000'), 'CorMenu'),
            'cor_btcu'         => atxcolor($post('cor_btcu', '#ff0000'), 'CorBtcu'),
            'webview'          => atxurl($post('webview'), 'WebView'),
            'roteador'         => $post('roteador', 'true') === 'false' ? 'false' : 'true',
            'status_if'        => $post('status_if', 'false') === 'true' ? 'true' : 'false',
            'icon_cor'         => atxcolor($post('icon_cor', '#ffffff'), 'IconCor'),
            'html_webview'     => atxurl($post('html_webview'), 'Html.WebView'),
            'html_use'         => $post('html_use', 'false') === 'true' ? 'true' : 'false',
            'html_app'         => $post('html_app'),
            'background_cor'   => atxcolor($post('background_cor', '#000000'), 'Background.Cor'),
            'background_borda' => $bd,
            'border_cor'       => atxcolor($post('border_cor', '#ff0000'), 'Border.Cor'),
            'border_borda'     => $br,
        ];

        saveAtxConfig($pdo, $selectedId, $data);
        logActivity($pdo, 'Atualizou configuração ATX', 'ATX', 'Configuração ATX atualizada.', $uid);

        header('Location: ' . appUrl('/atx/index.php?user=' . $selectedId . '&saved=1'));
        exit;
    } catch (Throwable $e) {
        error_log('ATX: ' . $e->getMessage());
        $error = $e->getMessage();
        $cfg = getAtxConfig($pdo, $selectedId);
        $servers = getAtxServers($pdo, $selectedId);
    }
}

$apiUrl = appUrl('/api/atx.php');
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
        @media(max-width:768px){.atx-grid{grid-template-columns:1fr}.atx-full{grid-column:auto}.atx-table{min-width:900px}}
    </style>
</head>
<body>
<div class="dashboard">
    <?php require __DIR__ . '/../includes/menu.php'; ?>
    <main class="main-content">
        <?php require __DIR__ . '/../includes/header.php'; ?>

        <?php if (!empty($_GET['saved'])): ?>
            <div class="success">Configuração ATX salva com sucesso.</div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="error"><?= atxe($error) ?></div>
        <?php endif; ?>

        <div class="container-form" style="max-width:1150px">
            <h1>Configuração <span>ATX</span></h1>
            <p class="subtitle">Configuração exclusiva do aplicativo ATXMOD.</p>

            <form method="post">
<?= csrfField() ?>
                <input type="hidden" name="action" value="save_config">
                <?php if (isAdmin()): ?><input type="hidden" name="user_id" value="<?= (int)$selectedId ?>"><?php endif; ?>

                <div class="atx-section">
                    <h2>Cliente e API</h2>
                    <div class="atx-grid">
                        <div>
                            <label>Usuário</label>
                            <select name="user_id" <?= !isAdmin() ? 'disabled' : '' ?> onchange="location.href='<?= atxe(appUrl('/atx/index.php')) ?>?user='+this.value">
                                <?php foreach ($users as $u): if (!$u) continue; ?>
                                    <option value="<?= (int)$u['id'] ?>" <?= $selectedId === (int)$u['id'] ? 'selected' : '' ?>><?= atxe($u['username'] ?? ('ID ' . $u['id'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>API ATX</label>
                            <input value="<?= atxe($apiUrl) ?>" readonly>
                        </div>
                    </div>
                </div>

                <div class="atx-section">
                    <h2>Configuração principal</h2>
                    <div class="atx-grid">
                        <div><label>AppName</label><input name="app_name" value="<?= atxe($cfg['app_name'] ?? 'ATXMOD') ?>" required></div>
                        <div><label>Version</label><input name="version" value="<?= atxe($cfg['version'] ?? '0') ?>" required></div>
                        <div><label>UrlUpdate</label><input type="url" name="update_url" value="<?= atxe($cfg['update_url'] ?? $apiUrl) ?>"></div>
                        <div><label>UrlContato</label><input type="url" name="contato" value="<?= atxe($cfg['contato'] ?? '') ?>"></div>
                        <div><label>Logo</label><input type="url" name="logo" value="<?= atxe($cfg['logo'] ?? '') ?>"></div>
                        <div><label>Fundo</label><input type="url" name="fundo" value="<?= atxe($cfg['fundo'] ?? '') ?>"></div>
                        <div><label>CheckPop</label><select name="check_pop"><option value="true" <?= ($cfg['check_pop'] ?? 'true') === 'true' ? 'selected' : '' ?>>true</option><option value="false" <?= ($cfg['check_pop'] ?? '') === 'false' ? 'selected' : '' ?>>false</option></select></div>
                        <div><label>WebView</label><input type="url" name="webview" value="<?= atxe($cfg['webview'] ?? '') ?>"></div>
                        <div><label>Roteador</label><select name="roteador"><option value="true" <?= ($cfg['roteador'] ?? 'true') === 'true' ? 'selected' : '' ?>>true</option><option value="false" <?= ($cfg['roteador'] ?? '') === 'false' ? 'selected' : '' ?>>false</option></select></div>
                        <div><label>StatusIF</label><select name="status_if"><option value="true" <?= ($cfg['status_if'] ?? '') === 'true' ? 'selected' : '' ?>>true</option><option value="false" <?= ($cfg['status_if'] ?? 'false') === 'false' ? 'selected' : '' ?>>false</option></select></div>
                    </div>
                </div>

                <div class="atx-section">
                    <h2>HTML e aparência</h2>
                    <div class="atx-grid">
                        <?php
                        $colors = [
                            'cor_menu' => 'CorMenu',
                            'cor_btcu' => 'CorBtcu',
                            'icon_cor' => 'IconCor',
                            'background_cor' => 'Background.Cor',
                            'border_cor' => 'Border.Cor',
                        ];
                        foreach ($colors as $key => $label):
                            $value = (string)($cfg[$key] ?? '#ffffff');
                            $picker = preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? strtoupper($value) : '#FFFFFF';
                        ?>
                            <div>
                                <label><?= atxe($label) ?></label>
                                <div class="atx-color">
                                    <input type="color" value="<?= atxe($picker) ?>" oninput="this.nextElementSibling.value=this.value.toUpperCase()">
                                    <input type="text" name="<?= atxe($key) ?>" value="<?= atxe($value) ?>" maxlength="9">
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div><label>Background.Borda</label><input type="number" min="0" max="100" name="background_borda" value="<?= (int)($cfg['background_borda'] ?? 35) ?>"></div>
                        <div><label>Border.Borda</label><input type="number" min="0" max="100" name="border_borda" value="<?= (int)($cfg['border_borda'] ?? 14) ?>"></div>
                        <div><label>Html.WebView</label><input type="url" name="html_webview" value="<?= atxe($cfg['html_webview'] ?? '') ?>"></div>
                        <div><label>Html.Use</label><select name="html_use"><option value="true" <?= ($cfg['html_use'] ?? '') === 'true' ? 'selected' : '' ?>>true</option><option value="false" <?= ($cfg['html_use'] ?? 'false') === 'false' ? 'selected' : '' ?>>false</option></select></div>
                        <div class="atx-full"><label>Html.App</label><textarea name="html_app" rows="5"><?= atxe($cfg['html_app'] ?? '') ?></textarea></div>
                    </div>
                </div>

                <div class="atx-section">
                    <h2>Release Notes</h2>
                    <textarea name="release_notes" rows="5" placeholder="Notas da configuração ATX."><?= atxe($cfg['release_notes'] ?? '') ?></textarea>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-save">Salvar configuração ATX</button>
                    <a class="btn btn-secondary" href="<?= atxe(appUrl('/api/atx.php')) ?>">📄 Visualizar Config.json</a>
                </div>
            </form>

            <div class="atx-section" style="margin-top:30px">
                <h2>Servidores ATXMOD</h2>
                <p class="atx-note">O gerenciamento dos servidores fica separado da configuração do aplicativo.</p>
                <div class="actions">
                    <a class="btn btn-save" href="<?= atxe(appUrl('/atx/servers.php?user=' . $selectedId)) ?>">Gerenciar servidores</a>
                </div>
            </div>

        </div>
    </main>
</div>
<script src="<?= atxe(appUrl('/assets/js/script.js')) ?>"></script>
</body>
</html>