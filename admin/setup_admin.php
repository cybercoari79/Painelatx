<?php
// Remova este arquivo imediatamente após criar o primeiro administrador.
require_once __DIR__.'/../includes/bootstrap.php';
$exists=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
if ($exists>0) { http_response_code(403); exit('Já existe administrador. Remova este arquivo.'); }
$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') { if (!verifyCsrfToken()) { $msg='Token de segurança inválido.'; } else { $u=trim((string)($_POST['username']??'')); $p=(string)($_POST['password']??''); if (strlen($u)<3 || strlen($p)<4) $msg='Use usuário com 3+ caracteres e senha com pelo menos 4 caracteres.'; else { $s=$pdo->prepare("INSERT INTO users (username,password,role,status) VALUES (?,?,'admin','active')"); $s->execute([$u,password_hash($p,PASSWORD_DEFAULT)]); exit('Administrador criado. Apague agora admin/setup_admin.php e entre em /admin/login.php.'); } } }
?><!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Criar administrador ATX</title><body><h1>Configuração inicial ATX</h1><p><?= htmlspecialchars($msg,ENT_QUOTES,'UTF-8') ?></p><form method="post"><?= csrfField() ?><p>Usuário <input name="username" required></p><p>Senha forte (4+ caracteres) <input type="password" name="password" required minlength="4"></p><button>Criar administrador</button></form></body></html>
