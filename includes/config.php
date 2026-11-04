<?php
declare(strict_types=1);

// ==========================================
// CONFIGURAÇÕES DO BANCO DE DADOS E DO SITE
// ==========================================
// Substitua os valores abaixo pelos seus dados reais!
define('DB_HOST', 'localhost');
define('DB_NAME', 'nome_do_seu_banco');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha_real');

define('SITE_URL', 'https://seudominio.com.br');
define('SITE_NAME', 'Painel ATX');
define('SALT', 'SEU_SALT_ALEATORIO_LONGO');
define('ATX_GITHUB_TOKEN', 'seu_token_github');

// ==========================================
// LÓGICA DO SISTEMA
// ==========================================
// Verifica se as constantes obrigatórias foram preenchidas
if (!defined('SITE_URL') || !defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
    http_response_code(500); 
    exit('Configuração incompleta. Verifique o config.php');
}

define('SESSION_TIMEOUT', defined('ATX_SESSION_TIMEOUT') ? ATX_SESSION_TIMEOUT : 3600);
date_default_timezone_set('America/Sao_Paulo');

if (session_status() === PHP_SESSION_NONE) { 
    session_name('atx_session'); 
    session_start(); 
}