<?php
namespace app\controller\admin;

use app\BaseController;
use think\exception\FileException;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;

class SystemConfig extends BaseController
{
    public function getPartners()
    {
        $page = max(1, (int) $this->request->get('page', 1));
        $pageSize = max(1, (int) $this->request->get('pageSize', 20));
        $keyword = trim((string) $this->request->get('keyword', ''));

        $query = Db::name('partner_sites');
        if ($keyword !== '') {
            $query->whereLike('name', "%{$keyword}%")->whereOr('url', 'like', "%{$keyword}%");
        }

        $total = $query->count();
        $list = $query->order('sort', 'desc')->order('id', 'desc')->page($page, $pageSize)->select();

        return json(['code' => 200, 'data' => ['list' => $list, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize]]);
    }

    public function savePartner()
    {
        $id = (int) $this->request->post('id', 0);
        $name = trim((string) $this->request->post('name', ''));
        $url = trim((string) $this->request->post('url', ''));
        if ($name === '' || $url === '') {
            return json(['code' => 400, 'msg' => '璇峰～鍐欑綉绔欏悕绉板拰閾炬帴']);
        }

        $data = [
            'name' => $name,
            'url' => $url,
            'logo' => trim((string) $this->request->post('logo', '')),
            'sort' => (int) $this->request->post('sort', 0),
            'status' => (int) $this->request->post('status', 1),
            'remark' => trim((string) $this->request->post('remark', '')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            Db::name('partner_sites')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            Db::name('partner_sites')->insert($data);
        }

        return json(['code' => 200, 'msg' => '淇濆瓨鎴愬姛']);
    }

    public function deletePartner()
    {
        $id = (int) $this->request->post('id', 0);
        if (!$id) {
            return json(['code' => 400, 'msg' => 'Invalid parameters']);
        }

        Db::name('partner_sites')->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => '鍒犻櫎鎴愬姛']);
    }

    public function uploadPartnerLogo()
    {
        $file = $this->request->file('file');
        if (!$file) {
            return json(['code' => 400, 'msg' => 'Please choose an image']);
        }

        try {
            validate([
                'file' => 'fileSize:5242880|fileExt:jpg,jpeg,png,gif,webp',
            ])->check(['file' => $file]);
        } catch (ValidateException $e) {
            return json(['code' => 400, 'msg' => $e->getMessage()]);
        }

        $imageInfo = @getimagesize($file->getPathname());
        if ($imageInfo === false) {
            return json(['code' => 400, 'msg' => 'Only image files are allowed']);
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($imageInfo['mime'] ?? '', $allowedMimeTypes, true)) {
            return json(['code' => 400, 'msg' => 'Only JPG, PNG, GIF, and WebP images are supported']);
        }

        $uploadDir = public_path() . 'uploads' . DIRECTORY_SEPARATOR . 'partners';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = strtolower($file->extension() ?: 'jpg');
        $filename = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        try {
            $file->move($uploadDir, $filename);
        } catch (FileException $e) {
            return json(['code' => 500, 'msg' => 'Failed to save image: ' . $e->getMessage()]);
        }

        return json(['code' => 200, 'msg' => 'Upload successful', 'data' => ['url' => '/uploads/partners/' . $filename]]);
    }

    public function getSettings()
    {
        $rows = Db::name('system_settings')->select();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $settings = array_merge($this->defaultSettings(), $settings);
        $settings['default_commission_rate'] = '1';
        return json(['code' => 200, 'data' => $settings]);
    }

    public function saveSettings()
    {
        $allowed = array_keys($this->defaultSettings());
        $payload = $this->request->post();
        if (array_key_exists('default_commission_rate', $payload)) {
            $payload['default_commission_rate'] = '1';
        }
        $now = date('Y-m-d H:i:s');
        $oldDailyTaskCount = $this->getSettingValue('daily_task_count', $this->defaultSettings()['daily_task_count']);

        if (array_key_exists('default_commission_rate', $payload)) {
            try {
                $this->syncTodayDefaultCommissionRate((float) $payload['default_commission_rate']);
            } catch (\Throwable $e) {
                Log::error('Failed to sync today default commission rate: ' . $e->getMessage());
            }
        }

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = is_array($payload[$key]) ? json_encode($payload[$key], JSON_UNESCAPED_UNICODE) : (string) $payload[$key];
            $exists = Db::name('system_settings')->where('setting_key', $key)->find();
            if ($exists) {
                Db::name('system_settings')->where('setting_key', $key)->update(['setting_value' => $value, 'updated_at' => $now]);
            } else {
                Db::name('system_settings')->insert(['setting_key' => $key, 'setting_value' => $value, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        if (array_key_exists('daily_task_count', $payload)) {
            try {
                $this->syncTodayTaskTotals((int) $oldDailyTaskCount, (int) $payload['daily_task_count']);
            } catch (\Throwable $e) {
                Log::error('Failed to sync today task totals: ' . $e->getMessage());
            }
        }

        return json(['code' => 200, 'msg' => '淇濆瓨鎴愬姛']);
    }

    private function syncTodayDefaultCommissionRate(float $rate): void
    {
        $rate = 1.0;
        $configs = Db::table('tp_task_configs')->where('is_active', 1)->select();
        foreach ($configs as $config) {
            $userId = (int) ($config['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            Db::table('tp_tasks')
                ->where('user_id', $userId)
                ->where('round_no', max(1, (int) ($config['current_round'] ?? 1)))
                ->where('status', 0)
                ->where('is_package', 0)
                ->update(['commission_rate' => $rate]);
        }
    }

    private function syncTodayTaskTotals(int $oldTotal, int $newTotal): void
    {
        $newTotal = max(0, min($newTotal, 999));

        $configs = Db::table('tp_task_configs')->where('is_active', 1)->select();
        Db::table('tp_task_configs')->where('is_active', 1)->update(['total_tasks' => $newTotal]);

        foreach ($configs as $config) {
            $userId = (int) ($config['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            Db::table('tp_tasks')
                ->where('user_id', $userId)
                ->where('round_no', max(1, (int) ($config['current_round'] ?? 1)))
                ->where('task_index', '>', $newTotal)
                ->where('status', 0)
                ->whereNull('matched_at')
                ->update(['status' => -1]);
        }
    }

    private function getSettingValue(string $key, string $default): string
    {
        try {
            $row = Db::name('system_settings')->where('setting_key', $key)->find();
            return (string) ($row['setting_value'] ?? $default);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function defaultSettings(): array
    {
        return [
            'site_name' => 'TARGET',
            'task_min_balance' => '50',
            'normal_order_price_down_diff' => '0',
            'team_reward_rate' => '0.25',
            'task_min_total_amount' => '50',
            'combo_default_diff' => '10',
            'task_balance_min' => '40',
            'task_balance_max' => '70',
            'reset_order_next_day' => '0',
            'negative_reward_enabled' => '0',
            'combo_counts_as_one' => '1',
            'task_start_time' => '06:00 AM',
            'task_end_time' => '06:00 AM',
            'phone_rule' => 'US+1',
            'phone_rule_scope' => 'all',
            'daily_task_count' => '0',
            'default_commission_rate' => '1',
            'withdraw_min_amount' => '100',
            'withdraw_max_amount' => '100000',
            'invite_reward_rate' => '25',
            'maintenance_mode' => '0',
            'customer_service_url' => '',
            'default_usdt_address' => '',
            'customer_service_hours' => '9:00-21:00',
            'timezone' => 'UTC+0',
            'register_bonus' => '10',
            'about_us' => '',
            'register_agreement' => '',
            'terms_agreements' => '',
            'popup_enabled' => '0',
            'popup_title' => '',
            'popup_content' => '',
        ];
    }
}
