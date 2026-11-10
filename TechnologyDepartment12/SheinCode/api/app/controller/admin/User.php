<?php
namespace app\controller\admin;

use app\BaseController;
use app\model\BonusRecord;
use app\model\Commissions;
use app\model\TaskConfig;
use app\model\Tasks;
use app\model\User as UserModel;
use app\service\TaskService;
use think\exception\FileException;
use think\exception\ValidateException;
use think\facade\Db;

class User extends BaseController
{
    private const ONLINE_THRESHOLD = 300;

    public function getList()
    {
        $page = (int) $this->request->get('page', 1);
        $pageSize = (int) $this->request->get('pageSize', 20);
        $keyword = (string) $this->request->get('keyword', '');
        $accountId = (string) $this->request->get('account_id', '');
        $phone = trim((string) $this->request->get('phone', ''));
        $parentId = (string) $this->request->get('parent_id', '');
        $inviteCode = (string) $this->request->get('invite_code', '');
        $userType = $this->request->get('user_type', '');
        $accountRole = (string) $this->request->get('account_role', '');
        $status = $this->request->get('status', '');

        $query = UserModel::where('id', '>', 0);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('phone', "%{$keyword}%")
                    ->whereOr('id', $keyword)
                    ->whereOr('my_invite_code', $keyword)
                    ->whereOr('invite_code', $keyword)
                    ->whereOr('agent_id', $keyword)
                    ->whereOr('paired_user_id', $keyword)
                    ->whereOr('real_user_id', $keyword);
            });
        }

        if ($accountId !== '') {
            $query->where('id', (int) $accountId);
        }

        if ($phone !== '') {
            $query->whereLike('phone', "%{$phone}%");
        }

        if ($parentId !== '') {
            $query->where(function ($q) use ($parentId) {
                $q->where('agent_id', (int) $parentId)
                    ->whereOr('invited_by', (int) $parentId)
                    ->whereOr('managed_by', (int) $parentId)
                    ->whereOr('paired_user_id', (int) $parentId);
            });
        }

        if ($inviteCode !== '') {
            $query->where(function ($q) use ($inviteCode) {
                $q->whereLike('my_invite_code', "%{$inviteCode}%")
                    ->whereOr('invite_code', 'like', "%{$inviteCode}%");
            });
        }

        if ($userType !== '' && $userType !== null) {
            $query->where('user_type', $userType);
        }

        if ($accountRole !== '') {
            $query->where('account_role', $accountRole);
        }

        if ($status !== '' && $status !== null) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $list = $query->order('id', 'desc')->page($page, $pageSize)->select();

        $today = date('Y-m-d');
        $defaultTotalTasks = $this->getDailyTaskCount();
        $userIds = [];
        foreach ($list as $user) {
            $userIds[] = (int) $user->id;
        }
        $accountingTotals = $this->loadAccountingTotals($userIds);
        $taskService = new TaskService();
        foreach ($list as $user) {
            $parentUserId = (int) ($user->agent_id ?: $user->invited_by ?: $user->managed_by ?: 0);
            $parentUser = $parentUserId ? UserModel::find($parentUserId) : null;
            $todayConfig = $taskService->getCurrentTaskConfig((int) $user->id);
            $currentRound = $taskService->currentRound((int) $user->id, $todayConfig);
            $user->parent_id = $parentUserId ?: 0;
            $user->parent_phone = $parentUser ? $parentUser->phone : '';
            $user->today_completed = $taskService->completedCountForRound((int) $user->id, $currentRound);
            $user->today_frozen = Tasks::where('user_id', $user->id)
                ->where('round_no', $currentRound)
                ->where('status', 2)
                ->count();
            $configuredTotal = $todayConfig ? (int) ($todayConfig->total_tasks ?? 0) : 0;
            $user->today_total = $configuredTotal > 0 ? $configuredTotal : $defaultTotalTasks;
            $user->task_amount = $todayConfig ? (float) ($todayConfig->task_amount ?? 0) : 0;
            $user->task_display_date = (string) ($todayConfig->config_date ?? $today);
            $user->current_round = $currentRound;
            $user->credit_score = $this->userHasColumn('credit_score') ? (int) ($user->credit_score ?? 100) : 100;
            $totals = $accountingTotals[(int) $user->id] ?? [
                'recharge' => 0.0,
                'withdrawn' => 0.0,
                'task_commission' => 0.0,
                'team_commission' => 0.0,
            ];
            $storedRecharge = (float) ($user->total_recharge ?? 0);
            $storedWithdrawn = (float) ($user->total_withdrawn ?? 0);
            $user->total_recharge = round($storedRecharge > 0 ? $storedRecharge : $totals['recharge'], 2);
            $user->total_withdrawn = round($storedWithdrawn > 0 ? $storedWithdrawn : $totals['withdrawn'], 2);
            $user->task_commission = round((float) $totals['task_commission'], 2);
            $user->team_commission = round((float) $totals['team_commission'], 2);
            $lastActive = (string) ($user->last_active_at ?? '');
            $user->is_online = $lastActive !== '' && strtotime($lastActive) >= time() - self::ONLINE_THRESHOLD;
        }

        return json([
            'code' => 200,
            'data' => [
                'list' => $list,
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
            ],
        ]);
    }

    public function create()
    {
        $phone = trim((string) $this->request->post('phone', ''));
        $password = (string) $this->request->post('password', '');
        $withdrawalPassword = (string) $this->request->post('withdrawal_password', '');
        $inviteCode = trim((string) $this->request->post('invite_code', ''));
        $accountRole = (string) $this->request->post('account_role', 'customer');
        $agentId = (int) $this->request->post('agent_id', 0);
        $balance = (float) $this->request->post('balance', 10);
        $completedTasks = (int) $this->request->post('completed_tasks', 0);
        $status = (int) $this->request->post('status', 1);

        if ($password === '' || $withdrawalPassword === '') {
            return json(['code' => 400, 'msg' => 'Please complete account information']);
        }

        if ($phone === '') {
            return json(['code' => 400, 'msg' => 'Please enter account']);
        }

        if (UserModel::where('phone', $phone)->find()) {
            return json(['code' => 400, 'msg' => 'Account already exists']);
        }

        $inviter = $inviteCode !== '' ? UserModel::where('my_invite_code', $inviteCode)->find() : null;
        $invitedBy = $inviter ? (int) $inviter->id : 0;
        $pairedUserId = null;
        $managedBy = null;

        if ($accountRole === 'agent') {
            $agentId = 0;
        } elseif ($inviter) {
            if ((int) $inviter->user_type === 1 || $inviter->account_role === 'agent') {
                $agentId = (int) $inviter->id;
                $accountRole = $accountRole === 'managed' ? 'managed' : 'customer';
            } else {
                $accountRole = 'managed';
                $pairedUserId = (int) $inviter->id;
                $managedBy = (int) ($inviter->agent_id ?: $inviter->invited_by ?: 0);
                $agentId = $managedBy;
            }
        }

        $user = UserModel::create([
            'phone' => $phone,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'withdrawal_password' => password_hash($withdrawalPassword, PASSWORD_DEFAULT),
            'invite_code' => $inviteCode ?: 'ADMIN',
            'my_invite_code' => UserModel::generateInviteCode(),
            'invited_by' => $invitedBy,
            'user_type' => $accountRole === 'agent' ? 1 : 2,
            'agent_id' => $agentId,
            'account_role' => $accountRole,
            'paired_user_id' => $pairedUserId,
            'managed_by' => $managedBy,
            'is_fake_account' => 1,
            'real_user_id' => $accountRole === 'managed' ? $pairedUserId : null,
            'balance' => $balance,
            'credit_score' => 100,
            'status' => $status,
        ]);

        $taskResult = (new TaskService())->configDailyTasks(
            (int) ($user->agent_id ?: 1),
            (int) $user->id,
            max(1, (float) $user->balance),
            $this->getCreateDailyTaskCount(),
            $completedTasks
        );
        if (($taskResult['code'] ?? 0) !== 200) {
            return json(['code' => 400, 'msg' => $taskResult['msg'] ?? 'Failed to initialize tasks']);
        }

        return json(['code' => 200, 'msg' => 'Created successfully', 'data' => ['id' => $user->id]]);
    }

    public function updatePassword()
    {
        $userId = (int) $this->request->post('user_id');
        $password = (string) $this->request->post('password', '');
        $withdrawalPassword = (string) $this->request->post('withdrawal_password', '');
        $postData = $this->request->post();
        $hasRealName = array_key_exists('real_name', $postData);
        $realName = trim((string) ($postData['real_name'] ?? ''));

        $user = UserModel::find($userId);
        if (!$user) {
            return json(['code' => 400, 'msg' => 'User not found']);
        }

        if ($password === '' && $withdrawalPassword === '' && !$hasRealName) {
            return json(['code' => 400, 'msg' => 'Please enter information to update']);
        }

        if ($password !== '') {
            if (strlen($password) < 6 || strlen($password) > 20) {
                return json(['code' => 400, 'msg' => 'Login password must be 6-20 characters']);
            }
            $user->password = password_hash($password, PASSWORD_DEFAULT);
        }

        if ($withdrawalPassword !== '') {
            if (strlen($withdrawalPassword) < 6 || strlen($withdrawalPassword) > 20) {
                return json(['code' => 400, 'msg' => 'Withdrawal password must be 6-20 characters']);
            }
            $user->withdrawal_password = password_hash($withdrawalPassword, PASSWORD_DEFAULT);
        }

        if ($hasRealName) {
            $user->real_name = $realName;
        }

        $user->save();
        return json(['code' => 200, 'msg' => 'User info updated']);
    }

    public function updateInfo()
    {
        $userId = (int) $this->request->post('user_id');
        $phone = trim((string) $this->request->post('phone', ''));
        $nickname = trim((string) $this->request->post('nickname', ''));
        $realName = trim((string) $this->request->post('real_name', ''));
        $status = (int) $this->request->post('status', 1);
        $avatar = trim((string) $this->request->post('avatar', ''));
        $password = (string) $this->request->post('password', '');
        $withdrawalPassword = (string) $this->request->post('withdrawal_password', '');
        $postData = $this->request->post();
        $hasInviteCode = array_key_exists('my_invite_code', $postData);
        $myInviteCode = trim((string) ($postData['my_invite_code'] ?? ''));

        $user = UserModel::find($userId);
        if (!$user) {
            return json(['code' => 400, 'msg' => 'User not found']);
        }

        if ($phone === '') {
            return json(['code' => 400, 'msg' => 'Please enter account']);
        }

        $samePhoneUser = UserModel::where('phone', $phone)->where('id', '<>', $userId)->find();
        if ($samePhoneUser) {
            return json(['code' => 400, 'msg' => 'Phone number already exists']);
        }

        if ($hasInviteCode) {
            if (!UserModel::validateInviteCode($myInviteCode)) {
                return json(['code' => 400, 'msg' => 'Invite code must be 4-6 digits']);
            }
            $sameInviteCodeUser = UserModel::where('my_invite_code', $myInviteCode)->where('id', '<>', $userId)->find();
            if ($sameInviteCodeUser) {
                return json(['code' => 400, 'msg' => 'Invite code already exists']);
            }
        }

        $user->phone = $phone;
        $user->nickname = $nickname;
        $user->real_name = $realName;
        $user->status = $status === 1 ? 1 : 0;
        $user->avatar = $avatar;
        if ($hasInviteCode) {
            $user->my_invite_code = $myInviteCode;
        }

        if ($password !== '') {
            if (strlen($password) < 6 || strlen($password) > 20) {
                return json(['code' => 400, 'msg' => 'Login password must be 6-20 characters']);
            }
            $user->password = password_hash($password, PASSWORD_DEFAULT);
        }

        if ($withdrawalPassword !== '') {
            if (strlen($withdrawalPassword) < 6 || strlen($withdrawalPassword) > 20) {
                return json(['code' => 400, 'msg' => 'Withdrawal password must be 6-20 characters']);
            }
            $user->withdrawal_password = password_hash($withdrawalPassword, PASSWORD_DEFAULT);
        }

        $user->save();
        return json(['code' => 200, 'msg' => 'User info updated']);
    }

    public function upload()
    {
        $file = $this->request->file('file');
        if (!$file) {
            return json(['code' => 400, 'msg' => '请选择图片']);
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
            return json(['code' => 400, 'msg' => '只能上传图片文件']);
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($imageInfo['mime'] ?? '', $allowedMimeTypes, true)) {
            return json(['code' => 400, 'msg' => '只支持 JPG、PNG、GIF、WebP 图片']);
        }

        $uploadDir = public_path() . 'uploads' . DIRECTORY_SEPARATOR . 'users';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = strtolower($file->extension() ?: 'jpg');
        $filename = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        try {
            $file->move($uploadDir, $filename);
        } catch (FileException $e) {
            return json(['code' => 500, 'msg' => '图片保存失败：' . $e->getMessage()]);
        }

        return json(['code' => 200, 'msg' => '上传成功', 'data' => ['url' => '/uploads/users/' . $filename]]);
    }

    public function updateLevel()
    {
        $userId = (int) $this->request->post('user_id');
        $level = trim((string) $this->request->post('member_level', '1'));

        if (!$userId || $level === '' || !is_numeric($level)) {
            return json(['code' => 400, 'msg' => 'Invalid parameters']);
        }

        $user = UserModel::find($userId);
        if (!$user) {
            return json(['code' => 400, 'msg' => 'User not found']);
        }

        $levelConfig = Db::name('member_levels')->where('level', (int) $level)->find();
        if (!$levelConfig) {
            return json(['code' => 400, 'msg' => 'Level not found']);
        }

        $dailyOrders = max(0, min((int) ($levelConfig['daily_orders'] ?? 0), 999));

        Db::startTrans();
        try {
            Db::name('users')->where('id', $userId)->update([
                'member_level' => (string) (int) $level,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->syncUserLevelTaskTotal($user, $dailyOrders);

            Db::commit();
            return json(['code' => 200, 'msg' => 'Level updated', 'data' => ['total_tasks' => $dailyOrders]]);
        } catch (\Throwable $e) {
            Db::rollback();
            return json(['code' => 500, 'msg' => 'Failed to update level']);
        }
    }

    public function updateCreditScore()
    {
        $userId = (int) $this->request->post('user_id');
        $score = $this->request->post('credit_score', null);

        if ($score === null || !is_numeric($score)) {
            return json(['code' => 400, 'msg' => 'Please enter a valid credit score']);
        }

        $score = (int) $score;
        if ($score < 0 || $score > 100) {
            return json(['code' => 400, 'msg' => 'Credit score must be between 0 and 100']);
        }

        if (!UserModel::where('id', $userId)->find()) {
            return json(['code' => 400, 'msg' => 'User not found']);
        }

        UserModel::where('id', $userId)->update(['credit_score' => $score]);

        return json(['code' => 200, 'msg' => 'Credit score updated']);
    }

    public function getMemberLevels()
    {
        $page = max(1, (int) $this->request->get('page', 1));
        $pageSize = max(1, (int) $this->request->get('pageSize', 20));
        $keyword = trim((string) $this->request->get('keyword', ''));

        $query = Db::name('member_levels');
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('level_name', "%{$keyword}%")
                    ->whereOr('level', $keyword);
            });
        }

        $total = $query->count();
        $list = $query->order('level', 'desc')->page($page, $pageSize)->select();

        return json([
            'code' => 200,
            'data' => [
                'list' => $list,
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
            ],
        ]);
    }

    public function saveMemberLevel()
    {
        $id = (int) $this->request->post('id', 0);
        $level = (int) $this->request->post('level', 0);
        $levelName = trim((string) $this->request->post('level_name', ''));

        if ($level < 0 || $levelName === '') {
            return json(['code' => 400, 'msg' => 'Please complete level information']);
        }

        $sameLevel = Db::name('member_levels')->where('level', $level);
        if ($id > 0) {
            $sameLevel->where('id', '<>', $id);
        }
        if ($sameLevel->find()) {
            return json(['code' => 400, 'msg' => 'Level already exists']);
        }

        $payload = $this->request->post();
        $existingLevel = $id > 0 ? Db::name('member_levels')->where('id', $id)->find() : null;

        $data = [
            'level' => $level,
            'level_name' => $levelName,
            'daily_orders' => (int) $this->request->post('daily_orders', 0),
            'recharge_amount' => $this->optionalDecimalField($payload, $existingLevel, 'recharge_amount', 2),
            'team_count' => $this->optionalIntField($payload, $existingLevel, 'team_count'),
            'valid_count' => $this->optionalIntField($payload, $existingLevel, 'valid_count'),
            'withdraw_min_amount' => $this->optionalDecimalField($payload, $existingLevel, 'withdraw_min_amount', 2),
            'withdraw_max_amount' => $this->optionalDecimalField($payload, $existingLevel, 'withdraw_max_amount', 2),
            'commission_rate' => $this->optionalDecimalField($payload, $existingLevel, 'commission_rate', 4),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            Db::name('member_levels')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            Db::name('member_levels')->insert($data);
        }

        return json(['code' => 200, 'msg' => 'Saved successfully']);
    }

    private function optionalIntField(array $payload, ?array $existing, string $field): int
    {
        if (array_key_exists($field, $payload)) {
            return (int) $payload[$field];
        }

        return (int) ($existing[$field] ?? 0);
    }

    private function optionalDecimalField(array $payload, ?array $existing, string $field, int $precision): float
    {
        if (array_key_exists($field, $payload)) {
            return round((float) $payload[$field], $precision);
        }

        return round((float) ($existing[$field] ?? 0), $precision);
    }

    public function deleteMemberLevel()
    {
        $id = (int) $this->request->post('id', 0);
        if (!$id) {
            return json(['code' => 400, 'msg' => 'Invalid parameters']);
        }

        $level = Db::name('member_levels')->where('id', $id)->find();
        if (!$level) {
            return json(['code' => 400, 'msg' => 'Level not found']);
        }

        $used = UserModel::where('member_level', (string) $level['level'])->count();
        if ($used > 0) {
            return json(['code' => 400, 'msg' => 'This level is currently used by members']);
        }

        Db::name('member_levels')->where('id', $id)->delete();
        return json(['code' => 200, 'msg' => 'Deleted successfully']);
    }

    private function userHasColumn(string $name): bool
    {
        try {
            $columns = Db::query("SHOW COLUMNS FROM tp_users LIKE '{$name}'");
            return (bool) $columns;
        } catch (\Throwable $e) {
        }

        return false;
    }

    private function loadAccountingTotals(array $userIds): array
    {
        $totals = [];
        foreach (array_values(array_unique(array_filter($userIds))) as $userId) {
            $totals[(int) $userId] = [
                'recharge' => 0.0,
                'withdrawn' => 0.0,
                'task_commission' => 0.0,
                'team_commission' => 0.0,
            ];
        }

        if (!$totals) {
            return [];
        }

        $ids = array_keys($totals);

        try {
            $rows = Db::table('tp_balance_logs')
                ->field('user_id, SUM(change_amount) AS total_amount')
                ->whereIn('user_id', $ids)
                ->where('type', 'deposit')
                ->group('user_id')
                ->select();
            foreach ($rows as $row) {
                $userId = (int) ($row['user_id'] ?? 0);
                if (isset($totals[$userId])) {
                    $totals[$userId]['recharge'] = round((float) ($row['total_amount'] ?? 0), 2);
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            $rows = Db::table('tp_withdrawals')
                ->field('user_id, SUM(amount) AS total_amount')
                ->whereIn('user_id', $ids)
                ->where('status', 'approved')
                ->group('user_id')
                ->select();
            foreach ($rows as $row) {
                $userId = (int) ($row['user_id'] ?? 0);
                if (isset($totals[$userId])) {
                    $totals[$userId]['withdrawn'] += round((float) ($row['total_amount'] ?? 0), 2);
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            $rows = Db::table('tp_balance_logs')
                ->field('user_id, SUM(ABS(change_amount)) AS total_amount')
                ->whereIn('user_id', $ids)
                ->where('type', 'withdraw')
                ->group('user_id')
                ->select();
            foreach ($rows as $row) {
                $userId = (int) ($row['user_id'] ?? 0);
                if (isset($totals[$userId])) {
                    $totals[$userId]['withdrawn'] += round((float) ($row['total_amount'] ?? 0), 2);
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            $rows = Db::table('tp_balance_logs')
                ->field('user_id, type, SUM(change_amount) AS total_amount')
                ->whereIn('user_id', $ids)
                ->whereIn('type', ['task_commission', 'linked_profit_share'])
                ->group('user_id, type')
                ->select();
            foreach ($rows as $row) {
                $userId = (int) ($row['user_id'] ?? 0);
                $type = (string) ($row['type'] ?? '');
                if (!isset($totals[$userId])) {
                    continue;
                }

                if ($type === 'task_commission') {
                    $totals[$userId]['task_commission'] = round((float) ($row['total_amount'] ?? 0), 2);
                } elseif ($type === 'linked_profit_share') {
                    $totals[$userId]['team_commission'] = round((float) ($row['total_amount'] ?? 0), 2);
                }
            }
        } catch (\Throwable $e) {
        }

        return $totals;
    }

    private function getDailyTaskCount(): int
    {
        try {
            $row = Db::name('system_settings')->where('setting_key', 'daily_task_count')->find();
            $value = $row['setting_value'] ?? 0;
        } catch (\Throwable $e) {
            $value = 0;
        }

        return max(0, min((int) $value, 999));
    }

    private function getCreateDailyTaskCount(): int
    {
        $count = $this->getDailyTaskCount();
        return $count > 0 ? $count : 40;
    }

    private function syncUserLevelTaskTotal(UserModel $user, int $totalTasks): void
    {
        $totalTasks = max(0, min($totalTasks, 999));
        $now = date('Y-m-d H:i:s');
        $config = Db::table('tp_task_configs')
            ->where('user_id', (int) $user->id)
            ->where('is_active', 1)
            ->order('id', 'desc')
            ->find();

        if ($config) {
            Db::table('tp_task_configs')
                ->where('id', (int) $config['id'])
                ->update(['total_tasks' => $totalTasks]);

            $roundNo = max(1, (int) ($config['current_round'] ?? 1));
        } else {
            $roundNo = 1;
            Db::table('tp_task_configs')->insert([
                'agent_id' => (int) ($user->agent_id ?: 1),
                'user_id' => (int) $user->id,
                'config_date' => date('Y-m-d'),
                'task_amount' => max(1, (float) ($user->balance ?? 0)),
                'total_tasks' => $totalTasks,
                'current_round' => $roundNo,
                'is_active' => 1,
                'manual_completed_count' => 0,
                'created_at' => $now,
            ]);
        }

        Db::table('tp_tasks')
            ->where('user_id', (int) $user->id)
            ->where('round_no', $roundNo)
            ->where('task_index', '>', $totalTasks)
            ->where('status', 0)
            ->where('is_package', 0)
            ->whereNull('matched_at')
            ->update(['status' => -1]);
    }

    public function toggleStatus()
    {
        $userId = (int) $this->request->post('user_id');
        $status = (int) $this->request->post('status');

        $user = UserModel::find($userId);
        if (!$user) {
            return json(['code' => 400, 'msg' => '用户不存在']);
        }

        $user->status = $status;
        $user->save();

        return json(['code' => 200, 'msg' => '操作成功']);
    }

    public function getCommissionRecords()
    {
        $userId = (int) $this->request->get('user_id');
        $page = (int) $this->request->get('page', 1);
        $pageSize = (int) $this->request->get('pageSize', 20);

        $query = Commissions::where(function ($q) use ($userId) {
            $q->where('source_user_id', $userId)->whereOr('target_user_id', $userId);
        });
        $total = $query->count();
        $list = $query->order('id', 'desc')->page($page, $pageSize)->select();

        return json(['code' => 200, 'data' => ['list' => $list, 'total' => $total]]);
    }

    public function getBonusRecords()
    {
        $userId = (int) $this->request->get('user_id');
        $page = (int) $this->request->get('page', 1);
        $pageSize = (int) $this->request->get('pageSize', 20);

        $query = BonusRecord::where('user_id', $userId);
        $total = $query->count();
        $list = $query->order('id', 'desc')->page($page, $pageSize)->select();

        return json(['code' => 200, 'data' => ['list' => $list, 'total' => $total]]);
    }
}
