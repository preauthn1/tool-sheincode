-- SheinCode MySQL 数据库初始化脚本
-- 适用环境: MySQL 5.7+ / 8.0+
-- 使用方式: mysql -u root -p < init_mysql.sql
-- 执行前请确保已创建数据库: CREATE DATABASE IF NOT EXISTS shein_code DEFAULT CHARSET=utf8mb4;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. tp_users - 用户表
-- ============================================================
DROP TABLE IF EXISTS `tp_users`;
CREATE TABLE `tp_users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(40) NOT NULL COMMENT '手机号',
  `real_name` varchar(120) DEFAULT '' COMMENT '真实姓名',
  `nickname` varchar(120) DEFAULT '' COMMENT '昵称',
  `avatar` varchar(255) DEFAULT '' COMMENT '头像',
  `member_level` varchar(20) DEFAULT '1' COMMENT '会员等级',
  `password` varchar(255) NOT NULL COMMENT '密码',
  `withdrawal_password` varchar(255) DEFAULT NULL COMMENT '提现密码',
  `wallet_currency` varchar(20) DEFAULT 'BTC' COMMENT '钱包币种',
  `wallet_address` varchar(255) DEFAULT '' COMMENT '钱包地址',
  `wallet_note` varchar(255) DEFAULT '' COMMENT '钱包备注',
  `invite_code` varchar(20) NOT NULL COMMENT '注册时使用的邀请码',
  `my_invite_code` varchar(20) NOT NULL COMMENT '用户自己的邀请码',
  `invited_by` int(11) DEFAULT NULL COMMENT '邀请人ID',
  `user_type` tinyint(1) DEFAULT 2 COMMENT '1=代理, 2=客户',
  `agent_id` int(11) DEFAULT NULL COMMENT '所属代理ID',
  `account_role` varchar(20) DEFAULT 'customer' COMMENT 'customer/agent/managed',
  `paired_user_id` int(11) DEFAULT NULL COMMENT '配对用户ID(托管账号)',
  `managed_by` int(11) DEFAULT NULL COMMENT '托管人ID',
  `is_fake_account` tinyint(1) DEFAULT 0 COMMENT '是否为托管账号',
  `real_user_id` int(11) DEFAULT NULL COMMENT '真实用户ID(托管账号指向)',
  `balance` decimal(12,2) DEFAULT 0.00 COMMENT '账户余额',
  `frozen_balance` decimal(12,2) DEFAULT 0.00 COMMENT '冻结金额',
  `total_recharge` decimal(12,2) DEFAULT 0.00 COMMENT '累计充值',
  `total_commission` decimal(12,2) DEFAULT 0.00 COMMENT '累计佣金',
  `daily_commission` decimal(12,2) DEFAULT 0.00 COMMENT '今日佣金',
  `consecutive_days` int(11) DEFAULT 0 COMMENT '连续完成天数',
  `last_work_date` date DEFAULT NULL COMMENT '最后工作日期',
  `total_withdrawn` decimal(12,2) DEFAULT 0.00 COMMENT '累计提现',
  `credit_score` int(11) DEFAULT 100 COMMENT '信用分',
  `last_login_ip` varchar(45) DEFAULT '' COMMENT '最后登录IP',
  `last_login_at` datetime DEFAULT NULL COMMENT '最后登录时间',
  `last_active_at` datetime DEFAULT NULL COMMENT '最后活跃时间(心跳)',
  `login_count` int(11) DEFAULT 0 COMMENT '登录次数',
  `status` tinyint(1) DEFAULT 1 COMMENT '1=正常, 0=禁用',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_phone` (`phone`),
  UNIQUE KEY `uk_my_invite_code` (`my_invite_code`),
  KEY `idx_invite_code` (`invite_code`),
  KEY `idx_invited_by` (`invited_by`),
  KEY `idx_agent_id` (`agent_id`),
  KEY `idx_user_type` (`user_type`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';


-- ============================================================
-- 2. tp_tasks - 任务表
-- ============================================================
DROP TABLE IF EXISTS `tp_tasks`;
CREATE TABLE `tp_tasks` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `agent_id` int(11) NOT NULL COMMENT '代理ID',
  `task_date` date NOT NULL COMMENT '任务日期',
  `round_no` int(11) DEFAULT 1 COMMENT 'task round number',
  `task_index` int(11) DEFAULT 0 COMMENT '当日任务序号',
  `good_id` int(11) DEFAULT NULL COMMENT '关联商品ID',
  `task_amount` decimal(12,2) NOT NULL COMMENT '任务金额',
  `profit` decimal(12,2) DEFAULT 0 COMMENT '利润',
  `is_package` tinyint(1) DEFAULT 0 COMMENT '是否为套餐任务',
  `commission_rate` decimal(10,4) DEFAULT 1 COMMENT '佣金比例',
  `profit_shared` tinyint(1) DEFAULT 0 COMMENT '利润是否已分配',
  `status` tinyint(1) DEFAULT 0 COMMENT '0=待完成, 1=已完成, 2=已取消',
  `matched_at` datetime DEFAULT NULL COMMENT '匹配时间',
  `completed_at` datetime DEFAULT NULL COMMENT '完成时间',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_agent_id` (`agent_id`),
  KEY `idx_task_date` (`task_date`),
  KEY `idx_status` (`status`),
  KEY `idx_user_date` (`user_id`, `task_date`),
  KEY `idx_user_round_status` (`user_id`, `round_no`, `status`),
  KEY `idx_user_round_index` (`user_id`, `round_no`, `task_index`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='任务表';


-- ============================================================
-- 3. tp_goods - 商品表
-- ============================================================
DROP TABLE IF EXISTS `tp_goods`;
CREATE TABLE `tp_goods` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT '商品名称',
  `image` varchar(500) DEFAULT NULL COMMENT '商品图片URL',
  `price` decimal(12,2) DEFAULT 0 COMMENT '商品价格',
  `sort` int(11) DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) DEFAULT 1 COMMENT '1=上架, 0=下架',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品表';


-- ============================================================
-- 4. tp_task_package_items - 任务套餐明细表
-- ============================================================
DROP TABLE IF EXISTS `tp_task_package_items`;
CREATE TABLE `tp_task_package_items` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL COMMENT '任务ID',
  `good_id` int(11) NOT NULL COMMENT '商品ID',
  `quantity` int(11) DEFAULT 1 COMMENT '购买数量',
  `unit_price` decimal(12,2) DEFAULT 0 COMMENT '商品单价',
  `total_price` decimal(12,2) DEFAULT 0 COMMENT '商品总价',
  `commission_rate` decimal(10,4) DEFAULT 0 COMMENT '佣金比例',
  `sort_order` int(11) DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) DEFAULT 0 COMMENT '0=待支付, 1=已支付, 2=已退款',
  `reserved_amount` decimal(12,2) DEFAULT 0 COMMENT '冻结金额',
  `completed_at` datetime DEFAULT NULL COMMENT '完成时间',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_good_id` (`good_id`),
  KEY `idx_status` (`status`),
  KEY `idx_task_status` (`task_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='任务套餐明细表';


-- ============================================================
-- 5. tp_balance_logs - 余额变动日志表
-- ============================================================
DROP TABLE IF EXISTS `tp_balance_logs`;
CREATE TABLE `tp_balance_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `change_amount` decimal(12,2) NOT NULL COMMENT '变动金额(正负)',
  `before_balance` decimal(12,2) NOT NULL COMMENT '变动前余额',
  `after_balance` decimal(12,2) NOT NULL COMMENT '变动后余额',
  `type` varchar(50) NOT NULL COMMENT '类型: recharge/withdraw/task_profit/commission/bonus/freeze/unfreeze',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `operator_id` int(11) DEFAULT NULL COMMENT '操作人ID(管理员)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='余额变动日志表';


-- ============================================================
-- 6. tp_withdrawals - 提现记录表
-- ============================================================
DROP TABLE IF EXISTS `tp_withdrawals`;
CREATE TABLE `tp_withdrawals` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `amount` decimal(10,2) NOT NULL COMMENT '提现金额',
  `wallet_currency` varchar(20) DEFAULT 'BTC' COMMENT '钱包币种',
  `wallet_address` varchar(255) NOT NULL COMMENT '钱包地址',
  `wallet_note` varchar(255) DEFAULT '' COMMENT '钱包备注',
  `status` varchar(20) DEFAULT 'pending' COMMENT 'pending=待审核, approved=已通过, rejected=已拒绝',
  `admin_id` int(11) DEFAULT NULL COMMENT '处理管理员ID',
  `admin_remark` varchar(255) DEFAULT '' COMMENT '管理员备注',
  `reviewed_at` datetime DEFAULT NULL COMMENT '审核时间',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status_created` (`status`, `created_at`),
  KEY `idx_admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='提现记录表';


-- ============================================================
-- 7. tp_invite_codes - 邀请码表
-- ============================================================
DROP TABLE IF EXISTS `tp_invite_codes`;
CREATE TABLE `tp_invite_codes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL COMMENT '邀请码',
  `is_used` tinyint(1) DEFAULT 0 COMMENT '是否已使用',
  `used_by` int(11) DEFAULT NULL COMMENT '使用人用户ID',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_is_used` (`is_used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邀请码表';


-- ============================================================
-- 8. tp_commissions - 佣金记录表
-- ============================================================
DROP TABLE IF EXISTS `tp_commissions`;
CREATE TABLE `tp_commissions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `source_user_id` int(11) NOT NULL COMMENT '来源用户ID(被邀请人)',
  `target_user_id` int(11) NOT NULL COMMENT '目标用户ID(邀请人)',
  `task_id` int(11) DEFAULT 0 COMMENT '关联任务ID',
  `profit_amount` decimal(12,2) NOT NULL COMMENT '利润金额',
  `commission_rate` decimal(10,4) DEFAULT 25 COMMENT '佣金比例(%)',
  `commission_amount` decimal(12,2) NOT NULL COMMENT '佣金金额',
  `type` varchar(30) DEFAULT 'linked_share' COMMENT 'linked_share=链式分享奖励',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_source_user_id` (`source_user_id`),
  KEY `idx_target_user_id` (`target_user_id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='佣金记录表';


-- ============================================================
-- 9. tp_bonus_records - 奖金记录表
-- ============================================================
DROP TABLE IF EXISTS `tp_bonus_records`;
CREATE TABLE `tp_bonus_records` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `bonus_type` varchar(30) NOT NULL COMMENT 'daily_task=每日任务奖金, team_bonus=团队奖金',
  `bonus_amount` decimal(12,2) NOT NULL COMMENT '奖金金额',
  `conditions_met` varchar(500) DEFAULT NULL COMMENT '满足的条件(JSON)',
  `status` tinyint(1) DEFAULT 1 COMMENT '1=未发放, 2=已发放',
  `paid_at` datetime DEFAULT NULL COMMENT '发放时间',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_bonus_type` (`bonus_type`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='奖金记录表';


-- ============================================================
-- 10. tp_task_configs - 任务配置表
-- ============================================================
DROP TABLE IF EXISTS `tp_task_configs`;
CREATE TABLE `tp_task_configs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NOT NULL COMMENT '代理ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `config_date` date NOT NULL COMMENT '配置日期',
  `task_amount` decimal(12,2) NOT NULL COMMENT '任务金额',
  `total_tasks` int(11) DEFAULT 40 COMMENT '当日总任务数',
  `completed_reset_count` int(11) DEFAULT 0 COMMENT 'completed count reset baseline',
  `current_round` int(11) DEFAULT 1 COMMENT 'current task round',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '是否启用',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_date` (`user_id`, `config_date`),
  KEY `idx_agent_id` (`agent_id`),
  KEY `idx_config_date` (`config_date`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='任务配置表';


-- ============================================================
-- 11. tp_member_levels - 会员等级表
-- ============================================================
DROP TABLE IF EXISTS `tp_member_levels`;
CREATE TABLE `tp_member_levels` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `level` int(11) NOT NULL COMMENT '等级(0-3)',
  `level_name` varchar(120) NOT NULL COMMENT '等级名称',
  `recharge_amount` decimal(12,2) DEFAULT 0 COMMENT '累计充值要求',
  `team_count` int(11) DEFAULT 0 COMMENT '团队人数要求',
  `valid_count` int(11) DEFAULT 0 COMMENT '有效人数要求',
  `daily_orders` int(11) DEFAULT 0 COMMENT '每日订单要求',
  `withdraw_min_amount` decimal(12,2) DEFAULT 0 COMMENT '最小提现金额',
  `withdraw_max_amount` decimal(12,2) DEFAULT 0 COMMENT '最大提现金额',
  `commission_rate` decimal(10,4) DEFAULT 0 COMMENT '佣金比例',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员等级表';


-- ============================================================
-- 12. tp_system_settings - 系统配置表
-- ============================================================
DROP TABLE IF EXISTS `tp_system_settings`;
CREATE TABLE `tp_system_settings` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(120) NOT NULL COMMENT '配置键',
  `setting_value` text COMMENT '配置值',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统配置表';


-- ============================================================
-- 13. tp_partner_sites - 合作站点表
-- ============================================================
DROP TABLE IF EXISTS `tp_partner_sites`;
CREATE TABLE `tp_partner_sites` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL COMMENT '站点名称',
  `url` varchar(255) NOT NULL COMMENT '站点URL',
  `logo` varchar(255) DEFAULT '' COMMENT '站点Logo',
  `sort` int(11) DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) DEFAULT 1 COMMENT '0=禁用, 1=启用',
  `remark` varchar(255) DEFAULT '' COMMENT '备注',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='合作站点表';


-- ============================================================
-- 14. tp_support_conversations - 客服会话表
-- ============================================================
DROP TABLE IF EXISTS `tp_support_conversations`;
CREATE TABLE `tp_support_conversations` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `user_phone` varchar(40) DEFAULT '' COMMENT '用户手机号',
  `status` varchar(20) DEFAULT 'open' COMMENT 'open=进行中, closed=已关闭',
  `last_message` varchar(500) DEFAULT '' COMMENT '最后一条消息',
  `last_message_type` varchar(20) DEFAULT 'text' COMMENT 'text/image/file',
  `unread_user` int(11) DEFAULT 0 COMMENT '用户未读数',
  `unread_admin` int(11) DEFAULT 0 COMMENT '管理员未读数',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客服会话表';


-- ============================================================
-- 15. tp_support_messages - 客服消息表
-- ============================================================
DROP TABLE IF EXISTS `tp_support_messages`;
CREATE TABLE `tp_support_messages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL COMMENT '会话ID',
  `sender_type` varchar(20) NOT NULL COMMENT 'user=用户, admin=管理员, system=系统',
  `sender_id` int(11) NOT NULL COMMENT '发送者ID',
  `message_type` varchar(20) DEFAULT 'text' COMMENT 'text/image/file',
  `content` text NOT NULL COMMENT '消息内容',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_conversation_id` (`conversation_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客服消息表';


-- ============================================================
-- 初始化数据
-- ============================================================

-- 邀请码
INSERT INTO `tp_invite_codes` (`code`, `is_used`) VALUES
('USA2024001', 0),
('USA2024002', 0),
('USA2024003', 0),
('USA2024999', 0);

-- 会员等级
INSERT INTO `tp_member_levels` (`level`, `level_name`, `recharge_amount`, `team_count`, `valid_count`, `daily_orders`, `withdraw_min_amount`, `withdraw_max_amount`, `commission_rate`) VALUES
(3, 'VIP3', 3000.00, 60, 30, 50, 10.00, 50000.00, 0.0250),
(2, 'VIP2', 2000.00, 50, 20, 48, 10.00, 10000.00, 0.0200),
(1, 'VIP1', 1000.00, 20, 10, 45, 10.00, 500000.00, 0.0150),
(0, 'New Member Activities', 500.00, 0, 0, 40, 100.00, 100000.00, 0.0100);

-- 商品
INSERT INTO `tp_goods` (`id`, `name`, `image`, `price`, `sort`, `status`) VALUES
(1, 'Premium Leather Shoulder Bag', 'https://images.unsplash.com/photo-1591561954557-26941169b49e?auto=format&fit=crop&w=500&q=80', 128.00, 40, 1),
(2, 'Diamond Dial Stainless Watch', 'https://images.unsplash.com/photo-1524592094714-0f0654e20314?auto=format&fit=crop&w=500&q=80', 260.00, 30, 1),
(3, 'Gold Chain Necklace', 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?auto=format&fit=crop&w=500&q=80', 188.00, 20, 1),
(4, 'Luxury Mini Satchel Bag', 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=500&q=80', 146.00, 10, 1);

-- 测试用户 (密码: 123456)
INSERT INTO `tp_users` (`phone`, `password`, `withdrawal_password`, `invite_code`, `my_invite_code`, `invited_by`, `user_type`, `agent_id`, `account_role`, `balance`, `total_recharge`, `total_commission`, `daily_commission`, `consecutive_days`, `total_withdrawn`, `status`)
VALUES ('+1716093552', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'USA2024001', '952001', 0, 2, 1, 'customer', 120.00, 0.00, 0.00, 0.00, 0, 0.00, 1);

-- 测试代理 (密码: 123456)
INSERT INTO `tp_users` (`phone`, `password`, `withdrawal_password`, `invite_code`, `my_invite_code`, `invited_by`, `user_type`, `agent_id`, `account_role`, `balance`, `total_recharge`, `total_commission`, `daily_commission`, `consecutive_days`, `total_withdrawn`, `status`)
VALUES ('+1716093553', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'SYSTEM', '952002', 0, 1, 0, 'agent', 200.00, 0.00, 0.00, 0.00, 0, 0.00, 1);

-- 修复测试用户邀请码关联
UPDATE `tp_users` SET `invited_by` = (SELECT `id` FROM `tp_users` WHERE `my_invite_code` = '952002' LIMIT 1) WHERE `my_invite_code` = '952001';

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 测试账号说明:
-- 密码统一为: 123456
-- 会员账号: +1716093552 / 123456
-- 代理账号: +1716093553 / 123456, 邀请码: 952002
-- ============================================================
