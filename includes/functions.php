<?php
declare(strict_types=1);

/** Registra uma ação do painel sem interromper o funcionamento caso a tabela ainda não exista. */
function logActivity(PDO $pdo, string $action, string $module, string $description = '', ?int $userId = null): bool {
    try {
        if ($userId === null) {
            $userId = function_exists('currentUserId') ? currentUserId() : 0;
        }
        $stmt = $pdo->prepare('INSERT INTO logs (user_id, action, module, description) VALUES (?, ?, ?, ?)');
        return $stmt->execute([$userId > 0 ? $userId : null, $action, $module, $description]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Funções centrais do ConnectPlus V2.
 * Regras de usuário, senha, token e URLs ficam aqui para evitar duplicação.
 */

function getServers(PDO $pdo, int $userId): array {
    // ConnectPlus usa exclusivamente sua tabela própria.
    $stmt = $pdo->prepare('SELECT * FROM connectplus_servers WHERE user_id = ? ORDER BY id ASC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getNetworks(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('SELECT * FROM networks WHERE user_id = ? ORDER BY name ASC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getUsers(PDO $pdo): array {
    $stmt = $pdo->query('SELECT id, username, role, status, created_at, updated_at FROM users ORDER BY id ASC');
    return $stmt->fetchAll();
}

function getUser(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare('SELECT id, username, password, role, status, update_token_hash, update_token, created_at, updated_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function getConfig(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('SELECT * FROM config WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: [
        'version' => '0',
        'update_url' => '',
        'udp_ports' => '7300',
        'contato' => '',
        'site' => ''
    ];
}

function saveConfig(PDO $pdo, int $userId, array $data): bool {
    try {
        $version = trim((string)($data['version'] ?? '2'));
        $updateUrl = trim((string)($data['update_url'] ?? ''));
        $udpPorts = trim((string)($data['udp_ports'] ?? '7300'));
        $contato = trim((string)($data['contato'] ?? ''));
        $site = trim((string)($data['site'] ?? ''));

        $stmt = $pdo->prepare('SELECT id FROM config WHERE user_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$userId]);
        $id = $stmt->fetchColumn();

        if ($id) {
            $stmt = $pdo->prepare('UPDATE config SET version=?, update_url=?, udp_ports=?, contato=?, site=? WHERE id=? AND user_id=?');
            $ok = $stmt->execute([$version, $updateUrl, $udpPorts, $contato, $site, $id, $userId]);
            $generated = $ok && generateClientUpdateFiles($pdo, $userId);
            if ($generated) logActivity($pdo, 'Atualizou configuração', 'Configurações', 'Configuração do cliente atualizada.', $userId);
            return $generated;
        }

        $stmt = $pdo->prepare('INSERT INTO config (user_id, version, update_url, udp_ports, contato, site) VALUES (?, ?, ?, ?, ?, ?)');
        $ok = $stmt->execute([$userId, $version, $updateUrl, $udpPorts, $contato, $site]);
        $generated = $ok && generateClientUpdateFiles($pdo, $userId);
        if ($generated) logActivity($pdo, 'Criou configuração', 'Configurações', 'Configuração inicial criada.', $userId);
        return $generated;
    } catch (Throwable $e) {
        return false;
    }
}

function deleteServer(PDO $pdo, int $id, int $userId): bool {
    // Compatibilidade do módulo ConnectPlus: nunca usar a tabela legada servers.
    $stmt = $pdo->prepare('DELETE FROM connectplus_servers WHERE id = ? AND user_id = ?');
    $ok = $stmt->execute([$id, $userId]);
    if ($ok && $stmt->rowCount() > 0) { generateClientUpdateFiles($pdo, $userId); logActivity($pdo, 'Excluiu servidor', 'Servidores', 'Servidor ID ' . $id . ' excluído.', $userId); }
    return $ok;
}

function deleteNetwork(PDO $pdo, int $id, int $userId): bool {
    $stmt = $pdo->prepare('SELECT payload_file FROM networks WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    $network = $stmt->fetch();

    if ($network && !empty($network['payload_file']) && defined('PAYLOAD_DIR')) {
        $filePath = PAYLOAD_DIR . basename((string)$network['payload_file']);
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    $stmt = $pdo->prepare('DELETE FROM networks WHERE id = ? AND user_id = ?');
    $ok = $stmt->execute([$id, $userId]);
    if ($ok && $stmt->rowCount() > 0) { generateClientUpdateFiles($pdo, $userId); logActivity($pdo, 'Excluiu rede', 'Redes', 'Rede ID ' . $id . ' excluída.', $userId); }
    return $ok;
}

function validatePassword(string $password): bool {
    return strlen($password) >= 4;
}

function generateApiToken(): string {
    // 24 caracteres URL-safe, com entropia suficiente e sem caracteres especiais problemáticos.
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;
    $token = '';
    for ($i = 0; $i < 24; $i++) {
        $token .= $alphabet[random_int(0, $max)];
    }
    return $token;
}

/** Compatibilidade temporária com páginas antigas. */
function generateToken(): string {
    return generateApiToken();
}

function hashApiToken(string $token): string {
    return hash('sha256', $token);
}

function createUser(PDO $pdo, string $username, string $password, string $status = 'active', string $role = 'user'): array {
    $username = trim($username);

    if (strlen($username) < 3) {
        return ['success' => false, 'message' => 'Usuário deve ter pelo menos 3 caracteres.'];
    }
    if (!validatePassword($password)) {
        return ['success' => false, 'message' => 'A senha deve ter pelo menos 4 caracteres.'];
    }
    if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
        return ['success' => false, 'message' => 'Status inválido.'];
    }
    if (!in_array($role, ['admin', 'user'], true)) {
        return ['success' => false, 'message' => 'Perfil inválido.'];
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        if ($stmt->fetchColumn()) {
            return ['success' => false, 'message' => 'Usuário já existe.'];
        }
        if (clientFolderExistsForOtherUser($pdo, $username, 0)) {
            return ['success' => false, 'message' => 'Esse nome de cliente gera uma pasta já utilizada por outro cliente. Escolha outro nome.'];
        }

        $token = generateApiToken();
        $hash = hashApiToken($token);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // update_token fica NULL para novos usuários. O token só é revelado no momento da criação/rotação.
        $stmt = $pdo->prepare('INSERT INTO users (username, password, role, status, update_token_hash, update_token) VALUES (?, ?, ?, ?, ?, NULL)');
        $stmt->execute([$username, $passwordHash, $role, $status, $hash]);
        $newUserId = (int)$pdo->lastInsertId();

        // Configuração padrão do cliente. O banco continua sendo a fonte de verdade.
        $configApiUrl = rtrim(getApiUrl(), '/') . '?token=' . rawurlencode($token);
        $stmt = $pdo->prepare('INSERT INTO config (user_id, version, update_url, udp_ports, contato, site) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$newUserId, '2', $configApiUrl, '7300', '', '']);

        if (!ensureUserTheme($pdo, $newUserId)) {
            $pdo->prepare('DELETE FROM config WHERE user_id = ?')->execute([$newUserId]);
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$newUserId]);
            return ['success' => false, 'message' => 'Não foi possível criar o tema do cliente.'];
        }

        // Cria imediatamente /update/NOME_DO_CLIENTE/ com os três JSON.
        if (!generateClientUpdateFiles($pdo, $newUserId)) {
            $partialDir = getClientUpdateDirectory($pdo, $newUserId);
            if (is_dir($partialDir)) {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($partialDir, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($it as $item) {
                    $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
                }
                @rmdir($partialDir);
            }
            $pdo->prepare('DELETE FROM user_theme WHERE user_id = ?')->execute([$newUserId]);
            $pdo->prepare('DELETE FROM config WHERE user_id = ?')->execute([$newUserId]);
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$newUserId]);
            return ['success' => false, 'message' => 'Não foi possível criar os arquivos do cliente.'];
        }

        logActivity($pdo, 'Criou usuário', 'Usuários', 'Usuário ' . $username . ' criado.', currentUserId());
        return ['success' => true, 'message' => 'Usuário criado com sucesso.', 'id' => $newUserId, 'token' => $token];
    } catch (Throwable $e) {
        return ['success' => false, 'message' => 'Erro ao criar usuário.'];
    }
}

function updateUser(PDO $pdo, int $userId, string $username, string $status, ?string $password = null): array {
    $username = trim($username);
    if (strlen($username) < 3) {
        return ['success' => false, 'message' => 'Usuário deve ter pelo menos 3 caracteres.'];
    }
    if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
        return ['success' => false, 'message' => 'Status inválido.'];
    }
    if ($password !== null && $password !== '' && !validatePassword($password)) {
        return ['success' => false, 'message' => 'A nova senha deve ter pelo menos 4 caracteres.'];
    }

    try {
        $oldStmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
        $oldStmt->execute([$userId]);
        $oldUsername = (string)($oldStmt->fetchColumn() ?: '');

        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
        $stmt->execute([$username, $userId]);
        if ($stmt->fetchColumn()) {
            return ['success' => false, 'message' => 'Esse usuário já existe.'];
        }
        if (clientFolderExistsForOtherUser($pdo, $username, $userId)) {
            return ['success' => false, 'message' => 'Esse nome de cliente gera uma pasta já utilizada por outro cliente. Escolha outro nome.'];
        }

        if ($password !== null && $password !== '') {
            $stmt = $pdo->prepare('UPDATE users SET username = ?, status = ?, password = ? WHERE id = ?');
            $stmt->execute([$username, $status, password_hash($password, PASSWORD_DEFAULT), $userId]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET username = ?, status = ? WHERE id = ?');
            $stmt->execute([$username, $status, $userId]);
        }

        // Se o nome mudou, migra a pasta antiga para /update/NOVO_NOME/.
        if ($oldUsername !== '' && $oldUsername !== $username) {
            $oldDir = dirname(__DIR__) . '/update/' . clientFolderName($oldUsername, $userId);
            $newDir = getClientUpdateDirectory($pdo, $userId);
            if ($oldDir !== $newDir && is_dir($oldDir)) {
                if (!is_dir(dirname($newDir)) && !mkdir(dirname($newDir), 0755, true) && !is_dir(dirname($newDir))) {
                    return ['success' => false, 'message' => 'Não foi possível preparar a pasta do cliente.'];
                }

                // Se a nova pasta já existir, não sobrescrevemos silenciosamente:
                // os arquivos novos serão gerados nela e a pasta antiga é removida
                // somente depois de a geração terminar com sucesso.
                if (!is_dir($newDir) && !@rename($oldDir, $newDir)) {
                    return ['success' => false, 'message' => 'Não foi possível renomear a pasta do cliente.'];
                }

                if (is_dir($newDir) && $oldDir !== $newDir && is_dir($oldDir)) {
                    // Colisão rara após normalização do username. Mantém a pasta nova
                    // e remove a antiga somente depois da geração dos arquivos.
                }
            }
        }

        if (!generateClientUpdateFiles($pdo, $userId)) {
            return ['success' => false, 'message' => 'Usuário atualizado, mas não foi possível regenerar os arquivos do cliente.'];
        }

        // Se houve colisão e a pasta antiga ainda existe, ela é descartada após
        // a nova configuração ter sido gerada com sucesso.
        if ($oldUsername !== '' && $oldUsername !== $username) {
            $oldDir = dirname(__DIR__) . '/update/' . clientFolderName($oldUsername, $userId);
            $newDir = getClientUpdateDirectory($pdo, $userId);
            if ($oldDir !== $newDir && is_dir($oldDir)) {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($oldDir, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($it as $item) {
                    $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
                }
                @rmdir($oldDir);
            }
        }

        logActivity($pdo, 'Atualizou usuário', 'Usuários', 'Usuário ID ' . $userId . ' atualizado.', currentUserId());
        return ['success' => true, 'message' => 'Usuário atualizado com sucesso.'];
    } catch (Throwable $e) {
        return ['success' => false, 'message' => 'Erro ao atualizar usuário.'];
    }
}

function changeUserPassword(PDO $pdo, int $userId, string $currentPassword, string $newPassword, string $confirmPassword): array {
    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        return ['success' => false, 'message' => 'Preencha todos os campos.'];
    }
    if (!validatePassword($newPassword)) {
        return ['success' => false, 'message' => 'A nova senha deve ter pelo menos 4 caracteres.'];
    }
    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'message' => 'As senhas não coincidem.'];
    }
    if ($newPassword === $currentPassword) {
        return ['success' => false, 'message' => 'A nova senha não pode ser igual à atual.'];
    }

    $user = getUser($pdo, $userId);
    if (!$user || !password_verify($currentPassword, (string)$user['password'])) {
        return ['success' => false, 'message' => 'Senha atual incorreta.'];
    }

    try {
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
        logActivity($pdo, 'Alterou senha', 'Usuários', 'Senha alterada.', currentUserId());
        return ['success' => true, 'message' => 'Senha alterada com sucesso.'];
    } catch (Throwable $e) {
        return ['success' => false, 'message' => 'Erro ao alterar senha.'];
    }
}

function regenerateApiToken(PDO $pdo, int $userId): ?string {
    $token = generateApiToken();
    $hash = hashApiToken($token);

    try {
        $stmt = $pdo->prepare('UPDATE users SET update_token_hash = ?, update_token = NULL WHERE id = ?');
        $stmt->execute([$hash, $userId]);
        logActivity($pdo, 'Regenerou token', 'Usuários', 'Token de API regenerado para usuário ID ' . $userId . '.', currentUserId());
        return $stmt->rowCount() >= 0 ? $token : null;
    } catch (Throwable $e) {
        return null;
    }
}

function revokeApiToken(PDO $pdo, int $userId): bool {
    $stmt = $pdo->prepare('UPDATE users SET update_token_hash = NULL, update_token = NULL WHERE id = ?');
    $ok = $stmt->execute([$userId]);
    if ($ok) logActivity($pdo, 'Revogou token', 'Usuários', 'Token de API revogado para usuário ID ' . $userId . '.', currentUserId());
    return $ok;
}

function verifyApiToken(PDO $pdo, string $token): ?array {
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    $hash = hashApiToken($token);
    $stmt = $pdo->prepare('SELECT id, username, role, status FROM users WHERE update_token_hash = ? LIMIT 1');
    $stmt->execute([$hash]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active') {
        return null;
    }
    return $user;
}

function getPublicBaseUrl(): string {
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    $host = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', $host) ?: '';

    if ($host !== '') {
        return $scheme . '://' . $host;
    }

    if (defined('SITE_URL') && SITE_URL !== '') {
        return rtrim((string)SITE_URL, '/');
    }

    return '';
}

function absoluteUrl(string $path): string {
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    return rtrim(getPublicBaseUrl(), '/') . '/' . ltrim($path, '/');
}

function getApiUrl(): string {
    return rtrim(getPublicBaseUrl(), '/') . '/api/config.php';
}

function getThemeApiUrl(): string {
    return rtrim(getPublicBaseUrl(), '/') . '/api/tema.php';
}

function getSmsApiUrl(): string {
    return rtrim(getPublicBaseUrl(), '/') . '/api/sms.php';
}

function userUpdateUrl(int $userId, ?string $token = null): string {
    if ($token === null || $token === '') {
        return getApiUrl();
    }
    return getApiUrl() . '?token=' . rawurlencode($token);
}


/** Retorna um nome seguro para a pasta pública do cliente. */
function clientFolderName(string $username, int $userId = 0): string {
    $name = trim($username);
    $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
    $name = strtolower($name);
    $name = preg_replace('/[^a-z0-9]+/', '-', $name) ?? '';
    $name = trim($name, '-');
    if ($name === '') {
        $name = 'cliente';
    }
    // O username é único na tabela users, então ele identifica a pasta do cliente.
    // O ID é recebido por compatibilidade, mas não faz parte do nome da pasta.
    return $name;
}

function clientFolderExistsForOtherUser(PDO $pdo, string $username, int $userId = 0): bool {
    $folder = clientFolderName($username, $userId);
    $stmt = $pdo->query('SELECT id, username FROM users');
    foreach ($stmt->fetchAll() as $row) {
        $otherId = (int)$row['id'];
        if ($otherId === $userId) {
            continue;
        }
        if (clientFolderName((string)$row['username'], $otherId) === $folder) {
            return true;
        }
    }
    return false;
}

function getClientFolder(PDO $pdo, int $userId): string {
    $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $username = (string)($stmt->fetchColumn() ?: 'cliente');
    return clientFolderName($username, $userId);
}

function getClientUpdateDirectory(PDO $pdo, int $userId): string {
    return dirname(__DIR__) . '/update/' . getClientFolder($pdo, $userId);
}

function getClientUpdateUrl(PDO $pdo, int $userId, string $file = '4g.json'): string {
    return rtrim(getPublicBaseUrl(), '/') . '/update/' . rawurlencode(getClientFolder($pdo, $userId)) . '/' . rawurlencode($file);
}

function buildUserConfigJson(PDO $pdo, int $userId): array {
    $config = getConfig($pdo, $userId);
    $theme = getUserTheme($pdo, $userId);
    $message = getUserMessage($pdo, $userId);
    $servers = getServers($pdo, $userId);
    $networks = getNetworks($pdo, $userId);

    $udp = [];
    $udpPorts = trim((string)($config['udp_ports'] ?? ''));
    if ($udpPorts !== '') {
        foreach (preg_split('/[;,#,\s]+/', $udpPorts, -1, PREG_SPLIT_NO_EMPTY) as $port) {
            $udp[] = ['Porta' => (string)$port];
        }
    }

    $serverList = [];
    foreach ($servers as $s) {
        $serverList[] = [
            'Name' => (string)$s['name'],
            'TYPE' => (string)$s['type'],
            'FLAG' => absoluteUrl((string)$s['flag_url']),
            'ServerIP' => (string)$s['server_ip'],
            'CheckUser' => (string)$s['check_user'],
            'ServerPort' => (string)$s['server_port'],
            'SSLPort' => (string)$s['ssl_port'],
            'USER' => (string)$s['username'],
            'PASS' => (string)$s['password'],
        ];
    }

    $networkList = [];
    foreach ($networks as $n) {
        $payload = '';
        if (!empty($n['payload_file'])) {
            $stmt = $pdo->prepare('SELECT content FROM payloads WHERE network_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([(int)$n['id'], $userId]);
            $row = $stmt->fetch();
            $payload = (string)($row['content'] ?? '');
        }
        $networkList[] = [
            'Name' => (string)$n['name'],
            'FLAG' => absoluteUrl((string)$n['flag_url']),
            'Payload' => $payload,
            'SNI' => (string)$n['sni'],
            'TlsIP' => (string)$n['tls_ip'],
            'ProxyIP' => (string)$n['proxy_ip'],
            'ProxyPort' => (string)$n['proxy_port'],
            'Info' => (string)$n['info'],
        ];
    }

    return [
        'Version' => (string)($config['version'] ?? '0'),
        'AppName' => (string)($theme['app_name'] ?? 'CYBERCOARI VIP'),
        'ReleaseNotes' => (string)($message['notes'] ?? ''),
        'UrlUpdate' => (string)($config['update_url'] ?? getApiUrl()),
        'Sms' => (string)($config['sms_url'] ?? getSmsApiUrl()),
        'logoonline' => absoluteUrl((string)($config['logoonline'] ?? '')),
        'fundoonline' => absoluteUrl((string)($config['fundoonline'] ?? '')),
        'logonline' => absoluteUrl((string)($config['logonline'] ?? '')),
        'rodapeonline' => absoluteUrl((string)($config['rodapeonline'] ?? '')),
        'EmailFeedback' => (string)($config['email_feedback'] ?? ''),
        'UrlContato' => (string)($config['contato'] ?? ''),
        'UrlTermos' => (string)($config['url_termos'] ?? ''),
        'CheckUser' => (string)($config['check_user'] ?? 'true'),
        'CorBordaCampo' => (string)($config['cor_borda_campo'] ?? '#FF0000'),
        'CorIconeUsuario' => (string)($config['cor_icone_usuario'] ?? '#FF0000'),
        'CorIconeSenha' => (string)($config['cor_icone_senha'] ?? '#0000FF'),
        'CorBotaoStart' => (string)($config['cor_botao_start'] ?? '#0000FF'),
        'CorBordaBotaoStart' => (string)($config['cor_borda_botao_start'] ?? '#FF0000'),
        'CorFonteBotaoStart' => (string)($config['cor_fonte_botao_start'] ?? '#00FFFF'),
        'CorTituloApp' => (string)($config['cor_titulo_app'] ?? '#FF00BAFF'),
        'CorStatus' => (string)($config['cor_status'] ?? '#FFFFFFFF'),
        'CorTextoVersao' => (string)($config['cor_texto_versao'] ?? '#FFFFFFFF'),
        'CorNumeroVersao' => (string)($config['cor_numero_versao'] ?? '#FF00FF00'),
        'CorIP' => (string)($config['cor_ip'] ?? '#FF00FF00'),
        'CorOperadora' => (string)($config['cor_operadora'] ?? '#FF00FF00'),
        'CorIconeAtualizar' => (string)($config['cor_icone_atualizar'] ?? '#FF00DE0C'),
        'EspessuraBordaCampo' => (int)($config['espessura_borda_campo'] ?? 3),
        'Udp' => $udp,
        'UdpPort' => $udpPorts,
        'Servers' => $serverList,
        'Networks' => $networkList,
    ];
}

function buildUserThemeJson(PDO $pdo, int $userId): array {
    $theme = getUserTheme($pdo, $userId);
    return [
        'Version' => (string)($theme['version'] ?? '1'),
        'Update' => getThemeApiUrl(),
        'AppName' => (string)($theme['app_name'] ?? 'ConnectPlus'),
        'ImgFundo' => absoluteUrl((string)($theme['img_fundo'] ?? '')),
        'ImgLogo' => absoluteUrl((string)($theme['img_logo'] ?? '')),
        'ImgBanner' => absoluteUrl((string)($theme['img_banner'] ?? '')),
        'ImgMenu' => absoluteUrl((string)($theme['img_menu'] ?? '')),
        'ImgLogs' => absoluteUrl((string)($theme['img_logs'] ?? '')),
        'ImgCheck' => absoluteUrl((string)($theme['img_check'] ?? '')),
        'ImgUser' => absoluteUrl((string)($theme['img_user'] ?? '')),
        'ImgPass' => absoluteUrl((string)($theme['img_pass'] ?? '')),
        'ImgUpdate' => absoluteUrl((string)($theme['img_update'] ?? '')),
        'ColorOne' => (string)($theme['color_one'] ?? '#333333'),
        'ColorTwo' => (string)($theme['color_two'] ?? '#333333'),
        'ColorStarter' => (string)($theme['color_starter'] ?? '#333333'),
        'ColorDialogs' => (string)($theme['color_dialogs'] ?? '#ffffff'),
        'ColorButtons' => (string)($theme['color_buttons'] ?? '#667eea'),
    ];
}

function ensureUserTheme(PDO $pdo, int $userId): bool {
    $stmt = $pdo->prepare('SELECT id FROM user_theme WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    if ($stmt->fetchColumn()) return true;
    $stmt = $pdo->prepare('INSERT INTO user_theme (user_id, version, app_name) VALUES (?, ?, ?)');
    return $stmt->execute([$userId, '1', 'ConnectPlus']);
}

function getUserTheme(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('SELECT * FROM user_theme WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if ($row) return $row;
    return [
        'version'=>'1','app_name'=>'ConnectPlus','img_fundo'=>'','img_logo'=>'','img_banner'=>'','img_menu'=>'','img_logs'=>'','img_check'=>'','img_user'=>'','img_pass'=>'','img_update'=>'',
        'color_one'=>'#333333','color_two'=>'#333333','color_starter'=>'#333333','color_dialogs'=>'#ffffff','color_buttons'=>'#667eea'
    ];
}

function getMessageTableColumns(PDO $pdo): array {
    static $columns = null;
    if ($columns !== null) return $columns;
    try {
        $rows = $pdo->query('SHOW COLUMNS FROM messages')->fetchAll();
        $columns = [];
        foreach ($rows as $row) {
            $columns[(string)$row['Field']] = true;
        }
    } catch (Throwable $e) {
        $columns = [];
    }
    return $columns;
}

function getClientApiTokenForDisplay(PDO $pdo, int $userId): string {
    $config = getConfig($pdo, $userId);
    $url = trim((string)($config['update_url'] ?? ''));
    if ($url !== '' && preg_match('/[?&]token=([^&]+)/', $url, $m)) {
        return rawurldecode($m[1]);
    }
    return '';
}

function getClientApiUrlForDisplay(PDO $pdo, int $userId, string $endpoint): string {
    $token = getClientApiTokenForDisplay($pdo, $userId);
    $url = rtrim(getPublicBaseUrl(), '/') . '/' . ltrim($endpoint, '/');
    return $url . ($token !== '' ? '?token=' . rawurlencode($token) : '?token=COLE_O_TOKEN_AQUI');
}

function getUserMessage(PDO $pdo, int $userId): ?array {
    $columns = getMessageTableColumns($pdo);
    try {
        if (isset($columns['notes'])) {
            $updateField = isset($columns['update_url']) ? 'update_url' : "'' AS update_url";
            $stmt = $pdo->prepare("SELECT id, user_id, version, notes, {$updateField}, created_at, updated_at FROM messages WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            return $stmt->fetch() ?: null;
        }
        if (isset($columns['message'])) {
            $stmt = $pdo->prepare('SELECT id, user_id, title, message, status, created_at, updated_at FROM messages WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            if (!$row) return null;
            $row['version'] = ctype_digit((string)($row['title'] ?? '')) ? (string)$row['title'] : '1';
            $row['notes'] = (string)($row['message'] ?? '');
            $row['update_url'] = '';
            return $row;
        }
    } catch (Throwable $e) {
        return null;
    }
    return null;
}

function buildUserSmsJson(PDO $pdo, int $userId): array {
    $message = getUserMessage($pdo, $userId);
    return [
        'Version' => (string)($message['version'] ?? '1'),
        'Update' => (string)($message['update_url'] ?? ''),
        'Notes' => (string)($message['notes'] ?? ''),
    ];
}

function saveUserMessage(PDO $pdo, int $userId, string $notes): bool {
    $notes = trim($notes);
    if ($notes === '') return false;
    $columns = getMessageTableColumns($pdo);
    $existing = getUserMessage($pdo, $userId);
    $version = (int)($existing['version'] ?? 0);
    if ($version < 1) $version = 1;
    if ($existing && trim((string)($existing['notes'] ?? '')) !== $notes) $version++;
    try {
        if (isset($columns['notes'])) {
            if ($existing) {
                $stmt = $pdo->prepare('UPDATE messages SET version = ?, notes = ? WHERE user_id = ?');
                $ok = $stmt->execute([(string)$version, $notes, $userId]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO messages (user_id, version, notes) VALUES (?, ?, ?)');
                $ok = $stmt->execute([$userId, (string)$version, $notes]);
            }
        } elseif (isset($columns['message'])) {
            if ($existing) {
                $stmt = $pdo->prepare('UPDATE messages SET title = ?, message = ?, status = ? WHERE user_id = ?');
                $ok = $stmt->execute([(string)$version, $notes, 'active', $userId]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO messages (user_id, title, message, status) VALUES (?, ?, ?, ?)');
                $ok = $stmt->execute([$userId, (string)$version, $notes, 'active']);
            }
        } else {
            return false;
        }
        if ($ok && generateClientUpdateFiles($pdo, $userId)) { logActivity($pdo, 'Publicou mensagem', 'Mensagens', 'Mensagem do aplicativo atualizada.', currentUserId()); return true; }
        return false;
    } catch (Throwable $e) {
        return false;
    }
}

function deleteUserMessage(PDO $pdo, int $userId): bool {
    try {
        $stmt = $pdo->prepare('DELETE FROM messages WHERE user_id = ?');
        $ok = $stmt->execute([$userId]);
        $generated = $ok && generateClientUpdateFiles($pdo, $userId);
        if ($generated && $stmt->rowCount() > 0) logActivity($pdo, 'Excluiu mensagem', 'Mensagens', 'Mensagem do aplicativo excluída.', currentUserId());
        return $generated;
    } catch (Throwable $e) {
        return false;
    }
}

function writeJsonFile(string $path, array $data): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) return false;
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) return false;
    return file_put_contents($path, $json . PHP_EOL, LOCK_EX) !== false;
}

/**
 * Monta o JSON de atualização específico do ATXMOD.
 *
 * O ATX continua usando sua própria configuração/estrutura.
 * Somente a URL de atualização é redirecionada para o arquivo
 * físico do usuário em /update/{usuario}/atx.json.
 */
function buildAtxUpdateJson(PDO $pdo, int $userId): array {
    // Monta diretamente a mesma estrutura usada pela API ATX.
    // Não depende de buildAtxConfig(), pois essa função não existe
    // no includes/atx.php atual do painel.
    if (!function_exists('getAtxConfig') || !function_exists('getAtxServers') || !function_exists('buildAtxServerFromRow')) {
        throw new RuntimeException('Funções ATX necessárias não estão disponíveis.');
    }

    $cfg = getAtxConfig($pdo, $userId);
    $servers = array_map('buildAtxServerFromRow', getAtxServers($pdo, $userId));

    return [
        'Version' => (string)($cfg['version'] ?? '0'),
        'AppName' => (string)($cfg['app_name'] ?? 'ATXMOD'),
        'ReleaseNotes' => (($cfg['release_notes'] ?? '') !== '') ? $cfg['release_notes'] : null,
        'UrlContato' => (string)($cfg['contato'] ?? ''),
        'UrlUpdate' => getClientUpdateUrl($pdo, $userId, 'atx.json'),
        'Logo' => (string)($cfg['logo'] ?? ''),
        'Fundo' => (string)($cfg['fundo'] ?? ''),
        'CheckPop' => (string)($cfg['check_pop'] ?? 'true'),
        'CorMenu' => (string)($cfg['cor_menu'] ?? '#000000'),
        'CorBtcu' => (string)($cfg['cor_btcu'] ?? '#ff0000'),
        'WebView' => (string)($cfg['webview'] ?? ''),
        'Roteador' => (string)($cfg['roteador'] ?? 'true'),
        'StatusIf' => (string)($cfg['status_if'] ?? 'false'),
        'IconCor' => (string)($cfg['icon_cor'] ?? '#ffffff'),
        'Html' => [
            'WebView' => (string)($cfg['html_webview'] ?? ''),
            'HtmlUse' => (string)($cfg['html_use'] ?? 'false'),
            'HtmlAPP' => (string)($cfg['html_app'] ?? ''),
        ],
        'Background' => [
            'Cor' => (string)($cfg['background_cor'] ?? '#000000'),
            'Borda' => (int)($cfg['background_borda'] ?? 35),
        ],
        'Border' => [
            'Cor' => (string)($cfg['border_cor'] ?? '#ff0000'),
            'Borda' => (int)($cfg['border_borda'] ?? 14),
        ],
        'Servers' => $servers,
    ];
}

/**
 * Monta o JSON de atualização específico do ConnectPlus.
 *
 * O JSON existente 4g.json não é alterado.
 * Este arquivo é exclusivo do fluxo de atualização do APK ConnectPlus.
 */
function buildConnectPlusUpdateJson(PDO $pdo, int $userId): array {
    if (!function_exists('buildConnectPlusConfig')) {
        throw new RuntimeException('buildConnectPlusConfig() não está disponível.');
    }

    $data = buildConnectPlusConfig($pdo, $userId);

    if (!is_array($data)) {
        throw new RuntimeException('buildConnectPlusConfig() não retornou um array.');
    }

    $data['Update'] = getClientUpdateUrl($pdo, $userId, 'connectplus.json');

    return $data;
}

function generateClientUpdateFiles(PDO $pdo, int $userId): bool {
    $dir = getClientUpdateDirectory($pdo, $userId);

    // Arquivos atuais: permanecem intactos e continuam sendo gerados.
    $configOk = writeJsonFile($dir . '/4g.json', buildUserConfigJson($pdo, $userId));
    $themeOk = writeJsonFile($dir . '/theme.json', buildUserThemeJson($pdo, $userId));
    $smsOk = writeJsonFile($dir . '/sms.json', buildUserSmsJson($pdo, $userId));

    // Arquivos específicos dos APKs: um por aplicativo, na pasta do usuário.
    $atxOk = writeJsonFile($dir . '/atx.json', buildAtxUpdateJson($pdo, $userId));
    $connectPlusOk = writeJsonFile($dir . '/connectplus.json', buildConnectPlusUpdateJson($pdo, $userId));

    return $configOk && $themeOk && $smsOk && $atxOk && $connectPlusOk;
}

function formatDate(string $date): string {
    $time = strtotime($date);
    return $time ? date('d/m/Y H:i', $time) : '';
}