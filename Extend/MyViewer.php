<?php
/**
 * 视图器扩展
 */

namespace FF\Extend;

use FF\Framework\Common\Code;
use FF\Framework\Common\Format;
use FF\Framework\Core\FF;
use FF\Framework\Core\FFViewer;
use FF\Framework\Utils\Config;
use FF\Library\Utils\Request;

class MyViewer extends FFViewer
{
    protected function initRenders()
    {
        $this->setRender(Format::PBUF, function ($data) {
            header("Content-type: application/json; charset=utf-8");
            //echo json_encode($data, JSON_UNESCAPED_UNICODE);
            echo Request::serializeMessage($data);
        });
    }

    private function getSmarty()
    {
        file_require(PATH_LIB . '/Vendor/Smarty/Smarty.class.php');

        $isProduct = FF::isProduct();

        $smarty = new \Smarty();
        $smarty->left_delimiter = '{{';
        $smarty->right_delimiter = '}}';
        $smarty->force_compile = !$isProduct;
        $smarty->compile_check = !$isProduct;
        $smarty->debugging = false;
        $smarty->caching = false;
        $smarty->cache_lifetime = 7 * 86400;
        $smarty->setTemplateDir(PATH_VIEW);
        $smarty->setCompileDir(PATH_VIEW . '/Compile');
        $smarty->setCacheDir(PATH_VIEW . '/Cache');

        return $smarty;
    }

    protected function tplRendering($tpl, $data = array())
    {
        $smarty = $this->getSmarty();

        $data['REQUEST'] = $_REQUEST;
        $data['ENV'] = ENV;
        $data['JS_URL'] = JS_URL;
        $data['CSS_URL'] = CSS_URL;
        $data['IMG_URL'] = IMG_URL;
        $data['BASE_URL'] = BASE_URL;
        $data['RES_URL'] = RES_URL;
        $data['CDN_URL'] = CDN_URL;

        $data['VER'] = FF::isProduct() ? Config::get('core', 'static_ver') : time();

        foreach ($data as $key => $val) {
            $smarty->assign($key, $val);
        }

        $path = FF::getRouter()->getPath();
        $controller = FF::getRouter()->getController();
        $tpl = $path . '/' . $controller . '/' . $tpl;
        $tpl = substr($tpl, 1);

        if (!$smarty->templateExists($tpl)) {
            $this->error(Code::FILE_NOT_EXIST, "Template {$tpl} not found");
        }

        $smarty->display($tpl);
    }
}