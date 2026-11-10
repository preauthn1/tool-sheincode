<?php
namespace app\controller\admin;

use app\BaseController;
use app\model\Good as GoodModel;
use think\exception\FileException;
use think\exception\ValidateException;

class Good extends BaseController
{
    private const MIN_PRICE = 45;
    private const MAX_PRICE = 50000;

    public function getList()
    {
        $page = (int) $this->request->get('page', 1);
        $pageSize = (int) $this->request->get('pageSize', 20);
        $keyword = (string) $this->request->get('keyword', '');
        $status = $this->request->get('status', '');

        $query = GoodModel::where('id', '>', 0);
        if ($keyword !== '') {
            $query->whereLike('name', "%{$keyword}%");
        }
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->order('sort', 'desc')->order('id', 'desc')->page($page, $pageSize)->select();

        return json(['code' => 200, 'data' => ['list' => $list, 'total' => $total]]);
    }

    public function save()
    {
        $id = (int) $this->request->post('id', 0);
        $name = trim((string) $this->request->post('name', ''));
        $image = trim((string) $this->request->post('image', ''));
        $price = (float) $this->request->post('price', 0);
        $sort = (int) $this->request->post('sort', 0);
        $status = (int) $this->request->post('status', 1);

        if ($name === '' || $price <= 0) {
            return json(['code' => 400, 'msg' => '请填写商品名称和价格']);
        }

        if ($price < self::MIN_PRICE || $price > self::MAX_PRICE) {
            return json(['code' => 400, 'msg' => 'Price must be between 45 and 50000 USD']);
        }

        $good = $id ? GoodModel::find($id) : new GoodModel();
        if (!$good) {
            return json(['code' => 400, 'msg' => '商品不存在']);
        }

        $good->name = $name;
        $good->image = $image;
        $good->price = $price;
        $good->sort = $sort;
        $good->status = $status;
        $good->save();

        return json(['code' => 200, 'msg' => '保存成功', 'data' => ['id' => $good->id]]);
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

        $uploadDir = public_path() . 'uploads' . DIRECTORY_SEPARATOR . 'goods';
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

        $url = '/uploads/goods/' . $filename;
        return json(['code' => 200, 'msg' => '上传成功', 'data' => ['url' => $url]]);
    }

    public function toggleStatus()
    {
        $id = (int) $this->request->post('id');
        $status = (int) $this->request->post('status');

        $good = GoodModel::find($id);
        if (!$good) {
            return json(['code' => 400, 'msg' => '商品不存在']);
        }

        $good->status = $status;
        $good->save();

        return json(['code' => 200, 'msg' => '操作成功']);
    }

    public function delete()
    {
        $id = (int) $this->request->post('id');
        $good = GoodModel::find($id);
        if (!$good) {
            return json(['code' => 400, 'msg' => '商品不存在']);
        }

        $good->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }
}
