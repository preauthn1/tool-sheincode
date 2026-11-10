-- Initial schema for Fraudulent Order Processing

CREATE TABLE IF NOT EXISTS `tp_users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `real_name` varchar(120) DEFAULT '',
  `member_level` varchar(20) DEFAULT '1',
  `password` varchar(255) NOT NULL,
  `withdrawal_password` varchar(255) DEFAULT NULL,
  `wallet_currency` varchar(20) DEFAULT 'BTC',
  `wallet_address` varchar(255) DEFAULT '',
  `wallet_note` varchar(255) DEFAULT '',
  `invite_code` varchar(20) NOT NULL,
  `my_invite_code` varchar(20) NOT NULL,
  `invited_by` int(11) DEFAULT NULL,
  `user_type` tinyint(1) DEFAULT '2',
  `agent_id` int(11) DEFAULT NULL,
  `account_role` varchar(20) DEFAULT 'customer',
  `paired_user_id` int(11) DEFAULT NULL,
  `managed_by` int(11) DEFAULT NULL,
  `is_fake_account` tinyint(1) DEFAULT '0',
  `real_user_id` int(11) DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT '0.00',
  `frozen_balance` decimal(10,2) DEFAULT '0.00',
  `total_recharge` decimal(10,2) DEFAULT '0.00',
  `total_commission` decimal(10,2) DEFAULT '0.00',
  `daily_commission` decimal(10,2) DEFAULT '0.00',
  `consecutive_days` int(11) DEFAULT '0',
  `last_work_date` date DEFAULT NULL,
  `total_withdrawn` decimal(10,2) DEFAULT '0.00',
  `credit_score` int(11) DEFAULT '100',
  `last_login_ip` varchar(45) DEFAULT '',
  `last_login_at` datetime DEFAULT NULL,
  `login_count` int(11) DEFAULT '0',
  `status` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_phone` (`phone`),
  UNIQUE KEY `uk_my_invite_code` (`my_invite_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tp_task_configs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `config_date` date NOT NULL,
  `task_amount` decimal(10,2) NOT NULL,
  `total_tasks` int(11) DEFAULT '40',
  `completed_reset_count` int(11) DEFAULT '0',
  `current_round` int(11) DEFAULT '1',
  `manual_completed_count` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_date` (`user_id`, `config_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tp_goods` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `image` varchar(500) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT '0.00',
  `sort` int(11) DEFAULT '0',
  `status` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tp_tasks` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `task_date` date NOT NULL,
  `round_no` int(11) DEFAULT '1',
  `task_index` int(11) DEFAULT '0',
  `good_id` int(11) DEFAULT NULL,
  `task_amount` decimal(10,2) NOT NULL,
  `profit` decimal(10,2) DEFAULT '0.00',
  `is_package` tinyint(1) DEFAULT '0',
  `commission_rate` decimal(5,2) DEFAULT '1.00',
  `profit_shared` tinyint(1) DEFAULT '0',
  `status` tinyint(1) DEFAULT '0',
  `matched_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_date` (`user_id`, `task_date`),
  KEY `idx_user_round_status` (`user_id`, `round_no`, `status`),
  KEY `idx_user_round_index` (`user_id`, `round_no`, `task_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tp_task_package_items` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `good_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT '1',
  `unit_price` decimal(10,2) DEFAULT '0.00',
  `total_price` decimal(10,2) DEFAULT '0.00',
  `commission_rate` decimal(5,2) DEFAULT '0.00',
  `sort_order` int(11) DEFAULT '0',
  `status` tinyint(1) DEFAULT '0',
  `reserved_amount` decimal(10,2) DEFAULT '0.00',
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tp_commissions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `source_user_id` int(11) NOT NULL,
  `target_user_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL DEFAULT '0',
  `profit_amount` decimal(10,2) NOT NULL,
  `commission_rate` decimal(5,2) DEFAULT '25.00',
  `commission_amount` decimal(10,2) NOT NULL,
  `type` varchar(50) DEFAULT 'linked_share',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_source_user` (`source_user_id`),
  KEY `idx_target_user` (`target_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tp_bonus_records` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `bonus_type` varchar(50) NOT NULL,
  `bonus_amount` decimal(10,2) NOT NULL,
  `conditions_met` text,
  `status` tinyint(1) DEFAULT '1',
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tp_balance_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `change_amount` decimal(10,2) NOT NULL,
  `before_balance` decimal(10,2) NOT NULL,
  `after_balance` decimal(10,2) NOT NULL,
  `type` varchar(50) NOT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tp_invite_codes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `is_used` tinyint(1) DEFAULT '0',
  `used_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tp_withdrawals` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `wallet_currency` varchar(20) DEFAULT 'BTC',
  `wallet_address` varchar(255) NOT NULL,
  `wallet_note` varchar(255) DEFAULT '',
  `status` varchar(20) DEFAULT 'pending',
  `admin_id` int(11) DEFAULT NULL,
  `admin_remark` varchar(255) DEFAULT '',
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_created` (`status`, `created_at`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tp_support_conversations` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_phone` varchar(40) DEFAULT '',
  `status` varchar(20) DEFAULT 'open',
  `last_message` varchar(500) DEFAULT '',
  `last_message_type` varchar(20) DEFAULT 'text',
  `unread_user` int(11) DEFAULT '0',
  `unread_admin` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_support_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tp_support_messages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `sender_type` varchar(20) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message_type` varchar(20) DEFAULT 'text',
  `content` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_support_conversation` (`conversation_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tp_invite_codes` (`code`, `is_used`) VALUES
('USA2024001', 0),
('USA2024002', 0),
('USA2024003', 0);
