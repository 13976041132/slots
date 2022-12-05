<?php
/**
 * 管理员账号相关
 */

namespace FF\App\Admin\Controller;

use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;
use FF\Library\Utils\Pager;

class AccountController extends BaseController
{
    /**
     * @ignore permission
     */
    public function login()
    {
        if ($this->session) {
            redirect(BASE_URL);
            exit(0);
        }

        $this->display('login.html');
    }

    /**
     * 请求登录
     * @ignore permission
     */
    public function doLogin()
    {
        if ($this->session) {
            return array('redirect' => '/');
        }

        $account = $this->getParam('account');
        $password = $this->getParam('password');

        Bll::admin()->login($account, $password);

        return array('redirect' => '/');
    }

    /**
     * @ignore permission
     */
    public function logout()
    {
        Bll::session()->clean();

        return array();
    }

    /**
     * 查看账户列表
     */
    public function index()
    {
        $page = $this->getParam('page', false, 1);
        $limit = $this->getParam('limit', false, 10);
        $realname = $this->getParam('realname', false);

        $data = $where = array();
        if ($realname) $where['realname'] = $realname;
        if (!$where) $where['account'] = array('!=', 'root');
        $pageData = Model::admin()->getPageList($page, $limit, $where, null, array('id' => 'desc'));
        $data['list'] = $pageData['list'];
        $data['pager'] = new Pager($pageData);

        $createBy = array_column($data['list'], 'createBy');
        $data['creators'] = Model::admin()->getMulti(array_unique(array_filter($createBy)));

        $this->display('index.html', $data);
    }

    /**
     * 创建账户页面
     */
    public function edit()
    {
        $data['structure'] = Config::get('structure');

        $this->display('edit.html', $data);
    }

    /**
     * @ignore permission
     */
    public function info()
    {
        $data['admin'] = Model::admin()->getOne($this->session['id']);

        $this->display('info.html', $data);
    }

    /**
     * @ignore permission
     */
    public function password()
    {
        $this->display('password.html');
    }

    /**
     * @ignore permission
     */
    public function modify()
    {
        $data['admin'] = Model::admin()->getOne($this->session['id']);

        $this->display('modify.html', $data);
    }

    /**
     * @ignore permission
     */
    public function updateInfo()
    {
        $data = array(
            'realname' => $this->getParam('realname'),
            'mobile' => $this->getParam('mobile', false),
            'email' => $this->getParam('email', false)
        );
        Model::admin()->updateInfo($this->session['id'], $data);

        return array('message' => '已保存', 'redirect' => '/account/info');
    }

    /**
     * @ignore permission
     */
    public function updatePassword()
    {
        $oldPassword = $this->getParam('old_password');
        $newPassword = $this->getParam('new_password');

        Bll::admin()->modifyPassword($this->session['id'], $oldPassword, $newPassword);

        return array('message' => '已保存', 'redirect' => '/account/info');
    }

    protected function checkData($modelName, &$data, $curData, $action)
    {
        if (isset($data['account'])) {
            if (Model::admin()->getOneByAccount($data['account'])) {
                FF::throwException(Code::FAILED, '账号已存在');
            }
        }
    }

    /**
     * 请求创建账户
     */
    public function create()
    {
        $data = array(
            'account' => $this->getParam('account'),
            'password' => md5($this->getParam('password')),
            'realname' => $this->getParam('realname'),
            'department' => $this->getParam('department'),
            'post' => $this->getParam('post'),
            'mobile' => $this->getParam('mobile', false),
            'email' => $this->getParam('email', false),
            'status' => $this->getParam('status', false, 1),
            'createBy' => $this->session['id'],
            'createTime' => now()
        );

        return $this->_create('admin', $data);
    }

    /**
     * 删除账户
     */
    public function delete()
    {
        $id = $this->getParam('id');

        $result = $this->_delete('admin', $id);

        Bll::perm()->deleteRoleBindByUser($id);

        return $result;
    }

    /**
     * 修改账户状态
     */
    public function setStatus()
    {
        $id = (int)$this->getParam('id');
        $status = (int)$this->getParam('status');

        if (!in_array($status, [0, 1], true)) {
            FF::throwException(Code::PARAMS_INVALID);
        }

        Model::admin()->setStatus($id, $status);

        return true;
    }

    /**
     * @ignore permission
     */
    public function getUserList()
    {
        $where = array('status' => 1, 'account' => array('!=', 'root'));

        return Model::admin()->fetchAll($where, 'id,realname,department,post');
    }
}