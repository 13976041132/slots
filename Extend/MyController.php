<?php
/**
 * 控制器扩展
 */

namespace FF\Extend;

use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Core\FFController;

class MyController extends FFController
{
    /**
     * uri过滤检查
     */
    protected function isInFilter($filter)
    {
        $route = FF::getRouter()->getRoute();
        if (in_array($route, $filter)) return true;

        $path = FF::getRouter()->getPath();
        $controller = FF::getRouter()->getController();
        if (in_array("{$path}/{$controller}/*", $filter)) return true;
        if (in_array("{$path}/*", $filter)) return true;

        return false;
    }

    /**
     * 根据model名字返回实例
     * @return MyModel
     */
    protected function model($modelName)
    {
        if (!method_exists('FF\\Factory\\Model', $modelName)) {
            FF::throwException(Code::FAILED, "Model {$modelName} not exist!");
        }

        return call_user_func(array('FF\\Factory\\Model', $modelName));
    }

    /**
     * CURD-Create
     */
    protected function _create($modelName, &$data)
    {
        $this->checkData($modelName, $data, null, 'create');
        $model = $this->model($modelName);
        $result = $model->insert($data);

        if (!$result) {
            FF::throwException(Code::FAILED, '保存失败');
        }

        return array(
            'id' => $result,
            'message' => '已保存',
            'reload' => true
        );
    }

    /**
     * CURD-Update
     */
    protected function _update($modelName, $id, &$data)
    {
        $model = $this->model($modelName);
        $curData = $model->getOneById($id);

        if (!$curData) {
            FF::throwException(Code::FAILED, '记录不存在');
        }

        $this->checkData($modelName, $data, $curData, 'update');

        foreach ($data as $key => $val) {
            if ($val === $curData[$key]) unset($data[$key]);
        }

        if ($data) {
            $result = $model->updateById($id, $data);
            if (!$result) {
                FF::throwException(Code::FAILED, '保存失败');
            }
        }

        return array(
            'message' => '已保存',
            'reload' => true
        );
    }

    /**
     * CURD-Read
     */
    protected function _read($modelName, $id, $fields = null)
    {
        $model = $this->model($modelName);

        return $model->getOneById($id, $fields);
    }

    /**
     * CURD-Delete
     */
    protected function _delete($modelName, $id, &$row = null)
    {
        if (!$row = $this->_read($modelName, $id)) {
            FF::throwException(Code::PARAMS_INVALID, '记录不存在');
        }

        $this->checkData($modelName, $data, $row, 'delete');
        $this->model($modelName)->deleteById($id);

        return array(
            'message' => '已删除',
            'reload' => true
        );
    }

    /**
     * CURD-检查输入数据
     */
    protected function checkData($modelName, &$data, $curData, $action)
    {
        //to override
    }
}