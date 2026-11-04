<?php
declare(strict_types=1);

function ensureAtxTables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS atx_config (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        version VARCHAR(50) NOT NULL DEFAULT '0',
        app_name VARCHAR(100) NOT NULL DEFAULT 'ATXMOD',
        release_notes TEXT NULL,
        update_url TEXT NULL,
        contato VARCHAR(255) NOT NULL DEFAULT '',
        logo TEXT NULL,
        fundo TEXT NULL,
        check_pop VARCHAR(10) NOT NULL DEFAULT 'true',
        cor_menu VARCHAR(20) NOT NULL DEFAULT '#000000',
        cor_btcu VARCHAR(20) NOT NULL DEFAULT '#ff0000',
        webview TEXT NULL,
        roteador VARCHAR(10) NOT NULL DEFAULT 'true',
        status_if VARCHAR(10) NOT NULL DEFAULT 'false',
        icon_cor VARCHAR(20) NOT NULL DEFAULT '#ffffff',
        html_webview TEXT NULL,
        html_use VARCHAR(10) NOT NULL DEFAULT 'false',
        html_app TEXT NULL,
        background_cor VARCHAR(20) NOT NULL DEFAULT '#000000',
        background_borda INT NOT NULL DEFAULT 35,
        border_cor VARCHAR(20) NOT NULL DEFAULT '#ff0000',
        border_borda INT NOT NULL DEFAULT 14,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), UNIQUE KEY uq_atx_config_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $check = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atx_config' AND COLUMN_NAME = 'app_name'");
    if ((int)$check->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE atx_config ADD COLUMN app_name VARCHAR(100) NOT NULL DEFAULT 'ATXMOD' AFTER version");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS atx_servers (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        name VARCHAR(150) NOT NULL,
        type VARCHAR(50) NOT NULL DEFAULT 'ssh',
        flag_url TEXT NULL,
        server_ip VARCHAR(255) NOT NULL DEFAULT '',
        check_user TEXT NULL,
        server_port VARCHAR(20) NOT NULL DEFAULT '',
        ssl_port VARCHAR(20) NOT NULL DEFAULT '',
        username VARCHAR(150) NOT NULL DEFAULT '',
        password VARCHAR(255) NOT NULL DEFAULT '',
        payload TEXT NULL,
        sni VARCHAR(255) NOT NULL DEFAULT '',
        tls_ip VARCHAR(255) NOT NULL DEFAULT '',
        proxy_ip VARCHAR(255) NOT NULL DEFAULT '',
        proxy_port VARCHAR(20) NOT NULL DEFAULT '',
        info VARCHAR(255) NOT NULL DEFAULT 'Tlsws',
        color VARCHAR(20) NOT NULL DEFAULT '#ffffff',
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY idx_atx_servers_user (user_id), KEY idx_atx_servers_status (user_id,status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function getAtxConfig(PDO $pdo, int $userId): array
{
    ensureAtxTables($pdo);
    $stmt = $pdo->prepare('SELECT * FROM atx_config WHERE user_id=? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if ($row) return $row;
    return [
        'version'=>'0','app_name'=>'ATXMOD','release_notes'=>'','update_url'=>'https://painel.cybercoari.com.br/api/atx.php?URL=admin','contato'=>'',
        'logo'=>'','fundo'=>'','check_pop'=>'true','cor_menu'=>'#000000','cor_btcu'=>'#ff0000','webview'=>'',
        'roteador'=>'true','status_if'=>'false','icon_cor'=>'#ffffff','html_webview'=>'','html_use'=>'false','html_app'=>'',
        'background_cor'=>'#000000','background_borda'=>35,'border_cor'=>'#ff0000','border_borda'=>14
    ];
}

function saveAtxConfig(PDO $pdo, int $userId, array $data): bool
{
    ensureAtxTables($pdo);
    $sql = 'INSERT INTO atx_config (user_id,version,app_name,release_notes,update_url,contato,logo,fundo,check_pop,cor_menu,cor_btcu,webview,roteador,status_if,icon_cor,html_webview,html_use,html_app,background_cor,background_borda,border_cor,border_borda)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE version=VALUES(version),app_name=VALUES(app_name),release_notes=VALUES(release_notes),update_url=VALUES(update_url),contato=VALUES(contato),logo=VALUES(logo),fundo=VALUES(fundo),check_pop=VALUES(check_pop),cor_menu=VALUES(cor_menu),cor_btcu=VALUES(cor_btcu),webview=VALUES(webview),roteador=VALUES(roteador),status_if=VALUES(status_if),icon_cor=VALUES(icon_cor),html_webview=VALUES(html_webview),html_use=VALUES(html_use),html_app=VALUES(html_app),background_cor=VALUES(background_cor),background_borda=VALUES(background_borda),border_cor=VALUES(border_cor),border_borda=VALUES(border_borda)';
    $stmt=$pdo->prepare($sql);
    return $stmt->execute([
        $userId,$data['version'],$data['app_name'],$data['release_notes'],$data['update_url'],$data['contato'],$data['logo'],$data['fundo'],$data['check_pop'],
        $data['cor_menu'],$data['cor_btcu'],$data['webview'],$data['roteador'],$data['status_if'],$data['icon_cor'],$data['html_webview'],
        $data['html_use'],$data['html_app'],$data['background_cor'],$data['background_borda'],$data['border_cor'],$data['border_borda']
    ]);
}

function getAtxServers(PDO $pdo, int $userId): array
{
    ensureAtxTables($pdo);
    $stmt=$pdo->prepare("SELECT * FROM atx_servers WHERE user_id=? AND (status='active' OR status IS NULL) ORDER BY id ASC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function buildAtxServerFromRow(array $s): array
{
    return [
        'id'=>(string)($s['id']??''), 'id_ordem'=>(string)($s['id']??''), 'corct'=>(string)($s['color']??'#ffffff'),
        'Name'=>(string)($s['name']??''), 'TYPE'=>(string)($s['type']??'ssh'), 'FLAG'=>(string)($s['flag_url']??''),
        'ServerIP'=>(string)($s['server_ip']??''), 'CheckUser'=>(string)($s['check_user']??''), 'ServerPort'=>(string)($s['server_port']??''),
        'SSLPort'=>(string)($s['ssl_port']??''), 'USER'=>(string)($s['username']??''), 'PASS'=>(string)($s['password']??''),
        'Payload'=>(string)($s['payload']??''), 'SNI'=>(string)($s['sni']??''), 'TlsIP'=>(string)($s['tls_ip']??''),
        'ProxyIP'=>(string)($s['proxy_ip']??''), 'ProxyPort'=>(string)($s['proxy_port']??''), 'Info'=>(string)($s['info']??'Tlsws')
    ];
}


/** Credencial exclusiva do APK ATX, separada dos tokens das outras APIs. */
function ensureAtxCredentialTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS atx_credentials (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        credential_hash CHAR(64) NOT NULL,
        credential_preview VARCHAR(40) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_atx_credential_user (user_id),
        UNIQUE KEY uq_atx_credential_hash (credential_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function rotateAtxCredential(PDO $pdo, int $userId): string
{
    if ($userId <= 0) {
        throw new InvalidArgumentException('Usuário ATX inválido.');
    }
    ensureAtxCredentialTable($pdo);
    $token = 'atx_' . rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    $hash = hash('sha256', $token);
    $preview = substr($token, 0, 8) . '...' . substr($token, -4);
    $stmt = $pdo->prepare('INSERT INTO atx_credentials (user_id, credential_hash, credential_preview) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE credential_hash=VALUES(credential_hash), credential_preview=VALUES(credential_preview), updated_at=CURRENT_TIMESTAMP');
    $stmt->execute([$userId, $hash, $preview]);
    return $token;
}

function findUserByAtxCredential(PDO $pdo, string $credential): ?array
{
    $credential = trim($credential);
    if ($credential === '') return null;
    ensureAtxCredentialTable($pdo);
    $stmt = $pdo->prepare("SELECT u.id, u.username, u.role, u.status FROM atx_credentials c INNER JOIN users u ON u.id = c.user_id WHERE c.credential_hash = ? LIMIT 1");
    $stmt->execute([hash('sha256', $credential)]);
    $user = $stmt->fetch();
    if (!$user || ($user['status'] ?? '') !== 'active') return null;
    return $user;
}