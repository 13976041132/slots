<?php
/**
 * 路由器
 * 支持多路由规则
 */

namespace FF\Framework\Core;

class FFRouter
{
    protected $route = '';
    protected $path = '';
    protected $controller = '';
    protected $method = '';
    protected $rules = array();
    protected $isValid = false;

    public function __construct()
    {
        $this->addRule($this->getDefaultRule());
    }

    /**
     * 增加一条路由规则
     * @param callable $rule
     */
    protected function addRule(callable $rule)
    {
        $this->rules[] = $rule;
    }

    /**
     * 获取默认路由规则
     * @return callable
     */
    protected function getDefaultRule()
    {
        return function () {
            $route = '';
            if (isset($_SERVER['REQUEST_URI'])) {
                $route = explode('?', $_SERVER['REQUEST_URI'])[0];
            }
            if (!$route || $route == '/' || $route == '/index.php') {
                $route = FF::getOptions()['route_default'];
                if (!$route) $route = '/index/index';
            }
            return $route;
        };
    }

    /**
     * 路由初始化
     */
    public function initRoute()
    {
        //判定route
        $routes = null;
        foreach ($this->rules as $rule) {
            if ($route = call_user_func($rule)) {
                if ($routes = $this->parseRoute($route)) {
                    break;
                }
            }
        }

        if (!$routes) {
            header('HTTP/1.1 404 Not Found');
            exit(0);
        }

        $this->route = $routes['route'];
        $this->path = $routes['path'];
        $this->controller = $routes['controller'];
        $this->method = $routes['method'];
        $this->isValid = true;
    }

    /**
     * 解析路由
     * @param string $route
     * @return array|null
     */
    private function parseRoute($route)
    {
        $route = str_replace('//', '/', $route);
        if (substr($route, -1) == '/') $route = substr($route, 0, -1);
        if (substr($route, 0, 1) != '/') $route = '/' . $route;

        $routes = explode('/', $route);

        if (count($routes) < 2) return null;
        if (count($routes) == 2) $routes[] = 'index';

        $method = array_pop($routes);
        $controller = ucfirst(array_pop($routes));

        //目录名遵循首字母大写原则
        $paths = array();
        foreach ($routes as $v) {
            if ($v !== '') {
                $paths[] = ucfirst($v);
            }
        }
        $path = $paths ? ('/' . implode('/', $paths)) : '';
        $route = '/' . $controller . '/' . $method;
        if ($path) $route = $path . $route;

        //转换格式
        $routes = array(
            'route' => $route,
            'path' => $path,
            'controller' => $controller,
            'method' => $method
        );

        if (!$this->checkRoute($routes)) {
            return null;
        }

        return $routes;
    }

    /**
     * 检查路由是否有效
     * @param array $routes
     * @return bool
     */
    private function checkRoute($routes)
    {
        $class = FF::getControllerClass($routes);

        $valid = class_exists($class, true);
        $valid = $valid && method_exists($class, $routes['method']);

        if ($valid) {
            $ref = new \ReflectionMethod($class, $routes['method']);
            $modifiers = \Reflection::getModifierNames($ref->getModifiers());
            foreach ($modifiers as $modifier) {
                if ($modifier == 'protected' || $modifier == 'private') {
                    $valid = false;
                }
            }
        }

        return $valid;
    }

    /**
     * 获取路由解析后的信息
     * @return array
     */
    public function getRouteInfo()
    {
        return array(
            'route' => $this->route,
            'path' => $this->path,
            'controller' => $this->controller,
            'method' => $this->method
        );
    }

    /**
     * 获取完整路由
     * @return string
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * 获取路由Path部分
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * 获取路由Controller部分
     * @return string
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * 获取路由Method部分
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * 获取路由是否有效
     * @return bool
     */
    public function isValid()
    {
        return $this->isValid;
    }
}