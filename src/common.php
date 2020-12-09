<?php
// +----------------------------------------------------------------------
// | thinkphp5 Addons [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.zzstudio.net All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Byron Sampson <xiaobo.sun@qq.com>
// +----------------------------------------------------------------------

use think\App;
use think\Hook;
use think\Config;
use think\Loader;
use think\Cache;
use think\Route;
use think\Db;

// 插件目录
define('PLUGIN_PATH', ROOT_PATH . 'plugin' . DS);

// 定义路由
//Route::any('plugin/execute/:route', "\\think\\plugin\\Route@execute");
Route::any('plugin/:group/:addon/[:controller]/[:action]', "\\think\\plugin\\Route@execute");

// 如果插件目录不存在则创建
if (!is_dir(PLUGIN_PATH)) {
    @mkdir(PLUGIN_PATH, 0777, true);
}

// 注册类的根命名空间
Loader::addNamespace('plugin', PLUGIN_PATH);

// 闭包初始化行为
Hook::add('app_init', function () {
    //注册路由
    $routeArr = (array)Config::get('plugin.route');
    $domains = [];
    $rules = [];
    $execute = "\\think\\plugin\\Route@execute?addon=%s&controller=%s&action=%s";
    foreach ($routeArr as $k => $v) {
        if (is_array($v)) {
            $addon = $v['addon'];
            $domain = $v['domain'];
            $drules = [];
            foreach ($v['rule'] as $m => $n) {
                list($addon, $controller, $action) = explode('/', $n);
                $drules[$m] = sprintf($execute . '&indomain=1', $addon, $controller, $action);
            }
            //$domains[$domain] = $drules ? $drules : "\\addons\\{$k}\\controller";
            $domains[$domain] = $drules ? $drules : [];
            $domains[$domain][':controller/[:action]'] = sprintf($execute . '&indomain=1', $addon, ":controller", ":action");
        } else {
            if (!$v) {
                continue;
            }
            list($addon, $controller, $action) = explode('/', $v);
            $rules[$k] = sprintf($execute, $addon, $controller, $action);
        }
    }
    //自定义插件路径
    Route::rule($rules);
    if ($domains) {
        Route::domain($domains);
    }
    
    // 获取系统配置+插件安装时的hook机制
    $hooks = App::$debug ? [] : Cache::get('plugin_hooks', []);
    if (empty($hooks)) {
        $hooks = (array)Config::get('plugin.hooks');
        // 初始化钩子
        foreach ($hooks as $key => $values) {
            if (is_string($values)) {
                $values = explode(',', $values);
            } else {
                $values = (array)$values;
            }
            $hooks[$key] = array_filter(array_map('get_plugin_class', $values));
        }
        //获取hook表
        $list = Db::name("plugin_hook")->where('status',1)->field("name,hook_function")->select();
        foreach ($list as $k=>$v){
            $hooks[$v['hook_function']] = array_filter(array_map('get_plugin_class', [$v['name']]));
        }
        Cache::set('plugin_hooks', $hooks,3600*24);
    }
    //如果在插件中有定义app_init，则直接执行
    if (isset($hooks['app_init'])) {
        foreach ($hooks['app_init'] as $k => $v) {
            Hook::exec($v, 'app_init');
        }
    }
    Hook::import($hooks, true);
});


/**
 * 处理插件钩子
 * @param string $hook 钩子名称
 * @param mixed $params 传入参数
 * @return void
 */
function hooks($hook, $params = [])
{
    Hook::listen($hook, $params);
}

/**
 * 获取插件类的类名
 * @param $name 插件名
 * @param string $type 返回命名空间类型
 * @param string $class 当前类名
 * @return string
 */
function get_plugin_class($name, $group='backend',$type = 'hook', $class = null)
{
    $name = Loader::parseName($name);
    // 处理多级控制器情况
    if (!is_null($class) && strpos($class, '.')) {
        $class = explode('.', $class);
        foreach ($class as $key => $cls) {
            $class[$key] = Loader::parseName($cls, 1);
        }
        $class = implode('\\', $class);
    } else {
        $class = Loader::parseName(is_null($class) ? $name : $class, 1);
    }
    switch ($type) {
        case 'controller':
            $namespace = "\\plugin\\" . $name ."\\".$group. "\\controller\\" . $class;
            break;
        default:
            $namespace = "\\plugin\\" . $name . "\\" . $class;
    }
    return class_exists($namespace) ? $namespace : '';
}


/**
 * 获取插件类的配置值值
 * @param string $name 插件名
 * @return array
 */
function get_plugin_config($name)
{
    $addon = get_plugin_instance($name);
    if (!$addon) {
        return [];
    }
    return $addon->getConfig($name);
}


/**
 * 获取插件的单例
 * @param string $name 插件名
 * @return mixed|null
 */
function get_plugin_instance($name)
{
    static $_addons = [];
    if (isset($_addons[$name])) {
        return $_addons[$name];
    }
    $class = get_plugin_class($name);
    if (class_exists($class)) {
        $_addons[$name] = new $class();
        return $_addons[$name];
    } else {
        return null;
    }
}

/**
 * 插件显示内容里生成访问插件的url
 * @param $url
 * @param array $param
 * @return bool|string
 * @param bool|string $suffix 生成的URL后缀
 * @param bool|string $domain 域名
 */
function plugin_url($url, $vars = [], $suffix = true, $domain = false)
{
    $url = ltrim($url, '/');
    $addon = substr($url, 0, stripos($url, '/'));
    if (!is_array($vars)) {
        parse_str($vars, $params);
        $vars = $params;
    }
    $params = [];
    foreach ($vars as $k => $v) {
        if (substr($k, 0, 1) === ':') {
            $params[$k] = $v;
            unset($vars[$k]);
        }
    }
    $val = "@plugin/{$url}";
    $config = get_addon_config($addon);
    $dispatch = think\Request::instance()->dispatch();
    $indomain = isset($dispatch['var']['indomain']) && $dispatch['var']['indomain'] ? true : false;
    $domainprefix = $config && isset($config['domain']) && $config['domain'] ? $config['domain'] : '';
    $domain = $domainprefix && Config::get('url_domain_deploy') ? $domainprefix : $domain;
    $rewrite = $config && isset($config['rewrite']) && $config['rewrite'] ? $config['rewrite'] : [];
    if ($rewrite) {
        $path = substr($url, stripos($url, '/') + 1);
        if (isset($rewrite[$path]) && $rewrite[$path]) {
            $val = $rewrite[$path];
            array_walk($params, function ($value, $key) use (&$val) {
                $val = str_replace("[{$key}]", $value, $val);
            });
                $val = str_replace(['^', '$'], '', $val);
                if (substr($val, -1) === '/') {
                    $suffix = false;
                }
        } else {
            // 如果采用了域名部署,则需要去掉前两段
            if ($indomain && $domainprefix) {
                $arr = explode("/", $val);
                $val = implode("/", array_slice($arr, 2));
            }
        }
    } else {
        // 如果采用了域名部署,则需要去掉前两段
        if ($indomain && $domainprefix) {
            $arr = explode("/", $val);
            $val = implode("/", array_slice($arr, 2));
        }
        foreach ($params as $k => $v) {
            $vars[substr($k, 1)] = $v;
        }
    }
    $url = url($val, [], $suffix, $domain) . ($vars ? '?' . http_build_query($vars) : '');
    $url = preg_replace("/\/((?!index)[\w]+)\.php\//i", "/", $url);
    return $url;
}