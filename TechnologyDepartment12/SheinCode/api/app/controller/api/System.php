<?php

namespace app\controller\api;

use app\BaseController;
use think\facade\Db;

class System extends BaseController
{
    public function settings()
    {
        $rows = Db::name('system_settings')->select();
        $settings = $this->defaultSettings();
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $settings['default_commission_rate'] = '1';

        return json([
            'code' => 200,
            'data' => [
                'site_name' => $settings['site_name'],
                'customer_service_url' => $settings['customer_service_url'],
                'customer_service_hours' => $settings['customer_service_hours'],
                'about_us' => $settings['about_us'],
                'register_agreement' => $settings['register_agreement'],
                'terms_agreements' => $settings['terms_agreements'],
                'task_min_balance' => (float) $settings['task_min_balance'],
                'normal_order_price_down_diff' => (float) $settings['normal_order_price_down_diff'],
                'task_balance_min' => (int) ($settings['task_balance_min'] ?? 40),
                'daily_task_count' => (int) $settings['daily_task_count'],
                'default_commission_rate' => (float) $settings['default_commission_rate'],
                'withdraw_min_amount' => (float) $settings['withdraw_min_amount'],
                'withdraw_max_amount' => (float) $settings['withdraw_max_amount'],
                'invite_reward_rate' => (float) $settings['invite_reward_rate'],
                'popup_enabled' => (int) $settings['popup_enabled'],
                'popup_title' => $settings['popup_title'],
                'popup_content' => $settings['popup_content'],
                'maintenance_mode' => (int) $settings['maintenance_mode'],
            ],
        ]);
    }

    public function partners()
    {
        $list = Db::name('partner_sites')
            ->field('id,name,url,logo,sort')
            ->where('status', 1)
            ->order('sort', 'desc')
            ->order('id', 'desc')
            ->select();

        return json(['code' => 200, 'data' => ['list' => $list]]);
    }

    private function defaultSettings(): array
    {
        return [
            'site_name' => 'TARGET',
            'customer_service_url' => '',
            'customer_service_hours' => '9:00-21:00',
            'about_us' => '',
            'register_agreement' => '',
            'terms_agreements' => '',
            'task_min_balance' => '50',
            'normal_order_price_down_diff' => '0',
            'task_balance_min' => '40',
            'daily_task_count' => '0',
            'default_commission_rate' => '1',
            'withdraw_min_amount' => '100',
            'withdraw_max_amount' => '100000',
            'invite_reward_rate' => '25',
            'popup_enabled' => '0',
            'popup_title' => '',
            'popup_content' => '',
            'maintenance_mode' => '0',
        ];
    }
}
