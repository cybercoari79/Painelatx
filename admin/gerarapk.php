<?php
declare(strict_types=1);

/*
 * ATX 3.0 - Gerador de APK Personalizado
 * Instale como: public_html/admin/gerarapk_personalizado.php
 * Arquivo independente: não substitui gerarapk.php nem gerar_apk.php.
 * Requer PHP cURL, ZipArchive e o token ATX_GITHUB_TOKEN configurado com segurança.
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';
requireLogin();

function canGeneratePersonalizedAtxApk(PDO $pdo): bool {
    if (function_exists('isAdmin') && isAdmin()) return true;
    $userId = function_exists('currentUserId') ? (int)currentUserId() : 0;
    if ($userId <= 0) return false;
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM atx_servers WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        error_log('ATX personalized APK permission check: ' . $e->getMessage());
        return false;
    }
}
if (!isset($pdo) || !($pdo instanceof PDO) || !canGeneratePersonalizedAtxApk($pdo)) {
    http_response_code(403);
    exit('Acesso permitido somente ao administrador ou ao dono de um servidor ATX.');
}
if (!extension_loaded('curl') || !class_exists('ZipArchive')) {
    http_response_code(500);
    exit('O servidor precisa ter as extensões PHP cURL e ZipArchive habilitadas.');
}
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

const ATXP_GH_OWNER = 'cybecoari';
const ATXP_GH_REPO = 'atx-builder';
const ATXP_GH_WORKFLOW = 'build-apk-personalizado.yml';
const ATXP_GH_BRANCH = 'main';
const ATXP_GH_API = 'https://api.github.com';
const ATXP_ARTIFACT_NAME = 'atx-apk-personalizado';

function atxp_h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function atxp_token(): string {
    if (defined('ATX_GITHUB_TOKEN')) {
        $token = trim((string)constant('ATX_GITHUB_TOKEN'));
        if ($token !== '') return $token;
    }
    $token = trim((string)getenv('ATX_GITHUB_TOKEN'));
    if ($token !== '') return $token;
    $secretFile = dirname(__DIR__, 2) . '/atx-secrets/github-token.php';
    if (is_file($secretFile) && is_readable($secretFile)) {
        $loaded = require $secretFile;
        if (is_string($loaded) && trim($loaded) !== '') return trim($loaded);
    }
    throw new RuntimeException('Token GitHub não encontrado. Configure ATX_GITHUB_TOKEN no ambiente, em includes/config.local.php ou no arquivo privado atx-secrets/github-token.php.');
}
function atxp_path(string $suffix): string {
    return '/repos/' . ATXP_GH_OWNER . '/' . ATXP_GH_REPO . $suffix;
}
function atxp_request(string $method, string $path, ?array $body = null, bool $allow204 = false): array {
    $ch = curl_init(ATXP_GH_API . $path);
    $headers = [
        'Accept: application/vnd.github+json',
        'Authorization: Bearer ' . atxp_token(),
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: ATX-APK-Personalizado'
    ];
    if ($body !== null) $headers[] = 'Content-Type: application/json';
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_POSTFIELDS => $body === null ? null : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($errno) throw new RuntimeException('Falha de conexão com GitHub: ' . $err);
    if ($status === 204 && $allow204) return ['_status' => 204];
    $json = is_string($raw) ? json_decode($raw, true) : null;
    if ($status < 200 || $status >= 300 || !is_array($json)) {
        $message = is_array($json) ? (string)($json['message'] ?? '') : '';
        throw new RuntimeException('GitHub API retornou HTTP ' . $status . ($message ? ': ' . $message : '.'));
    }
    return $json;
}
function atxp_csrf_check(): void {
    $sent = (string)($_POST['csrf'] ?? '');
    if (!isset($_SESSION['atxp_apk_csrf']) || !hash_equals((string)$_SESSION['atxp_apk_csrf'], $sent)) {
        throw new RuntimeException('Sessão expirou. Atualize a página e tente novamente.');
    }
}
if (empty($_SESSION['atxp_apk_csrf'])) $_SESSION['atxp_apk_csrf'] = bin2hex(random_bytes(32));

$error = '';
$notice = '';
$appName = trim((string)($_POST['app_name'] ?? 'ATX Cliente'));
$packageName = trim((string)($_POST['package_name'] ?? 'com.atx.cliente'));
$runId = (int)($_SESSION['atxp_apk_run_id'] ?? 0);
$run = null;
$artifact = null;

try {
    $action = (string)($_POST['action'] ?? '');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        atxp_csrf_check();

        if ($action === 'start') {
            $appName = trim((string)($_POST['app_name'] ?? ''));
            $credential = trim((string)($_POST['credential'] ?? ''));
            $packageName = trim((string)($_POST['package_name'] ?? ''));

            if ($appName === '' || mb_strlen($appName) > 80 || preg_match('/[\r\n\0]/', $appName)) {
                throw new RuntimeException('Informe um nome válido de até 80 caracteres.');
            }
            if ($credential === '' || strlen($credential) > 200 || !preg_match('/^[A-Za-z0-9_.=\-]+$/', $credential)) {
                throw new RuntimeException('Credencial inválida. Use de 1 a 200 caracteres: letras, números, _, -, . ou =.');
            }
            if (!preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/', $packageName) || strlen($packageName) > 180) {
                throw new RuntimeException('Pacote inválido. Exemplo: com.cliente.app.');
            }
            if ($packageName === 'com.atx.tunnel') {
                throw new RuntimeException('Escolha um pacote diferente de com.atx.tunnel para gerar um APK separado.');
            }

            $before = atxp_request('GET', atxp_path('/actions/workflows/' . rawurlencode(ATXP_GH_WORKFLOW) . '/runs?branch=' . rawurlencode(ATXP_GH_BRANCH) . '&per_page=10'));
            $beforeIds = [];
            foreach (($before['workflow_runs'] ?? []) as $item) $beforeIds[] = (int)($item['id'] ?? 0);

            atxp_request('POST', atxp_path('/actions/workflows/' . rawurlencode(ATXP_GH_WORKFLOW) . '/dispatches'), [
                'ref' => ATXP_GH_BRANCH,
                'inputs' => [
                    'app_name' => $appName,
                    'credential' => $credential,
                    'package_name' => $packageName,
                ],
            ], true);

            $_SESSION['atxp_apk_before_ids'] = $beforeIds;
            $_SESSION['atxp_apk_started_at'] = time();
            unset($_SESSION['atxp_apk_run_id']);
            $runId = 0;
            $notice = 'Solicitação enviada ao GitHub. Aguarde alguns segundos e toque em “Atualizar status”.';
        } elseif ($action === 'refresh') {
            $runId = (int)($_SESSION['atxp_apk_run_id'] ?? 0);
            if ($runId <= 0) {
                $started = (int)($_SESSION['atxp_apk_started_at'] ?? 0);
                if ($started <= 0) throw new RuntimeException('Nenhuma compilação iniciada nesta sessão.');
                $runs = atxp_request('GET', atxp_path('/actions/workflows/' . rawurlencode(ATXP_GH_WORKFLOW) . '/runs?branch=' . rawurlencode(ATXP_GH_BRANCH) . '&per_page=10'));
                $beforeIds = $_SESSION['atxp_apk_before_ids'] ?? [];
                foreach (($runs['workflow_runs'] ?? []) as $item) {
                    $created = strtotime((string)($item['created_at'] ?? '')) ?: 0;
                    if (!in_array((int)($item['id'] ?? 0), $beforeIds, true) && $created >= $started - 90) {
                        $runId = (int)$item['id'];
                        $_SESSION['atxp_apk_run_id'] = $runId;
                        break;
                    }
                }
                if ($runId <= 0) $notice = 'O GitHub ainda está registrando a execução. Aguarde alguns segundos e atualize novamente.';
            }
        } elseif ($action === 'download') {
            $runId = (int)($_SESSION['atxp_apk_run_id'] ?? 0);
            if ($runId <= 0) throw new RuntimeException('Não há execução vinculada a esta sessão.');
            $run = atxp_request('GET', atxp_path('/actions/runs/' . $runId));
            if (($run['status'] ?? '') !== 'completed' || ($run['conclusion'] ?? '') !== 'success') {
                throw new RuntimeException('O APK só pode ser baixado após uma compilação concluída com sucesso.');
            }
            $arts = atxp_request('GET', atxp_path('/actions/runs/' . $runId . '/artifacts?name=' . rawurlencode(ATXP_ARTIFACT_NAME) . '&per_page=10'));
            foreach (($arts['artifacts'] ?? []) as $a) {
                if (($a['name'] ?? '') === ATXP_ARTIFACT_NAME && empty($a['expired'])) { $artifact = $a; break; }
            }
            if (!$artifact) throw new RuntimeException('Artefato personalizado não encontrado ou expirado.');

            $ch = curl_init(ATXP_GH_API . atxp_path('/actions/artifacts/' . (int)$artifact['id'] . '/zip'));
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    'Accept: application/vnd.github+json',
                    'Authorization: Bearer ' . atxp_token(),
                    'X-GitHub-Api-Version: 2022-11-28',
                    'User-Agent: ATX-APK-Personalizado'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 90,
            ]);
            $zipData = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if (!is_string($zipData) || $status !== 200) throw new RuntimeException('Falha ao baixar artefato do GitHub. HTTP ' . $status . ($err ? ' — ' . $err : ''));

            $tmp = tempnam(sys_get_temp_dir(), 'atxp_');
            if ($tmp === false) throw new RuntimeException('Não foi possível criar arquivo temporário.');
            if (file_put_contents($tmp, $zipData) === false) { @unlink($tmp); throw new RuntimeException('Falha ao salvar ZIP temporário.'); }
            unset($zipData);
            $zip = new ZipArchive();
            if ($zip->open($tmp) !== true) { @unlink($tmp); throw new RuntimeException('Artefato ZIP inválido.'); }
            $apkContent = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat && basename((string)$stat['name']) === 'atx-signed.apk') {
                    $stream = $zip->getStream((string)$stat['name']);
                    if ($stream) { $apkContent = stream_get_contents($stream); fclose($stream); }
                    break;
                }
            }
            $zip->close();
            @unlink($tmp);
            if (!is_string($apkContent) || $apkContent === '') throw new RuntimeException('atx-signed.apk não encontrado no artefato.');
            header('Content-Type: application/vnd.android.package-archive');
            header('Content-Disposition: attachment; filename="atx-personalizado.apk"');
            header('Content-Length: ' . strlen($apkContent));
            header('X-Content-Type-Options: nosniff');
            echo $apkContent;
            exit;
        }
    }

    $runId = (int)($_SESSION['atxp_apk_run_id'] ?? $runId);
    if ($runId > 0) {
        $run = atxp_request('GET', atxp_path('/actions/runs/' . $runId));
        if (($run['status'] ?? '') === 'completed' && ($run['conclusion'] ?? '') === 'success') {
            $arts = atxp_request('GET', atxp_path('/actions/runs/' . $runId . '/artifacts?name=' . rawurlencode(ATXP_ARTIFACT_NAME) . '&per_page=10'));
            foreach (($arts['artifacts'] ?? []) as $a) {
                if (($a['name'] ?? '') === ATXP_ARTIFACT_NAME && empty($a['expired'])) { $artifact = $a; break; }
            }
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$pageTitle = 'Gerar APK Personalizado ATX';
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= atxp_h($pageTitle) ?></title>
<style>
:root{color-scheme:dark;--bg:#0b1020;--panel:#131b2e;--line:#26344e;--text:#eef3ff;--muted:#a7b5ce;--accent:#6d8cff;--bad:#ff7b86}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:16px/1.5 system-ui,-apple-system,Segoe UI,sans-serif}.wrap{max-width:900px;margin:32px auto;padding:0 16px}.top{margin-bottom:22px}.eyebrow{color:#9eb2ff;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}h1{font-size:clamp(26px,5vw,36px);margin:8px 0}p{color:var(--muted)}.card{background:var(--panel);border:1px solid var(--line);border-radius:18px;padding:22px;margin:16px 0}.field{display:grid;gap:7px;margin:16px 0}label{font-weight:700}input{width:100%;border:1px solid #354663;border-radius:10px;padding:13px 14px;background:#0c1425;color:var(--text);font:inherit}input:focus{outline:2px solid var(--accent);border-color:transparent}.hint{font-size:13px;color:var(--muted)}button{border:0;border-radius:10px;padding:12px 16px;background:var(--accent);color:white;font:inherit;font-weight:750;cursor:pointer}.secondary{background:#273650}.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.alert{padding:12px 14px;border-radius:10px;margin:12px 0;border:1px solid var(--line)}.error{border-color:#7e3947;background:#351b27;color:#ffd7dc}.notice{border-color:#315e53;background:#132e2a;color:#c8ffea}.status{display:inline-block;border-radius:999px;padding:4px 9px;background:#263650;font-size:13px;font-weight:750}.ok{background:#124834;color:#b6ffe0}.bad{background:#51252d;color:#ffd4d8}dl{display:grid;grid-template-columns:140px 1fr;gap:8px;margin:14px 0}dt{color:var(--muted)}dd{margin:0;overflow-wrap:anywhere}.foot{font-size:12px;color:var(--muted);margin-top:20px}@media(max-width:560px){.wrap{margin:16px auto}.card{padding:16px}dl{grid-template-columns:1fr;gap:2px}dd{margin-bottom:8px}}
</style>
</head>
<body>
<div class="wrap">
<header class="top">
<div class="eyebrow">ATX 3.0 · Administração</div>
<h1>Gerar APK personalizado</h1>
<p>Gere um APK com nome, credencial e pacote Android próprios. O gerador original não será alterado.</p>
</header>

<?php if ($error !== ''): ?><div class="alert error"><?= atxp_h($error) ?></div><?php endif; ?>
<?php if ($notice !== ''): ?><div class="alert notice"><?= atxp_h($notice) ?></div><?php endif; ?>

<section class="card">
<h2>Personalização</h2>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?= atxp_h((string)$_SESSION['atxp_apk_csrf']) ?>">
<input type="hidden" name="action" value="start">
<div class="field">
<label for="app_name">Nome do aplicativo</label>
<input id="app_name" name="app_name" maxlength="80" required value="<?= atxp_h($appName) ?>" placeholder="Ex.: ATX Cliente">
</div>
<div class="field">
<label for="credential">Credencial ATX</label>
<input id="credential" name="credential" required maxlength="200" pattern="[A-Za-z0-9_.=\-]+" autocomplete="new-password" placeholder="Credencial do cliente">
<div class="hint">Use uma credencial válida. Restrinja o acesso ao repositório GitHub, pois os inputs do workflow podem ser visíveis a pessoas com acesso às execuções.</div>
</div>
<div class="field">
<label for="package_name">Pacote Android</label>
<input id="package_name" name="package_name" maxlength="180" required pattern="[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+" value="<?= atxp_h($packageName) ?>" placeholder="Ex.: com.cliente.app">
<div class="hint">Exemplo: com.cliente.app. Escolha um identificador exclusivo. O pacote original é com.atx.tunnel.</div>
</div>
<button type="submit">Iniciar compilação personalizada</button>
</form>
</section>

<section class="card">
<h2>Status da compilação</h2>
<?php if ($run): ?>
<dl>
<dt>Execução</dt><dd>#<?= (int)($run['run_number'] ?? 0) ?> (ID <?= (int)($run['id'] ?? 0) ?>)</dd>
<dt>Estado</dt><dd><span class="status <?= ($run['status'] ?? '') === 'completed' ? ((($run['conclusion'] ?? '') === 'success') ? 'ok' : 'bad') : '' ?>"><?= atxp_h((string)($run['status'] ?? 'desconhecido')) ?><?= !empty($run['conclusion']) ? ' — ' . atxp_h((string)$run['conclusion']) : '' ?></span></dd>
<dt>Iniciado em</dt><dd><?= atxp_h((string)($run['created_at'] ?? '')) ?></dd>
<dt>Workflow</dt><dd><a style="color:#b8c7ff" href="<?= atxp_h((string)($run['html_url'] ?? '#')) ?>" target="_blank" rel="noopener noreferrer">Ver execução no GitHub</a></dd>
</dl>
<?php else: ?>
<p>Nenhuma execução identificada nesta sessão ainda. Depois de iniciar, clique em “Atualizar status”.</p>
<?php endif; ?>
<div class="row">
<?php if (!empty($_SESSION['atxp_apk_started_at'])): ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= atxp_h((string)$_SESSION['atxp_apk_csrf']) ?>">
<input type="hidden" name="action" value="refresh">
<button class="secondary" type="submit">Atualizar status</button>
</form>
<?php endif; ?>
<?php if ($artifact && $run && ($run['conclusion'] ?? '') === 'success'): ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= atxp_h((string)$_SESSION['atxp_apk_csrf']) ?>">
<input type="hidden" name="action" value="download">
<button type="submit">Baixar APK personalizado</button>
</form>
<p class="hint">Artefato disponível até <?= atxp_h((string)($artifact['expires_at'] ?? 'o prazo de retenção do GitHub')) ?>.</p>
<?php endif; ?>
</div>
</section>
<p class="foot">Requer HTTPS, PHP cURL, ZipArchive e token GitHub com permissões Actions: read/write. Este arquivo chama somente build-apk-personalizado.yml.</p>
</div>
</body>
</html>