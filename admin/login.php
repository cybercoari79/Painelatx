<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (function_exists('isLoggedIn') && isLoggedIn()) {
    redirect('/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('verifyCsrfToken') && !verifyCsrfToken()) {
        $error = 'Sessão de segurança inválida. Atualize a página.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Preencha usuário e senha.';
        } elseif (login($username, $password)) {
            redirect('/index.php');
        } else {
            $error = 'Usuário ou senha inválidos.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — Painel ATX</title>
<!-- Tenta carregar o CSS externo, mas o design abaixo não vai quebrar se falhar -->
<link rel="stylesheet" href="<?= htmlspecialchars(appUrl('/assets/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
<style>
    /* Estilos para garantir que o formulário fique bonito mesmo sem o CSS externo */
    body {
        background-color: #121212; /* Cor de fundo escura */
        color: #ffffff;
        font-family: Arial, sans-serif;
        display: grid;
        place-items: center;
        min-height: 100vh;
        margin: 0;
        padding: 20px;
    }
    .login-container {
        max-width: 420px;
        width: 100%;
        background: #1e1e1e; /* Cor do painel */
        padding: 28px;
        border: 1px solid #333;
        border-radius: 14px;
        box-sizing: border-box;
    }
    .login-container h1 {
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 24px;
    }
    .form-group {
        margin-bottom: 15px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-size: 14px;
        color: #ccc;
    }
    .form-control {
        width: 100%;
        padding: 12px;
        background-color: #121212;
        border: 1px solid #333;
        border-radius: 6px;
        color: #fff;
        box-sizing: border-box;
        outline: none;
    }
    .form-control:focus {
        border-color: #e67e22; /* Cor da borda ao clicar (laranja igual da imagem) */
    }
    .btn-entrar {
        width: 100%;
        padding: 12px 18px;
        background-color: #e67e22; /* Cor do botão (Laranja) */
        color: #fff;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .btn-entrar:hover {
        background-color: #d35400; /* Cor do botão ao passar o mouse */
    }
    .error-msg {
        color: #ff4d4d;
        background-color: rgba(255, 77, 77, 0.1);
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 15px;
        font-size: 14px;
    }
</style>
</head>
<body>
<section class="login-container">
    <h1>Painel ATX</h1>

    <?php if ($error !== ''): ?>
        <div class="error-msg"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrfField() ?>
        
        <div class="form-group">
            <label for="username">Usuário</label>
            <input type="text" id="username" name="username" class="form-control" required autocomplete="username">
        </div>
        
        <div class="form-group">
            <label for="password">Senha</label>
            <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
        </div>
        
        <button type="submit" class="btn-entrar">Entrar</button>
    </form>
</section>
</body>
</html>