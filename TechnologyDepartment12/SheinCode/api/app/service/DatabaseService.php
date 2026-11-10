<?php
namespace app\service;

use think\facade\Db;

class DatabaseService
{
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;
        self::ensureTables();
        self::ensureColumns();
    }

    private static function ensureTables(): void
    {
        $tables = [
            'tp_member_levels' => "CREATE TABLE IF NOT EXISTS `tp_member_levels` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `level` int(11) NOT NULL,
                `level_name` varchar(120) NOT NULL,
                `recharge_amount` decimal(12,2) DEFAULT 0,
                `team_count` int(11) DEFAULT 0,
                `valid_count` int(11) DEFAULT 0,
                `daily_orders` int(11) DEFAULT 0,
                `withdraw_min_amount` decimal(12,2) DEFAULT 0,
                `withdraw_max_amount` decimal(12,2) DEFAULT 0,
                `commission_rate` decimal(10,4) DEFAULT 0,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `level` (`level`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tp_withdrawals' => "CREATE TABLE IF NOT EXISTS `tp_withdrawals` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tp_task_package_items' => "CREATE TABLE IF NOT EXISTS `tp_task_package_items` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `task_id` INT NOT NULL,
                `good_id` INT NOT NULL,
                `quantity` INT DEFAULT 1,
                `unit_price` DECIMAL(12,2) DEFAULT 0,
                `total_price` DECIMAL(12,2) DEFAULT 0,
                `commission_rate` DECIMAL(10,4) DEFAULT 0,
                `sort_order` INT DEFAULT 0,
                `status` TINYINT DEFAULT 0,
                `reserved_amount` DECIMAL(12,2) DEFAULT 0,
                `completed_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tp_system_settings' => "CREATE TABLE IF NOT EXISTS `tp_system_settings` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `setting_key` varchar(120) NOT NULL,
                `setting_value` text,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `setting_key` (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tp_partner_sites' => "CREATE TABLE IF NOT EXISTS `tp_partner_sites` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(120) NOT NULL,
                `url` varchar(255) NOT NULL,
                `logo` varchar(255) DEFAULT '',
                `sort` int(11) DEFAULT 0,
                `status` tinyint(1) DEFAULT 1,
                `remark` varchar(255) DEFAULT '',
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tp_support_conversations' => "CREATE TABLE IF NOT EXISTS `tp_support_conversations` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `user_id` INTEGER NOT NULL,
                `user_phone` TEXT DEFAULT '',
                `status` TEXT DEFAULT 'open',
                `last_message` TEXT DEFAULT '',
                `last_message_type` TEXT DEFAULT 'text',
                `unread_user` INTEGER DEFAULT 0,
                `unread_admin` INTEGER DEFAULT 0,
                `created_at` TEXT DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TEXT DEFAULT CURRENT_TIMESTAMP
            )",

            'tp_support_messages' => "CREATE TABLE IF NOT EXISTS `tp_support_messages` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `conversation_id` INTEGER NOT NULL,
                `sender_type` TEXT NOT NULL,
                `sender_id` INTEGER NOT NULL,
                `message_type` TEXT DEFAULT 'text',
                `content` TEXT NOT NULL,
                `created_at` TEXT DEFAULT CURRENT_TIMESTAMP
            )",
        ];

        foreach ($tables as $sql) {
            try {
                Db::execute($sql);
            } catch (\Throwable $e) {
            }
        }

        self::initMemberLevels();
    }

    private static function initMemberLevels(): void
    {
        try {
            $count = (int) Db::name('member_levels')->count();
            if ($count > 0) {
                return;
            }

            $now = date('Y-m-d H:i:s');
            $defaults = [
                ['level' => 3, 'level_name' => 'VIP3', 'recharge_amount' => 3000, 'team_count' => 60, 'valid_count' => 30, 'daily_orders' => 50, 'withdraw_min_amount' => 10, 'withdraw_max_amount' => 50000, 'commission_rate' => 0.025],
                ['level' => 2, 'level_name' => 'VIP2', 'recharge_amount' => 2000, 'team_count' => 50, 'valid_count' => 20, 'daily_orders' => 48, 'withdraw_min_amount' => 10, 'withdraw_max_amount' => 10000, 'commission_rate' => 0.020],
                ['level' => 1, 'level_name' => 'VIP1', 'recharge_amount' => 1000, 'team_count' => 20, 'valid_count' => 10, 'daily_orders' => 45, 'withdraw_min_amount' => 10, 'withdraw_max_amount' => 500000, 'commission_rate' => 0.015],
                ['level' => 0, 'level_name' => 'New Member Activities', 'recharge_amount' => 500, 'team_count' => 0, 'valid_count' => 0, 'daily_orders' => 40, 'withdraw_min_amount' => 100, 'withdraw_max_amount' => 100000, 'commission_rate' => 0.010],
            ];

            foreach ($defaults as $row) {
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
                Db::name('member_levels')->insert($row);
            }
        } catch (\Throwable $e) {
        }
    }

    private static function ensureColumns(): void
    {
        $alterations = [
            'tp_tasks' => [
                'matched_at' => "ALTER TABLE `tp_tasks` ADD COLUMN `matched_at` datetime DEFAULT NULL",
                'round_no' => "ALTER TABLE `tp_tasks` ADD COLUMN `round_no` int(11) NOT NULL DEFAULT 1",
            ],
            'tp_task_configs' => [
                'current_round' => "ALTER TABLE `tp_task_configs` ADD COLUMN `current_round` int(11) NOT NULL DEFAULT 1",
                'manual_completed_count' => "ALTER TABLE `tp_task_configs` ADD COLUMN `manual_completed_count` int(11) DEFAULT NULL",
            ],
            'tp_task_package_items' => [
                'created_at' => "ALTER TABLE `tp_task_package_items` ADD COLUMN `created_at` datetime DEFAULT CURRENT_TIMESTAMP",
                'updated_at' => "ALTER TABLE `tp_task_package_items` ADD COLUMN `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
                'status' => "ALTER TABLE `tp_task_package_items` ADD COLUMN `status` tinyint DEFAULT 0",
                'reserved_amount' => "ALTER TABLE `tp_task_package_items` ADD COLUMN `reserved_amount` decimal(12,2) DEFAULT 0",
                'commission_rate' => "ALTER TABLE `tp_task_package_items` ADD COLUMN `commission_rate` decimal(10,4) DEFAULT 0",
                'completed_at' => "ALTER TABLE `tp_task_package_items` ADD COLUMN `completed_at` datetime DEFAULT NULL",
            ],
            'tp_users' => [
                'account_role' => "ALTER TABLE `tp_users` ADD COLUMN `account_role` varchar(20) DEFAULT 'customer'",
                'paired_user_id' => "ALTER TABLE `tp_users` ADD COLUMN `paired_user_id` int(11) DEFAULT NULL",
                'managed_by' => "ALTER TABLE `tp_users` ADD COLUMN `managed_by` int(11) DEFAULT NULL",
                'is_fake_account' => "ALTER TABLE `tp_users` ADD COLUMN `is_fake_account` tinyint(1) DEFAULT 0",
                'real_user_id' => "ALTER TABLE `tp_users` ADD COLUMN `real_user_id` int(11) DEFAULT NULL",
                'real_name' => "ALTER TABLE `tp_users` ADD COLUMN `real_name` varchar(120) DEFAULT ''",
                'nickname' => "ALTER TABLE `tp_users` ADD COLUMN `nickname` varchar(120) DEFAULT ''",
                'avatar' => "ALTER TABLE `tp_users` ADD COLUMN `avatar` varchar(255) DEFAULT ''",
                'member_level' => "ALTER TABLE `tp_users` ADD COLUMN `member_level` varchar(20) DEFAULT '1'",
                'withdrawal_password' => "ALTER TABLE `tp_users` ADD COLUMN `withdrawal_password` varchar(255) DEFAULT NULL",
                'wallet_currency' => "ALTER TABLE `tp_users` ADD COLUMN `wallet_currency` varchar(20) DEFAULT 'BTC'",
                'wallet_address' => "ALTER TABLE `tp_users` ADD COLUMN `wallet_address` varchar(255) DEFAULT ''",
                'wallet_note' => "ALTER TABLE `tp_users` ADD COLUMN `wallet_note` varchar(255) DEFAULT ''",
                'total_recharge' => "ALTER TABLE `tp_users` ADD COLUMN `total_recharge` decimal(10,2) DEFAULT '0.00'",
                'total_withdrawn' => "ALTER TABLE `tp_users` ADD COLUMN `total_withdrawn` decimal(10,2) DEFAULT '0.00'",
                'credit_score' => "ALTER TABLE `tp_users` ADD COLUMN `credit_score` int(11) DEFAULT 100",
                'last_login_ip' => "ALTER TABLE `tp_users` ADD COLUMN `last_login_ip` varchar(45) DEFAULT ''",
                'last_login_at' => "ALTER TABLE `tp_users` ADD COLUMN `last_login_at` datetime DEFAULT NULL",
                'last_active_at' => "ALTER TABLE `tp_users` ADD COLUMN `last_active_at` datetime DEFAULT NULL",
                'login_count' => "ALTER TABLE `tp_users` ADD COLUMN `login_count` int(11) DEFAULT 0",
                'updated_at' => "ALTER TABLE `tp_users` ADD COLUMN `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
            ],
        ];

        foreach ($alterations as $table => $columns) {
            foreach ($columns as $name => $sql) {
                try {
                    $exists = Db::query("SHOW COLUMNS FROM `{$table}` LIKE '{$name}'");
                    if (!$exists) {
                        Db::execute($sql);
                    }
                } catch (\Throwable $e) {
                }
            }
        }
    }
}
