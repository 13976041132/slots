<?php
/**
 * Admin控制器基类
 */

namespace FF\App\Admin\Controller;

use FF\Extend\MyController;
use FF\Factory\Bll;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;

class BaseController extends MyController
{
    protected $session = null;

    private $filterNotNeedLogin = array(
        '/Account/login', '/Account/doLogin', '/Account/logout'
    );

    private $filterNotNeedPerm = array(
        '/Account/login', '/Account/doLogin', '/Account/logout', '/Index/index'
    );

    public function init()
    {
        if (!$this->checkSession()) {
            $this->forceLogin('尚未登录或登录已过期，请登录');
        }

        if ($this->session && $this->session['from'] != 'BI' && !Bll::admin()->isValid($this->session['id'])) {
            $this->forceLogin('账号已禁用，请退出登录');
        }

        if (!$this->checkPermission()) {
            $this->accessForbidden('无访问权限');
        }
    }

    /**
     * 检查登录session
     * @return bool
     */
    private function checkSession()
    {
        if (!empty($_REQUEST['bi_token'])) {
            //从BI访问，采用token验证
            Bll::session()->setSessionId($_REQUEST['bi_token']);
            $this->session = Bll::session()->getSessionData();
            if (!$this->session) {
                $tokenInfo = Bll::biData()->authToken($_REQUEST['bi_token']);
                if ($tokenInfo) {
                    $session = array(
                        'id' => $tokenInfo['aid'],
                        'account' => $tokenInfo['account'],
                        'realname' => $tokenInfo['realname'],
                        'from' => 'BI'
                    );
                    Bll::session()->save($session, 7 * 86400);
                    $this->session = $session;
                }
            }
        } else {
            //默认采用cookie验证
            Bll::session()->setCookieKey('admin_session_id');
            $this->session = Bll::session()->getSessionData();
            if ($this->session) {
                $this->session['from'] = 'SELF';
            }
        }

        if ($this->isInFilter($this->filterNotNeedLogin)) {
            return true;
        }

        return $this->session ? true : false;
    }

    /**
     * 检查访问权限
     * @return bool
     */
    private function checkPermission()
    {
        if (!FF::isProduct()) {
            return true;
        }

        if ($this->session['account'] == 'root') {
            return true;
        }

        if ($this->isInFilter($this->filterNotNeedPerm)) {
            return true;
        }

        $route = FF::getRouter()->getRoute();
        $result = Bll::perm()->hasPerm($this->session['id'], strtolower($route));

        return $result;
    }

    /**
     * 强制登录
     * @param $reason
     */
    protected function forceLogin($reason)
    {
        if (is_ajax()) {
            FF::throwException(Code::FAILED, $reason);
        } else {
            Bll::session()->clean();
            redirect(BASE_URL . '/account/login');
            exit(0);
        }
    }

    /**
     * 无权限禁止访问
     * @param $reason
     */
    protected function accessForbidden($reason)
    {
        if (is_ajax()) {
            FF::throwException(Code::FAILED, $reason);
        } else {
            die($reason);
        }
    }

    /**
     * 获取可编辑字段列表
     * @param $model
     * @return array
     */
    protected function getEditableFields($model)
    {
        return array();
    }

    /**
     * 编辑字段并保存
     * @return array
     */
    public function updateField()
    {
        $model = $this->getParam('model');
        $key = $this->getParam('key');
        $value = $this->getParam('value');
        $id = $this->getParam('id');

        $fields = $this->getEditableFields($model);

        if (!$fields || !in_array($key, $fields, true)) {
            FF::throwException(Code::FAILED, '该字段不支持编辑');
        }

        $data = array($key => $value);

        $result = $this->_update($model, $id, $data);
        unset($result['reload']);

        return $result;
    }
}