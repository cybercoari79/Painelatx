<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

global $pdo;
$activePage = 'downloads';
$pageTitle = 'Central de Downloads ATX';

// Arquivos permitidos: nenhum caminho vem do usuário. Coloque os APKs em /storage/apks/.
$apps = [
    'atxmod' => ['name' => 'ATXMOD', 'file' => 'atxmod.apk', 'description' => 'Aplicativo ATXMOD'],
    'cybercoari' => ['name' => 'cybercoari', 'file' => 'cybercoari.apk', 'description' => 'Aplicativo cybercoari'],
    'connectplus' => ['name' => 'ConnectPlus', 'file' => 'connectplus.apk', 'description' => 'Aplicativo ConnectPlus'],
    'vip21' => ['name' => 'VIP 2.1', 'file' => 'vip-2.1.apk', 'description' => 'Aplicativo VIP versão 2.1'],
];
$selected = (string)($_GET['app'] ?? 'vip21');
if (!isset($apps[$selected])) $selected = 'vip21';
$storageDir = dirname(__DIR__) . '/storage/apks';
$error = '';

if (isset($_GET['download'])) {
    $key = (string)$_GET['download'];
    if (!isset($apps[$key])) {
        http_response_code(400);
        exit('Aplicativo inválido.');
    }
    $item = $apps[$key];
    $filePath = $storageDir . '/' . $item['file'];
    if (!is_file($filePath) || !is_readable($filePath)) {
        http_response_code(404);
        $selected = $key;
        $error = 'O APK de ' . $item['name'] . ' ainda não foi colocado na pasta storage/apks/. Nenhuma compilação foi iniciada.';
    } else {
        if (function_exists('logActivity')) {
            logActivity($pdo, 'Baixou APK', 'Downloads ATX', 'Download do aplicativo ' . $item['name'] . '.');
        }
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="' . $item['file'] . '"');
        header('Content-Length: ' . (string)filesize($filePath));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        while (ob_get_level() > 0) ob_end_clean();
        readfile($filePath);
        exit;
    }
}
function atxdle(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= atxdle($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= atxdle(appUrl('/assets/css/style.css?v=1')) ?>">
    <style>
        .downloads-wrap{max-width:1050px;margin:0 auto}
        .downloads-intro{color:#aab8cc;margin:8px 0 22px;line-height:1.55}
        .downloads-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
        .download-card{display:flex;align-items:center;gap:14px;background:#111927;border:1px solid #26364a;border-radius:14px;padding:18px;cursor:pointer;transition:border-color .18s,transform .18s}
        .download-card:hover{border-color:#ff6b00;transform:translateY(-1px)}
        .download-card.selected{border-color:#ff6b00;box-shadow:0 0 0 1px #ff6b00 inset}
        .download-icon{width:52px;height:52px;flex:0 0 52px;border-radius:13px;display:grid;place-items:center;background:#1d2b3d;font-size:25px}
        .download-info{flex:1;min-width:0}.download-info strong{display:block;font-size:17px;margin-bottom:5px}.download-info small{color:#9aabc0}
        .download-card input{accent-color:#ff6b00;width:18px;height:18px}
        .download-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:22px}
        .download-actions button{border:0;border-radius:9px;background:#ff6b00;color:#fff;font-weight:700;padding:13px 20px;cursor:pointer;font-size:15px}
        .download-actions button:hover{background:#e85e00}
        .download-help{font-size:13px;color:#91a2b8;line-height:1.6;margin-top:18px}
        .download-error{background:#3b1e23;border:1px solid #8f3b45;color:#ffd8dc;border-radius:10px;padding:13px 15px;margin:0 0 18px}
        @media(max-width:700px){.downloads-grid{grid-template-columns:1fr}.download-card{padding:14px}.downloads-wrap{padding-bottom:20px}}
    </style>
</head>
<body>
<div class="dashboard">
    <?php require __DIR__ . '/../includes/menu.php'; ?>
    <main class="main-content">
        <?php require __DIR__ . '/../includes/header.php'; ?>
        <section class="container-form downloads-wrap">
            <h1>Central de <span>Downloads ATX</span></h1>
            <p class="downloads-intro">Escolha um aplicativo e baixe o APK já compilado. Esta página não inicia builds nem aciona o GitHub Actions.</p>
            <?php if ($error !== ''): ?><div class="download-error"><?= atxdle($error) ?></div><?php endif; ?>
            <form method="get" action="<?= atxdle(appUrl('/admin/downloads_apk.php')) ?>" id="downloadForm">
                <div class="downloads-grid">
                    <?php foreach ($apps as $key => $item): $exists = is_file($storageDir . '/' . $item['file']) && is_readable($storageDir . '/' . $item['file']); ?>
                    <label class="download-card <?= $selected === $key ? 'selected' : '' ?>">
                        <span class="download-icon">📱</span>
                        <span class="download-info"><strong><?= atxdle($item['name']) ?></strong><small><?= $exists ? 'APK disponível para download' : 'APK ainda não enviado' ?></small></span>
                        <input type="radio" name="app" value="<?= atxdle($key) ?>" <?= $selected === $key ? 'checked' : '' ?> aria-label="Selecionar <?= atxdle($item['name']) ?>">
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="download-actions">
                    <button type="submit" name="download" value="<?= atxdle($selected) ?>" id="downloadButton">⬇ Baixar <?= atxdle($apps[$selected]['name']) ?></button>
                    <span style="color:#9aabc0;font-size:13px">Arquivo APK já compilado</span>
                </div>
            </form>
            <p class="download-help">Para habilitar cada botão, envie os arquivos prontos para <code>storage/apks/</code> usando estes nomes exatos: <code>atxmod.apk</code>, <code>cybercoari.apk</code>, <code>connectplus.apk</code> e <code>vip-2.1.apk</code>. A pasta é protegida contra acesso direto; os downloads passam por esta página autenticada.</p>
        </section>
    </main>
</div>
<script src="<?= atxdle(appUrl('/assets/js/script.js')) ?>"></script>
<script>
(function(){
 const form=document.getElementById('downloadForm');
 const button=document.getElementById('downloadButton');
 function update(){
   const checked=form.querySelector('input[name="app"]:checked');
   if(!checked)return;
   document.querySelectorAll('.download-card').forEach(card=>card.classList.toggle('selected',card.contains(checked)));
   const names={atxmod:'ATXMOD',cybercoari:'cybercoari',connectplus:'ConnectPlus',vip21:'VIP 2.1'};
   button.textContent='⬇ Baixar '+names[checked.value];
   button.value=checked.value;
 }
 form.querySelectorAll('input[name="app"]').forEach(input=>input.addEventListener('change',update));
 form.addEventListener('submit',function(event){
   const checked=form.querySelector('input[name="app"]:checked');
   if(!checked)return;
   event.preventDefault();
   window.location.href=form.action+'?download='+encodeURIComponent(checked.value);
 });
 update();
})();
</script>
</body>
</html>