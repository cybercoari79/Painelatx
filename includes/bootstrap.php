<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/atx.php';
global $pdo;
if (!isset($pdo) || !($pdo instanceof PDO)) {
    try { $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]); }
    catch (PDOException $e) { error_log('ATX DB: '.$e->getMessage()); http_response_code(500); exit('Erro na conexão com o banco de dados.'); }
}
// Regra de segurança do ATX: somente a conta chamada "admin" pode ter papel administrativo.
// As demais contas com role=admin são rebaixadas para user.
try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
    if ($tableExists) {
        $pdo->exec("UPDATE users SET role='user' WHERE username <> 'admin' AND role='admin'");
        $pdo->exec("UPDATE users SET role='admin' WHERE username='admin' LIMIT 1");
    }
} catch (Throwable $e) {
    error_log('ATX admin protection: ' . $e->getMessage());
}

if (!function_exists('isLoggedIn')) { function isLoggedIn(): bool { if (empty($_SESSION['v2_user_id']) || empty($_SESSION['v2_logged_in'])) return false; if (!empty($_SESSION['v2_last_activity']) && time()-(int)$_SESSION['v2_last_activity']>SESSION_TIMEOUT) { logout(); return false; } $_SESSION['v2_last_activity']=time(); return true; } }
if (!function_exists('currentUserId')) { function currentUserId(): int { return (int)($_SESSION['v2_user_id'] ?? 0); } }
if (!function_exists('isAdmin')) { function isAdmin(): bool { return ($_SESSION['v2_role'] ?? '') === 'admin' && strtolower((string)($_SESSION['v2_username'] ?? '')) === 'admin'; } }
if (!function_exists('requireLogin')) { function requireLogin(): void { if (!isLoggedIn()) redirect('/admin/login.php'); } }
if (!function_exists('login')) { function login(string $username,string $password): bool { global $pdo; $s=$pdo->prepare('SELECT id,username,password,role,status FROM users WHERE username=? LIMIT 1'); $s->execute([$username]); $u=$s->fetch(); if (!$u || $u['status']!=='active' || !password_verify($password,$u['password'])) return false; session_regenerate_id(true); $_SESSION['v2_user_id']=(int)$u['id']; $_SESSION['v2_username']=$u['username']; $_SESSION['v2_role']=$u['role']; $_SESSION['v2_logged_in']=true; $_SESSION['v2_last_activity']=time(); return true; } }
if (!function_exists('logout')) { function logout(): void { $_SESSION=[]; if (session_status()===PHP_SESSION_ACTIVE) session_destroy(); } }
if (!function_exists('appUrl')) { function appUrl(string $path=''): string { $base=rtrim(SITE_URL,'/'); return $base . ($path===''?'/':'/'.ltrim($path,'/')); } }
if (!function_exists('redirect')) { function redirect(string $url): void { header('Location: '.(preg_match('#^https?://#i',$url)?$url:appUrl($url)),true,302); exit; } }
if (!function_exists('sanitizeInput')) { function sanitizeInput(string $v): string { return htmlspecialchars(strip_tags(trim($v)),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); } }
if (!function_exists('getUsers')) { function getUsers(PDO $pdo): array { return $pdo->query('SELECT id,username,role,status,created_at,updated_at FROM users ORDER BY id ASC')->fetchAll(); } }
if (!function_exists('getUser')) { function getUser(PDO $pdo,int $id): ?array { $s=$pdo->prepare('SELECT id,username,role,status,created_at,updated_at FROM users WHERE id=? LIMIT 1'); $s->execute([$id]); return $s->fetch() ?: null; } }
if (!function_exists('logActivity')) { function logActivity(PDO $pdo,string $action,string $module,string $description='',?int $userId=null): bool { try { $s=$pdo->prepare('INSERT INTO logs (user_id,action,module,description) VALUES (?,?,?,?)'); return $s->execute([$userId ?? currentUserId() ?: null,$action,$module,$description]); } catch(Throwable $e) { return false; } } }
function csrfToken(): string { if (empty($_SESSION['atx_csrf'])) $_SESSION['atx_csrf']=bin2hex(random_bytes(32)); return $_SESSION['atx_csrf']; }
function csrfField(): string { return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8').'">'; }
function verifyCsrfToken(?string $token=null): bool { $token ??= (string)($_POST['csrf_token'] ?? ''); return isset($_SESSION['atx_csrf']) && $token!=='' && hash_equals((string)$_SESSION['atx_csrf'],$token); }