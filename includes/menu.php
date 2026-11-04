<?php
$activePage = $activePage ?? 'atx';
?>
<div class="sidebar-overlay" data-menu-overlay></div>
<aside class="sidebar" id="atx-sidebar">
    <div class="sidebar-brand">
        <div>
            <h2>Painel <span>ATX</span></h2>
            <small>Gerenciamento independente</small>
        </div>
        <button type="button" class="menu-toggle" data-menu-toggle aria-label="Fechar menu">×</button>
    </div>
    <nav class="sidebar-nav">
        <a class="<?= $activePage === 'atx' ? 'active' : '' ?>" href="<?= htmlspecialchars(appUrl('/atx/index.php'), ENT_QUOTES, 'UTF-8') ?>">Configurações ATX</a>
        <a class="<?= $activePage === 'servers' ? 'active' : '' ?>" href="<?= htmlspecialchars(appUrl('/atx/servers.php'), ENT_QUOTES, 'UTF-8') ?>">Servidores ATX</a>
        
        <a class="<?= $activePage === 'apk' ? 'active' : '' ?>" href="<?= htmlspecialchars(appUrl('/admin/gerar_mult.php'), ENT_QUOTES, 'UTF-8') ?>">Gerar APK ATX</a>
        
        <!--<a class="<?= $activePage === 'apk' ? 'active' : '' ?>" href="<?= htmlspecialchars(appUrl('/admin/gerarapk.php'), ENT_QUOTES, 'UTF-8') ?>">Gerar APK ATX</a>-->
        
        <a class="<?= $activePage === 'credential' ? 'active' : '' ?>" href="<?= htmlspecialchars(appUrl('/atx/credencial_atx.php'), ENT_QUOTES, 'UTF-8') ?>">Credencial do APK</a>
        <?php if (isAdmin()): ?>
        <a class="<?= $activePage === 'cadastro' ? 'active' : '' ?>" href="<?= htmlspecialchars(appUrl('/admin/cadastro.php'), ENT_QUOTES, 'UTF-8') ?>">Cadastrar cliente</a>
        <?php endif; ?>
        <hr>
        <a class="logout" href="<?= htmlspecialchars(appUrl('/admin/logout.php'), ENT_QUOTES, 'UTF-8') ?>">Sair</a>
    </nav>
    <div class="sidebar-footer">Painel ATX independente</div>
</aside>
