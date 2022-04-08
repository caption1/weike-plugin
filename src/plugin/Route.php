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
use think\Response;
use think\exception\HttpResponseException;
use think\Controller;
use think\Config;
/**
 * 插件执行默认控制器
 * Class AddonsController
 * @package think\addons
 */
class Route extends Controller
{
    protected $responseType = 'json';
    
//     // 当前插件操作
//     protected $plugin = null;
//     protected $controller = null;
//     protected $action = null;
//     // 当前template
//     protected $template;
//     // 模板配置信息
//     protected $config = [
//         'type' => 'Think',
//         'view_path' => '',
//         'view_suffix' => 'html',
//         'strip_space' => true,
//         'view_depr' => DS,
//         'tpl_begin' => '{',
//         'tpl_end' => '}',
//         'taglib_begin' => '{',
//         'taglib_end' => '}',
//     ];
    
//     /**
//      * 架构函数
//      * @param Request $request Request对象
//      * @access public
//      */
//     public function __construct(Request $request = null)
//     {
//         // 生成request对象
//         $this->request = is_null($request) ? Request::instance() : $request;
//         // 初始化配置信息
//         $this->config = Config::get('template') ?: $this->config;
        
//         // 处理路由参数
//         $param = $this->request->param();
//         $dispatch = $this->request->dispatch();
//         $var = isset($dispatch['var']) ? $dispatch['var'] : [];
//         $var = array_merge($param, $var);
//         if (isset($dispatch['method']) && substr($dispatch['method'][0], 0, 7) == "\\addons")
//         {
//             $arr = explode("\\", $dispatch['method'][0]);
//             $addon = strtolower($arr[2]);
//             $controller = strtolower(end($arr));
//             $action = $dispatch['method'][1];
//         }
//         else
//         {
//             $addon = isset($var['addon']) ? $var['addon'] : '';
//             $controller = isset($var['controller']) ? $var['controller'] : '';
//             $action = isset($var['action']) ? $var['action'] : '';
//         }
        
//         $group = isset($var['group']) ? $var['group'] : '';
        
//         // 是否自动转换控制器和操作名
//         $convert = \think\Config::get('url_convert');
//         $filter = $convert ? 'strtolower' : 'trim';
        
//         $this->plugin = $addon ? call_user_func($filter, $addon) : '';
//         $this->controller = $controller ? call_user_func($filter, $controller) : 'index';
//         $this->action = $action ? call_user_func($filter, $action) : 'index';
//         $this->group = $group ? call_user_func($filter, $group) : 'frontend';
//         // 生成view_path
//         $view_path = $this->config['view_path'] ?: 'view';
        
//         // 重置配置
//         Config::set('template.view_path', PLUGIN_PATH . $this->plugin.DS.$this->group . DS . $view_path . DS);
        
//         parent::__construct($request);
        
//     }
    
    
    /**
     * 插件执行
     */
    public function execute($addon = null,$group=null, $controller = null, $action = null)
    {
        $request = Request::instance();
        // 是否自动转换控制器和操作名
        $convert = config('url_convert');
        $filter = $convert ? 'strtolower' : 'trim';
        
        $addon = $addon ? trim(call_user_func($filter, $addon)) : '';
        $controller = $controller ? trim(call_user_func($filter, $controller)) : 'index';
        $action = $action ? trim(call_user_func($filter, $action)) : 'index';

        if (!empty($addon) && !empty($controller) && !empty($action)) {
            //查询插件信息
            $info = \app\common\model\Plugin::getPluginInfo($addon);
            if (!$info) {
                $this->error('插件不存在','2000');
            }
            if ($info['status']) {
                $this->error('插件已禁用','2001');
            }
            $storeId = input("store_id");
            //Cache::rm('user_plugin_id'.$info['id'].$storeId);
            //检测该店铺是否有插件权限
//             $userPlugin =  Cache::remember('user_plugin_id'.$info['id'].$storeId,function()use($info,$storeId){
//                 return Db::name("user_plugin")->where('store_id',$storeId)->where('plugin_id',$info['id'])->find();
//             },1800); 
            $userPlugin = \app\common\model\UserPlugin::getStorePluginInfo($storeId, $info['id']);
            if(!$userPlugin || empty($userPlugin)){
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

            return call_user_func_array($call, $vars);
        } else {
            abort(500, lang('addon can not be empty'));
        }
    }
    
    /**
     * 操作成功返回的数据
     * @param string $msg    提示信息
     * @param mixed  $data   要返回的数据
     * @param int    $code   错误码，默认为1
     * @param string $type   输出类型
     * @param array  $header 发送的 Header 信息
     */
    protected function success($data = null, $msg = '', $code = '0000', $type = null, array $header = [])
    {
        $this->result($msg, $data, $code, $type, $header);
    }
    
    /**
     * 操作失败返回的数据
     * @param string $msg    提示信息
     * @param mixed  $data   要返回的数据
     * @param int    $code   错误码，默认为0
     * @param string $type   输出类型
     * @param array  $header 发送的 Header 信息
     */
    protected function error($msg = '', $code = '1004',$data = null,  $type = null, array $header = [])
    {
        $this->result($msg, $data, $code, $type, $header);
    }
    
    /**
     * 返回封装后的 API 数据到客户端
     * @access protected
     * @param mixed  $msg    提示信息
     * @param mixed  $data   要返回的数据
     * @param int    $code   错误码，默认为0
     * @param string $type   输出类型，支持json/xml/jsonp
     * @param array  $header 发送的 Header 信息
     * @return void
     * @throws HttpResponseException
     */
    protected function result($msg, $data = null, $code = '0000', $type = null, array $header = [])
    {
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'time' => Request::instance()->server('REQUEST_TIME'),
            'data' => $data,
        ];
        
        // 如果未设置类型则自动判断
        $type = $type ? $type : ($this->request->param(config('var_jsonp_handler')) ? 'jsonp' : $this->responseType);
       
        if (isset($header['statuscode'])) {
            $code = $header['statuscode'];
            unset($header['statuscode']);
        } else {
            //未设置状态码,根据code值判断
            $code = $code >= 1000 || $code < 200 ? 200 : $code;
        }
        $response = Response::create($result, $type, $code)->header($header);
        throw new HttpResponseException($response);
    }
    
}
