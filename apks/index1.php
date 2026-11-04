<?php
declare(strict_types=1);

/*
 * ATX 3.0 - Central de Downloads
 * Instale em public_html/apks/index.php.
 * Página independente; não altera os geradores existentes.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
requireLogin();

function atxdl_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Os três APKs prontos são baixados diretamente do repositório GitHub.
// O ATXMOD exige credencial e usa o gerador personalizado já existente.
$apps = [
    [
        'name' => 'ATXMOD',
        'description' => 'Gerar uma versão personalizada com credencial ATX.',
        'type' => 'generator',
        'url' => appUrl('/admin/gerarapk_personalizado.php'),
        'button' => 'Personalizar e gerar APK',
    ],
    [
        'name' => 'cybercoari',
        'description' => 'Baixar o APK publicado no repositório.',
        'type' => 'download',
        'url' => 'https://raw.githubusercontent.com/cybecoari/atx-builder/main/cybercoari.apk',
        'button' => 'Baixar APK',
    ],
    [
        'name' => 'ConnectPlus',
        'description' => 'Baixar o APK publicado no repositório.',
        'type' => 'download',
        'url' => 'https://raw.githubusercontent.com/cybecoari/atx-builder/main/ConnectPlus.apk',
        'button' => 'Baixar APK',
    ],
    [
        'name' => 'VIP 2.1',
        'description' => 'Baixar o APK publicado no repositório.',
        'type' => 'download',
        'url' => 'https://raw.githubusercontent.com/cybecoari/atx-builder/main/vip_2.1.apk',
        'button' => 'Baixar APK',
    ],
];

$pageTitle = 'Central de Downloads ATX';
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= atxdl_h($pageTitle) ?></title>
<style>
:root{color-scheme:dark;--bg:#0b1020;--panel:#131b2e;--line:#26344e;--text:#eef3ff;--muted:#a7b5ce;--accent:#6d8cff;--green:#237b52}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:16px/1.5 system-ui,-apple-system,Segoe UI,sans-serif}.wrap{max-width:980px;margin:28px auto;padding:0 16px}.eyebrow{color:#9eb2ff;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}h1{font-size:clamp(27px,5vw,36px);margin:8px 0}p{color:var(--muted)}.intro{margin-bottom:24px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.card{display:flex;flex-direction:column;gap:12px;background:var(--panel);border:1px solid var(--line);border-radius:18px;padding:20px;min-width:0}.apphead{display:flex;align-items:center;gap:12px}.appicon{width:46px;height:46px;display:grid;place-items:center;border-radius:12px;background:#202d48;font-size:23px}.apphead h2{margin:0;font-size:20px}.tag{font-size:12px;color:#c8d4ff;background:#263650;border-radius:999px;padding:4px 9px;display:inline-block;width:max-content}.card p{margin:0;flex:1}.btn{display:inline-flex;justify-content:center;align-items:center;text-decoration:none;text-align:center;border:0;border-radius:10px;padding:12px 15px;background:var(--accent);color:white;font:inherit;font-weight:750;cursor:pointer}.btn.direct{background:var(--green)}.note{font-size:13px;margin-top:20px;border:1px solid var(--line);border-radius:12px;padding:13px 15px}.footer{font-size:12px;margin:18px 0;color:var(--muted)}@media(max-width:620px){.wrap{margin:18px auto}.grid{grid-template-columns:1fr}.card{padding:17px}}
</style>
</head>
<body>
<main class="wrap">
<header class="intro">
<div class="eyebrow">ATX 3.0 · Aplicativos</div>
<h1>Central de Downloads ATX</h1>
<p>Escolha o aplicativo que deseja baixar. O ATXMOD abre o gerador personalizado; os outros três arquivos são baixados diretamente do repositório.</p>
</header>
<section class="grid" aria-label="Aplicativos disponíveis">
<?php foreach ($apps as $app): ?>
<article class="card">
<div class="apphead">
<div class="appicon" aria-hidden="true">📱</div>
<div><h2><?= atxdl_h($app['name']) ?></h2></div>
</div>
<span class="tag"><?= $app['type'] === 'generator' ? 'Requer credencial' : 'Download direto' ?></span>
<p><?= atxdl_h($app['description']) ?></p>
<a class="btn <?= $app['type'] === 'download' ? 'direct' : '' ?>" href="<?= atxdl_h($app['url']) ?>"><?= atxdl_h($app['button']) ?></a>
</article>
<?php endforeach; ?>
</section>
<div class="note">Os downloads diretos dependem de os arquivos permanecerem públicos no repositório GitHub. O ATXMOD usa o gerador personalizado configurado no painel e não inicia uma compilação até você abrir o gerador e enviar o formulário.</div>
<p class="footer">Central independente. Não substitui nem modifica admin/gerarapk.php ou admin/gerar_apk.php.</p>
</main>
</body>
</html>