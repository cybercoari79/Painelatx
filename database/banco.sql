-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geraÃ§Ã£o: 13/10/2026 Ã s 15:52
-- VersÃ£o do servidor: 10.11.19-MariaDB-cll-lve-log
-- VersÃ£o do PHP: 8.4.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `cyberco1_atx`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `atx_config`
--

CREATE TABLE `atx_config` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `version` varchar(50) NOT NULL DEFAULT '0',
  `app_name` varchar(100) NOT NULL DEFAULT 'ATXMOD',
  `release_notes` text DEFAULT NULL,
  `update_url` text DEFAULT NULL,
  `contato` varchar(255) NOT NULL DEFAULT '',
  `logo` text DEFAULT NULL,
  `fundo` text DEFAULT NULL,
  `check_pop` varchar(10) NOT NULL DEFAULT 'true',
  `cor_menu` varchar(20) NOT NULL DEFAULT '#000000',
  `cor_btcu` varchar(20) NOT NULL DEFAULT '#ff0000',
  `webview` text DEFAULT NULL,
  `roteador` varchar(10) NOT NULL DEFAULT 'true',
  `status_if` varchar(10) NOT NULL DEFAULT 'false',
  `icon_cor` varchar(20) NOT NULL DEFAULT '#ffffff',
  `html_webview` text DEFAULT NULL,
  `html_use` varchar(10) NOT NULL DEFAULT 'false',
  `html_app` text DEFAULT NULL,
  `background_cor` varchar(20) NOT NULL DEFAULT '#000000',
  `background_borda` int(11) NOT NULL DEFAULT 35,
  `border_cor` varchar(20) NOT NULL DEFAULT '#ff0000',
  `border_borda` int(11) NOT NULL DEFAULT 14,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `atx_config`
--

INSERT INTO `atx_config` (`id`, `user_id`, `version`, `app_name`, `release_notes`, `update_url`, `contato`, `logo`, `fundo`, `check_pop`, `cor_menu`, `cor_btcu`, `webview`, `roteador`, `status_if`, `icon_cor`, `html_webview`, `html_use`, `html_app`, `background_cor`, `background_borda`, `border_cor`, `border_borda`, `created_at`, `updated_at`) VALUES
(1, 1, '2', 'ATXMOD', 'Teste de SMS', 'https://atx.cybercoari.com.br/api/atx.php?URL=atx_o32Dmu8GrQsHZzxmZxNTHC5xgdenPCQH', '', 'https://cybercoari.com.br/cyber/imagens/logoonline.png', 'https://cybercoari.com.br/cyber/imagens/fundoonline.png', 'true', '#000000', '#FF0000', '', 'true', 'false', '#FFFFFF', '', 'false', '', '#000000', 35, '#FF0000', 14, '2026-10-12 19:49:54', '2026-10-13 13:17:04'),
(7, 2, '0', 'ATXMOD', '', '', '', '', '', 'true', '#000000', '#ff0000', '', 'true', 'false', '#ffffff', '', 'false', '', '#000000', 35, '#ff0000', 14, '2026-10-13 15:05:33', '2026-10-13 15:05:33');

-- --------------------------------------------------------

--
-- Estrutura para tabela `atx_credentials`
--

CREATE TABLE `atx_credentials` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `credential_hash` char(64) NOT NULL,
  `credential_preview` varchar(40) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `atx_credentials`
--

INSERT INTO `atx_credentials` (`id`, `user_id`, `credential_hash`, `credential_preview`, `created_at`, `updated_at`) VALUES
(1, 1, 'ca5591e414bc8f7cd16d69a3d6879cd3f89b7d5bd387e97792722491d087639b', 'atx_o32D...PCQH', '2026-10-12 19:57:55', '2026-10-12 20:44:29'),
(3, 2, '3a6c3efed1a5cddc4c80707ae48be58ae95651e4fb9b88707f4748a0bbcc3b9f', 'atx_jyAc...-Ln6', '2026-10-13 15:07:23', '2026-10-13 15:35:45');

-- --------------------------------------------------------

--
-- Estrutura para tabela `atx_servers`
--

CREATE TABLE `atx_servers` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'ssh',
  `flag_url` text DEFAULT NULL,
  `server_ip` varchar(255) NOT NULL DEFAULT '',
  `check_user` text DEFAULT NULL,
  `server_port` varchar(20) NOT NULL DEFAULT '',
  `ssl_port` varchar(20) NOT NULL DEFAULT '',
  `username` varchar(150) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  `payload` text DEFAULT NULL,
  `sni` varchar(255) NOT NULL DEFAULT '',
  `tls_ip` varchar(255) NOT NULL DEFAULT '',
  `proxy_ip` varchar(255) NOT NULL DEFAULT '',
  `proxy_port` varchar(20) NOT NULL DEFAULT '',
  `info` varchar(255) NOT NULL DEFAULT 'Tlsws',
  `color` varchar(20) NOT NULL DEFAULT '#ffffff',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `atx_servers`
--

INSERT INTO `atx_servers` (`id`, `user_id`, `name`, `type`, `flag_url`, `server_ip`, `check_user`, `server_port`, `ssl_port`, `username`, `password`, `payload`, `sni`, `tls_ip`, `proxy_ip`, `proxy_port`, `info`, `color`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'TIM SSL', 'Premium', 'https://cybercoari.com.br/cyber/imagens/tim.png', 'app.cybercoari.com.br', '', '22', '443', '', '', '', '', '', '', '', 'Tlsws', '#FFFFFF', 'active', '2026-10-13 00:02:08', '2026-10-13 00:02:08');

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs`
--

CREATE TABLE `logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `action`, `module`, `description`, `created_at`) VALUES
(1, 1, 'Atualizou configuraÃ§Ã£o ATX', 'ATX', 'ConfiguraÃ§Ã£o ATX atualizada.', '2026-10-12 19:49:54'),
(2, 1, 'Gerou credencial ATX', 'ATX', 'Credencial ATX rotacionada para usuÃ¡rio ID 1.', '2026-10-12 19:57:55'),
(3, 1, 'Gerou credencial ATX', 'ATX', 'Credencial ATX rotacionada para usuÃ¡rio ID 1.', '2026-10-12 20:44:30'),
(4, 1, 'Atualizou configuraÃ§Ã£o ATX', 'ATX', 'ConfiguraÃ§Ã£o ATX atualizada.', '2026-10-12 20:46:06'),
(5, 1, 'Atualizou configuraÃ§Ã£o ATX', 'ATX', 'ConfiguraÃ§Ã£o ATX atualizada.', '2026-10-13 00:00:20'),
(6, 1, 'Criou servidor ATX', 'ATX', 'Servidor ATX salvo.', '2026-10-13 00:02:08'),
(7, 1, 'Atualizou configuraÃ§Ã£o ATX', 'ATX', 'ConfiguraÃ§Ã£o ATX atualizada.', '2026-10-13 00:05:47'),
(8, 1, 'Atualizou configuraÃ§Ã£o ATX', 'ATX', 'ConfiguraÃ§Ã£o ATX atualizada.', '2026-10-13 13:16:55'),
(9, 1, 'Atualizou configuraÃ§Ã£o ATX', 'ATX', 'ConfiguraÃ§Ã£o ATX atualizada.', '2026-10-13 13:17:04'),
(10, 2, 'Gerou credencial ATX', 'ATX', 'Credencial ATX rotacionada para usuÃ¡rio ID 2.', '2026-10-13 15:07:23'),
(11, 2, 'Gerou credencial ATX', 'ATX', 'Credencial ATX rotacionada para usuÃ¡rio ID 2.', '2026-10-13 15:35:45');

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `update_token_hash` char(64) DEFAULT NULL,
  `update_token` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `status`, `update_token_hash`, `update_token`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$ceehEz77DrbS6AWxQKHZz.HNoy.0Wxo7MmUEI2MHTBLwhxeQO2.Ye', 'admin', 'active', NULL, NULL, '2026-10-12 19:41:50', '2026-10-12 19:41:50'),
(2, 'cyberco1', '$2y$10$sTKDmuBlc7fPJyNjhaqeEO6OhjsYwfhnLiptvTt0QB1Na.mW8tW.G', 'user', 'active', NULL, NULL, '2026-10-13 15:05:33', '2026-10-13 15:05:33');

--
-- Ãndices para tabelas despejadas
--

--
-- Ãndices de tabela `atx_config`
--
ALTER TABLE `atx_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_atx_config_user` (`user_id`);

--
-- Ãndices de tabela `atx_credentials`
--
ALTER TABLE `atx_credentials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_atx_credential_user` (`user_id`),
  ADD UNIQUE KEY `uq_atx_credential_hash` (`credential_hash`);

--
-- Ãndices de tabela `atx_servers`
--
ALTER TABLE `atx_servers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_atx_servers_user` (`user_id`),
  ADD KEY `idx_atx_servers_status` (`user_id`,`status`);

--
-- Ãndices de tabela `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_user` (`user_id`);

--
-- Ãndices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `atx_config`
--
ALTER TABLE `atx_config`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `atx_credentials`
--
ALTER TABLE `atx_credentials`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `atx_servers`
--
ALTER TABLE `atx_servers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;