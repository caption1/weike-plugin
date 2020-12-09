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
namespace think\plugin;

use think\Hook;
use think\Request;
use think\exception\HttpException;
use think\Loader;
use think\Db;
use think\Cache;
/**
 * 插件执行默认控制器
 * Class AddonsController
 * @package think\addons
 */
class Route 
{
    /**
     * 插件执行
     */
    public function execute($group=null,$addon = null, $controller = null, $action = null)
    {
        $request = Request::instance();
        // 是否自动转换控制器和操作名
        $convert = config('url_convert');
        $filter = $convert ? 'strtolower' : 'trim';
        
        $addon = $addon ? trim(call_user_func($filter, $addon)) : '';
        $controller = $controller ? trim(call_user_func($filter, $controller)) : 'index';
        $action = $action ? trim(call_user_func($filter, $action)) : 'index';
        Hook::listen('plugin_begin', $request);
        
        if (!empty($addon) && !empty($controller) && !empty($action)) {
            //查询插件信息
            $info = Cache::remember('plugin_info'.$addon,function()use($addon){
                return Db::name("plugin")->where('name',$addon)->find();
            },3600);            
            if (!$info) {
                $this->error('插件不存在','2000');
            }
            if ($info['status']!=1) {
                $this->error('插件已禁用','2001');
            }
            //检测该店铺是否有插件权限
            $userPlugin =  Cache::remember('user_plugin_id'.$info['id'],function()use($info){
                return Db::name("user_plugin")->where('store_id',$this->storeId)->where('plugin_id',$info['id'])->find();
            },3600); 
            if(empty($userPlugin)){
                $this->error('插件未购买','2002');
            }
            if($userPlugin['expire_time'] > 0 && $userPlugin['expire_time'] < time()){
                $this->error('插已过期','2003');
            }
            
            $dispatch = $request->dispatch();
            if (isset($dispatch['var']) && $dispatch['var']) {
                //$request->route($dispatch['var']);
            }
            // 设置当前请求的控制器、操作
            $request->controller($controller)->action($action);
            // 兼容旧版本行为,即将移除,不建议使用
            Hook::listen('plugin_init', $request);
            
            $class = get_plugin_class($addon,$group, 'controller', $controller);
           
            if (!$class) {
                throw new HttpException(404, __('addon controller %s not found', Loader::parseName($controller, 1)));
            }
            
            $instance = new $class($request);
            
            $vars = [];
            if (is_callable([$instance, $action])) {
                // 执行操作方法
                $call = [$instance, $action];
            } elseif (is_callable([$instance, '_empty'])) {
                // 空操作
                $call = [$instance, '_empty'];
                $vars = [$action];
            } else {
                // 操作不存在
                throw new HttpException(404, __('addon action %s not found', get_class($instance) . '->' . $action . '()'));
            }
            Hook::listen('plugin_action_begin', $call);
            var_dump($call);exit();
            return call_user_func_array($call, $vars);
        } else {
            abort(500, lang('addon can not be empty'));
        }
    }
}
