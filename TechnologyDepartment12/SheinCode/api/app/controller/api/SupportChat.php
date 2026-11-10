<?php
namespace app\controller\api;

use app\BaseController;
use app\service\SupportChatService;
use app\utils\S3Uploader;
use think\exception\ValidateException;

class SupportChat extends BaseController
{
    public function conversation()
    {
        $conversation = SupportChatService::getOrCreateConversation((int) $this->request->userId);
        return json(['code' => 200, 'data' => $conversation]);
    }

    public function messages()
    {
        $conversation = SupportChatService::getOrCreateConversation((int) $this->request->userId);
        $page = max(1, (int) $this->request->get('page', 1));
        $pageSize = max(1, min(100, (int) $this->request->get('pageSize', 30)));
        SupportChatService::markRead((int) $conversation['id'], 'user');

        return json(['code' => 200, 'data' => SupportChatService::listMessages((int) $conversation['id'], $page, $pageSize)]);
    }

    public function upload()
    {
        return $this->uploadImage('support');
    }

    private function uploadImage(string $folder)
    {
        $file = $this->request->file('file');
        if (!$file) {
            return json(['code' => 400, 'msg' => 'Please select an image']);
        }

        try {
            validate(['file' => 'fileSize:5242880|fileExt:jpg,jpeg,png,gif,webp'])->check(['file' => $file]);
        } catch (ValidateException $e) {
            return json(['code' => 400, 'msg' => $e->getMessage()]);
        }

        $info = @getimagesize($file->getPathname());
        if (!$info || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return json(['code' => 400, 'msg' => 'Only image files are supported']);
        }

        $extension = strtolower($file->extension() ?: 'jpg');
        $contentType = $info['mime'] ?? 'image/jpeg';
        $key = S3Uploader::generateKey($folder, $extension);

        $url = S3Uploader::upload($file->getPathname(), $key, $contentType);
        if (!$url) {
            $error = S3Uploader::getLastError() ?: 'Image upload failed';
            return json(['code' => 500, 'msg' => $error]);
        }

        return json(['code' => 200, 'data' => ['url' => $url]]);
    }
}
