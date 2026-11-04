<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
requireLogin();

if (!function_exists('isAdmin') || !isAdmin()) {
    http_response_code(403);
    exit('Acesso somente para administradores.');
}
if (!extension_loaded('curl') || !class_exists('ZipArchive')) {
    http_response_code(500);
    exit('Extensões PHP cURL e ZipArchive são necessárias.');
}
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// --- CONFIGURAÇÕES ATUALIZADAS ---
const MG_OWNER = 'cybercoari79';
const MG_REPO = 'source'; // Repositório atualizado
const MG_WORKFLOW = 'build-multi-apk.yml';
const MG_BRANCH = 'main';
const MG_API = 'https://api.github.com';
const MG_ARTIFACT = 'multi-apk';

// APKs que estão na pasta /APKs do repositório
const MG_BASES = [
    'vip.apk',
    'vip-teste2.apk',
];

function mg_h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function mg_token(): string {
    if (defined('ATX_GITHUB_TOKEN')) {
        $t = trim((string)constant('ATX_GITHUB_TOKEN'));
        if ($t !== '') return $t;
    }
    $t = trim((string)getenv('ATX_GITHUB_TOKEN'));
    if ($t !== '') return $t;
    $f = dirname(__DIR__, 2) . '/atx-secrets/github-token.php';
    if (is_file($f) && is_readable($f)) {
        $l = require $f;
        if (is_string($l) && trim($l) !== '') return trim($l);
    }
    throw new RuntimeException('Token GitHub não configurado.');
}
function mg_path(string $s): string {
    return '/repos/' . MG_OWNER . '/' . MG_REPO . $s;
}
function mg_req(string $method, string $path, ?array $body = null, bool $allow204 = false): array {
    $ch = curl_init(MG_API . $path);
    $h = [
        'Accept: application/vnd.github+json',
        'Authorization: Bearer ' . mg_token(),
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: ATX-Multi'
    ];
    if ($body !== null) $h[] = 'Content-Type: application/json';
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $h,
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
    if ($errno) throw new RuntimeException('Falha de conexão: ' . $err);
    if ($status === 204 && $allow204) return ['_status' => 204];
    $json = is_string($raw) ? json_decode($raw, true) : null;
    if ($status < 200 || $status >= 300 || !is_array($json)) {
        $msg = is_array($json) ? (string)($json['message'] ?? '') : '';
        throw new RuntimeException('GitHub API HTTP ' . $status . ($msg ? ': ' . $msg : '.'));
    }
    return $json;
}
function mg_csrf(): void {
    $sent = (string)($_POST['csrf'] ?? '');
    if (!isset($_SESSION['mg_csrf']) || !hash_equals((string)$_SESSION['mg_csrf'], $sent)) {
        throw new RuntimeException('Sessão expirou. Atualize a página.');
    }
}
if (empty($_SESSION['mg_csrf'])) $_SESSION['mg_csrf'] = bin2hex(random_bytes(32));

$error = '';
$notice = '';
$runId = (int)($_SESSION['mg_run_id'] ?? 0);
$run = null;
$artifact = null;

try {
    $action = (string)($_POST['action'] ?? '');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        mg_csrf();

        if ($action === 'start') {
            $base = trim((string)($_POST['base_apk'] ?? ''));
            $appName = trim((string)($_POST['app_name'] ?? ''));
            $credential = trim((string)($_POST['credential'] ?? ''));
            $newPackage = trim((string)($_POST['new_package'] ?? ''));

            if (!in_array($base, MG_BASES, true)) {
                throw new RuntimeException('APK base inválido.');
            }
            if ($appName === '' || mb_strlen($appName) > 80) {
                throw new RuntimeException('Nome inválido (até 80 caracteres).');
            }
            // Prevenção de injeção de comandos no YAML
            if (preg_match('/[`$();|&<>\\\\]/', $appName)) {
                throw new RuntimeException('O nome contém caracteres inválidos.');
            }
            if ($credential !== '' && !preg_match('/^[A-Za-z0-9_.=\-]{1,200}$/', $credential)) {
                throw new RuntimeException('Credencial inválida. Deixe em branco se o app usa login manual.');
            }
            if ($newPackage !== '') {
                if (!preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $newPackage)) {
                    throw new RuntimeException('Pacote inválido. Ex.: com.vip.joao');
                }
                if (strlen($newPackage) > 180) {
                    throw new RuntimeException('Pacote longo demais.');
                }
            }

            $before = mg_req('GET', mg_path('/actions/workflows/' . rawurlencode(MG_WORKFLOW) . '/runs?branch=' . rawurlencode(MG_BRANCH) . '&per_page=10'));
            $beforeIds = [];
            foreach (($before['workflow_runs'] ?? []) as $item) $beforeIds[] = (int)($item['id'] ?? 0);

            mg_req('POST', mg_path('/actions/workflows/' . rawurlencode(MG_WORKFLOW) . '/dispatches'), [
                'ref' => MG_BRANCH,
                'inputs' => [
                    'base_apk' => $base,
                    'app_name' => $appName,
                    'credential' => $credential,
                    'new_package' => $newPackage,
                ],
            ], true);

            $_SESSION['mg_before_ids'] = $beforeIds;
            $_SESSION['mg_started_at'] = time();
            $_SESSION['mg_app_name'] = $appName; // Salvando na sessão de forma segura
            unset($_SESSION['mg_run_id']);
            $runId = 0;
            $notice = 'Solicitação enviada. Aguarde e clique em "Atualizar status".';
        } elseif ($action === 'refresh') {
            $runId = (int)($_SESSION['mg_run_id'] ?? 0);
            if ($runId <= 0) {
                $started = (int)($_SESSION['mg_started_at'] ?? 0);
                if ($started <= 0) throw new RuntimeException('Nenhuma compilação iniciada.');
                $runs = mg_req('GET', mg_path('/actions/workflows/' . rawurlencode(MG_WORKFLOW) . '/runs?branch=' . rawurlencode(MG_BRANCH) . '&per_page=10'));
                $beforeIds = $_SESSION['mg_before_ids'] ?? [];
                foreach (($runs['workflow_runs'] ?? []) as $item) {
                    $created = strtotime((string)($item['created_at'] ?? '')) ?: 0;
                    if (!in_array((int)($item['id'] ?? 0), $beforeIds, true) && $created >= $started - 90) {
                        $runId = (int)$item['id'];
                        $_SESSION['mg_run_id'] = $runId;
                        break;
                    }
                }
                if ($runId <= 0) $notice = 'O GitHub ainda está registrando. Aguarde e atualize.';
            }
        } elseif ($action === 'download') {
            $runId = (int)($_SESSION['mg_run_id'] ?? 0);
            if ($runId <= 0) throw new RuntimeException('Sem execução vinculada.');
            $run = mg_req('GET', mg_path('/actions/runs/' . $runId));
            if (($run['status'] ?? '') !== 'completed' || ($run['conclusion'] ?? '') !== 'success') {
                throw new RuntimeException('Só é possível baixar após compilação com sucesso.');
            }
            $arts = mg_req('GET', mg_path('/actions/runs/' . $runId . '/artifacts?name=' . rawurlencode(MG_ARTIFACT) . '&per_page=10'));
            foreach (($arts['artifacts'] ?? []) as $a) {
                if (($a['name'] ?? '') === MG_ARTIFACT && empty($a['expired'])) { $artifact = $a; break; }
            }
            if (!$artifact) throw new RuntimeException('Artefato não encontrado.');

            $ch = curl_init(MG_API . mg_path('/actions/artifacts/' . (int)$artifact['id'] . '/zip'));
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    'Accept: application/vnd.github+json',
                    'Authorization: Bearer ' . mg_token(),
                    'X-GitHub-Api-Version: 2022-11-28',
                    'User-Agent: ATX-Multi'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 90,
            ]);
            $zipData = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (!is_string($zipData) || $status !== 200) throw new RuntimeException('Falha ao baixar artefato. HTTP ' . $status);

            $tmp = tempnam(sys_get_temp_dir(), 'mg_');
            if ($tmp === false) throw new RuntimeException('Falha ao criar arquivo temporário.');
            if (file_put_contents($tmp, $zipData) === false) {
                @unlink($tmp);
                throw new RuntimeException('Falha ao gravar arquivo temporário.');
            }
            unset($zipData);
            $zip = new ZipArchive();
            if ($zip->open($tmp) !== true) { @unlink($tmp); throw new RuntimeException('ZIP inválido.'); }
            
            $apkContent = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                // A MÁGICA ESTÁ AQUI: Procura qualquer arquivo que termine com .apk
                if ($stat && str_ends_with((string)$stat['name'], '.apk')) {
                    $stream = $zip->getStream((string)$stat['name']);
                    if ($stream) { $apkContent = stream_get_contents($stream); fclose($stream); }
                    break;
                }
            }
            $zip->close();
            @unlink($tmp);
            if (!is_string($apkContent) || $apkContent === '') throw new RuntimeException('Nenhum arquivo .apk encontrado no artefato.');
            
            // Pega o nome salvo na sessão de forma segura
            $nomeSalvo = (string)($_SESSION['mg_app_name'] ?? 'app');
            $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $nomeSalvo);
            
            header('Content-Type: application/vnd.android.package-archive');
            header('Content-Disposition: attachment; filename="' . $safeName . '.apk"');
            header('Content-Length: ' . strlen($apkContent));
            echo $apkContent;
            exit;
        }
    }

    $runId = (int)($_SESSION['mg_run_id'] ?? $runId);
    if ($runId > 0) {
        $run = mg_req('GET', mg_path('/actions/runs/' . $runId));
        if (($run['status'] ?? '') === 'completed' && ($run['conclusion'] ?? '') === 'success') {
            $arts = mg_req('GET', mg_path('/actions/runs/' . $runId . '/artifacts?name=' . rawurlencode(MG_ARTIFACT) . '&per_page=10'));
            foreach (($arts['artifacts'] ?? []) as $a) {
                if (($a['name'] ?? '') === MG_ARTIFACT && empty($a['expired'])) { $artifact = $a; break; }
            }
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gerar APK - Multi</title>
<style>
:root{color-scheme:dark;--bg:#0b1020;--panel:#131b2e;--line:#26344e;--text:#eef3ff;--muted:#a7b5ce;--accent:#6d8cff}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:16px/1.5 system-ui,-apple-system,sans-serif}.wrap{max-width:900px;margin:32px auto;padding:0 16px}.card{background:var(--panel);border:1px solid var(--line);border-radius:18px;padding:22px;margin:16px 0}h1{margin:8px 0}.eyebrow{color:#9eb2ff;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.field{display:grid;gap:7px;margin:16px 0}label{font-weight:700}input,select{width:100%;border:1px solid #354663;border-radius:10px;padding:13px 14px;background:#0c1425;color:var(--text);font:inherit}button{border:0;border-radius:10px;padding:12px 16px;background:var(--accent);color:white;font:inherit;font-weight:750;cursor:pointer}.secondary{background:#273650}.alert{padding:12px 14px;border-radius:10px;margin:12px 0;border:1px solid var(--line)}.error{border-color:#7e3947;background:#351b27;color:#ffd7dc}.notice{border-color:#315e53;background:#132e2a;color:#c8ffea}.hint{font-size:13px;color:var(--muted)}.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}dl{display:grid;grid-template-columns:140px 1fr;gap:8px;margin:14px 0}dt{color:var(--muted)}dd{margin:0;overflow-wrap:anywhere}
</style>
</head>
<body>
<div class="wrap">
<div class="eyebrow">ATX 3.0 · Administração</div>
<h1>Gerar APK personalizado (multi-base)</h1>
<p class="hint">Escolha o APK base, dê um nome, opcionalmente uma credencial e um novo pacote.</p>

<?php if ($error !== ''): ?><div class="alert error"><?= mg_h($error) ?></div><?php endif; ?>
<?php if ($notice !== ''): ?><div class="alert notice"><?= mg_h($notice) ?></div><?php endif; ?>

<section class="card">
<h2>Personalização</h2>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?= mg_h((string)$_SESSION['mg_csrf']) ?>">
<input type="hidden" name="action" value="start">
<div class="field">
<label for="base_apk">APK base</label>
<select id="base_apk" name="base_apk" required>
<?php foreach (MG_BASES as $b): ?>
<option value="<?= mg_h($b) ?>"><?= mg_h($b) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="field">
<label for="app_name">Nome do aplicativo</label>
<input id="app_name" name="app_name" maxlength="80" required placeholder="Ex.: Meu App">
</div>
<div class="field">
<label for="credential">Credencial (opcional)</label>
<input id="credential" name="credential" maxlength="200" placeholder="Deixe em branco se o app usa login manual">
<div class="hint">Só é usada em APKs que leem <code>assets/config/credential.txt</code>.</div>
</div>
<div class="field">
<label for="new_package">Novo pacote (opcional)</label>
<input id="new_package" name="new_package" maxlength="180" placeholder="Ex.: com.vip.joao">
<div class="hint">Deixe vazio pra manter o pacote original. Preencha pra instalar junto com outra versão.</div>
</div>
<button type="submit">Iniciar compilação</button>
</form>
</section>

<section class="card">
<h2>Status</h2>
<?php if ($run): ?>
<dl>
<dt>Execução</dt><dd>#<?= (int)($run['run_number'] ?? 0) ?></dd>
<dt>Estado</dt><dd><?= mg_h((string)($run['status'] ?? '?')) ?> <?= mg_h((string)($run['conclusion'] ?? '')) ?></dd>
<dt>Ver no GitHub</dt><dd><a style="color:#b8c7ff" href="<?= mg_h((string)($run['html_url'] ?? '#')) ?>" target="_blank">abrir</a></dd>
</dl>
<?php else: ?>
<p>Nenhuma execução ainda.</p>
<?php endif; ?>
<div class="row">
<?php if (!empty($_SESSION['mg_started_at'])): ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= mg_h((string)$_SESSION['mg_csrf']) ?>">
<input type="hidden" name="action" value="refresh">
<button class="secondary" type="submit">Atualizar status</button>
</form>
<?php endif; ?>
<?php if ($artifact && $run && ($run['conclusion'] ?? '') === 'success'): ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= mg_h((string)$_SESSION['mg_csrf']) ?>">
<input type="hidden" name="action" value="download">
<button type="submit">Baixar APK</button>
</form>
<?php endif; ?>
</div>
</section>
</div>
</body>
</html>