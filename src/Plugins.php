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
namespace think;

use think\Config;
use think\View;
use think\Db;

/**
 * 插件基类
 * Class Addons
 * @author Byron Sampson <xiaobo.sun@qq.com>
 * @package think\plugin
 */
abstract class Plugins
{
    /**
     * 视图实例对象
     * @var view
     * @access protected
     */
    protected $view = null;

    // 当前错误信息
    protected $error;

    /**
     * $info = [
     *  'name'          => 'Test',
     *  'title'         => '测试插件',
     *  'description'   => '用于thinkphp5的插件扩展演示',
     *  'status'        => 1,
     *  'author'        => 'byron sampson',
     *  'version'       => '0.1'
     * ]
     */
    public $info = [];
    public $addons_path = '';
    public $config_file = '';

    /**
     * 架构函数
     * @access public
     */
    public function __construct()
    {
        // 获取当前插件目录
        $this->addons_path = PLUGIN_PATH . $this->getName() . DS;
        // 读取当前插件配置信息
        if (is_file($this->addons_path . 'config.php')) {
            $this->config_file = $this->addons_path . 'config.php';
        }

        // 初始化视图模型
        $config = ['view_path' => $this->addons_path];
        $config = array_merge(Config::get('template'), $config);
        $this->view = new View($config, Config::get('view_replace_str'));

        // 控制器初始化
        if (method_exists($this, '_initialize')) {
            $this->_initialize();
        }
    }
    

    /**
     * 获取插件的配置数组
     * @param string $name 可选模块名
     * @return array|mixed|null
     */
    final public function getConfig($name = '')
    {
        static $_config = array();
        if (empty($name)) {
            $name = $this->getName();
        }
        if (isset($_config[$name])) {
            return $_config[$name];
        }
        $map['name'] = $name;
        $map['status'] = 1;
        $config = [];
        if (is_file($this->config_file)) {
            $temp_arr = include $this->config_file;
            foreach ($temp_arr as $key => $value) {
                if ($value['type'] == 'group') {
                    foreach ($value['options'] as $gkey => $gvalue) {
                        foreach ($gvalue['options'] as $ikey => $ivalue) {
                            $config[$ikey] = $ivalue['value'];
                        }
                    }
                } else {
                    $config[$key] = $temp_arr[$key]['value'];
                }
            }
            unset($temp_arr);
        }
        $_config[$name] = $config;

        return $config;
    }

    /**
     * 获取当前模块名
     * @return string
     */
    final public function getName()
    {
        $data = explode('\\', get_class($this));
        return strtolower(array_pop($data));
    }

    /**
     * 检查配置信息是否完整
     * @return bool
     */
    final public function checkInfo()
    {
        $info_check_keys = ['name', 'title', 'description', 'status', 'author', 'version'];
        foreach ($info_check_keys as $value) {
            if (!array_key_exists($value, $this->info)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 加载模板和页面输出 可以返回输出内容
     * @access public
     * @param string $template 模板文件名或者内容
     * @param array $vars 模板输出变量
     * @param array $replace 替换内容
     * @param array $config 模板参数
     * @return mixed
     * @throws \Exception
     */
    public function fetch($template = '', $vars = [], $replace = [], $config = [])
    {
        if (!is_file($template)) {
            $template = '/' . $template;
        }
        // 关闭模板布局
        $this->view->engine->layout(false);

        echo $this->view->fetch($template, $vars, $replace, $config);
    }

    /**
     * 渲染内容输出
     * @access public
     * @param string $content 内容
     * @param array $vars 模板输出变量
     * @param array $replace 替换内容
     * @param array $config 模板参数
     * @return mixed
     */
    public function display($content, $vars = [], $replace = [], $config = [])
    {
        // 关闭模板布局
        $this->view->engine->layout(false);

        echo $this->view->display($content, $vars, $replace, $config);
    }

    /**
     * 渲染内容输出
     * @access public
     * @param string $content 内容
     * @param array $vars 模板输出变量
     * @return mixed
     */
    public function show($content, $vars = [])
    {
        // 关闭模板布局
        $this->view->engine->layout(false);

        echo $this->view->fetch($content, $vars, [], [], true);
    }

    /**
     * 模板变量赋值
     * @access protected
     * @param mixed $name 要显示的模板变量
     * @param mixed $value 变量的值
     * @return void
     */
    public function assign($name, $value = '')
    {
        $this->view->assign($name, $value);
    }

    /**
     * 获取当前错误信息
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }
    
    //必须实现安装
    public function init($group='',$hookName=''){
        if(!$group){
            return false;
        }
        if(!$hookName){
            return false;
        }
        $data['name'] = $this->getName();
        $data['group'] = $group;
        $data['hook_function'] = $hookName;
        $data['create_time'] = time();
        Db::name("plugin_hook")->insert($data);
        Cache::rm('plugin_hooks');
        return true;
    }

    /**
     * 初始化event事件
     * @param string $event_type
     * @param string $controller
     * @param string $method
     * @return boolean
     */
    public function event($event_type='',$controller='',$method=''){
        if(!$event_type){
            return false;
        }
        
        $data['name'] = $this->getName();
        $data['event_type'] = $event_type;
        $data['controller'] = $controller;
        $data['method'] = $method;
        $data['create_time'] = time();
        Db::name("plugin_event")->insert($data);
        Cache::rm('plugin_event'.$event_type);
        return true;
    }
    
    /**
     * 插件的统一安装
     * @param unknown $group
     * @param unknown $hookName
     * @return boolean
     */
    abstract public function install();

    /**
     * 插件的统一卸载
     */
    public function uninstall(){
        Db::name("plugin_hook")->where('name',$this->getName())->delete();
        Db::name("plugin_event")->where('name',$this->getName())->delete();
        Cache::rm('plugin_hooks');
    }
    
    /**
     * 禁用
     */
    public function disable(){
        Db::name("plugin")->where('name',$this->getName())->update(['status'=>0]);
        Cache::rm('plugin_hooks');
    }
    
    /**
     * 启用
     */
    public function enable(){
        Db::name("plugin")->where('name',$this->getName())->update(['status'=>1]);
        Cache::rm('plugin_hooks');
    }
    
}
