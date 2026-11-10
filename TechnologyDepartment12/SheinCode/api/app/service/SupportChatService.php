<?php
namespace app\service;

use think\facade\Db;

class SupportChatService
{
    public static function getOrCreateConversation(int $userId): array
    {
        $conversation = Db::table('tp_support_conversations')
            ->where('user_id', $userId)
            ->order('updated_at', 'desc')
            ->order('id', 'desc')
            ->find();
        if ($conversation) {
            if (($conversation['status'] ?? '') !== 'open') {
                Db::table('tp_support_conversations')->where('id', (int) $conversation['id'])->update([
                    'status' => 'open',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $conversation = Db::table('tp_support_conversations')->where('id', (int) $conversation['id'])->find();
            }
            return $conversation;
        }

        $user = Db::table('tp_users')->where('id', $userId)->find();
        $now = date('Y-m-d H:i:s');
        $id = Db::table('tp_support_conversations')->insertGetId([
            'user_id' => $userId,
            'user_phone' => (string) ($user['phone'] ?? ''),
            'status' => 'open',
            'last_message' => '',
            'last_message_type' => 'text',
            'unread_user' => 0,
            'unread_admin' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Db::table('tp_support_conversations')->where('id', $id)->find();
    }

    public static function listConversations(int $page, int $pageSize, string $keyword = '', string $status = ''): array
    {
        $query = Db::table('tp_support_conversations');
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('user_phone', "%{$keyword}%")
                    ->whereOr('user_id', (int) $keyword);
            });
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $list = $query->order('updated_at', 'desc')->page($page, $pageSize)->select();

        return ['list' => $list, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize];
    }

    public static function listMessages(int $conversationId, int $page, int $pageSize): array
    {
        $query = Db::table('tp_support_messages')->where('conversation_id', $conversationId);
        $total = (clone $query)->count();
        $list = $query->order('id', 'desc')->page($page, $pageSize)->select();

        return [
            'list' => array_reverse(is_array($list) ? $list : $list->toArray()),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }

    public static function saveMessage(int $conversationId, string $senderType, int $senderId, string $messageType, string $content): array
    {
        $messageType = in_array($messageType, ['text', 'image', 'emoji'], true) ? $messageType : 'text';
        $content = trim($content);
        if ($content === '') {
            throw new \InvalidArgumentException('Message content is empty');
        }

        $now = date('Y-m-d H:i:s');
        $messageId = Db::table('tp_support_messages')->insertGetId([
            'conversation_id' => $conversationId,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'message_type' => $messageType,
            'content' => $content,
            'created_at' => $now,
        ]);

        $field = $senderType === 'admin' ? 'unread_user' : 'unread_admin';
        Db::table('tp_support_conversations')->where('id', $conversationId)->inc($field)->update([
            'last_message' => mb_substr($messageType === 'text' ? $content : '[' . $messageType . ']', 0, 200),
            'last_message_type' => $messageType,
            'updated_at' => $now,
        ]);

        return Db::table('tp_support_messages')->where('id', $messageId)->find();
    }

    public static function markRead(int $conversationId, string $reader): void
    {
        $field = $reader === 'admin' ? 'unread_admin' : 'unread_user';
        Db::table('tp_support_conversations')->where('id', $conversationId)->update([$field => 0]);
    }

    public static function closeConversation(int $conversationId): void
    {
        Db::table('tp_support_conversations')->where('id', $conversationId)->update([
            'status' => 'closed',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
