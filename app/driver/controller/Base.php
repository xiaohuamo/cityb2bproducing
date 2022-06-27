<?php

declare (strict_types = 1);

namespace app\driver\controller;

use think\App;
use think\facade\View;

/**
 * 控制器基础类
 */
abstract class Base
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    /**
     * 检测是否登录
     * @var array
     */
    protected $middleware = [
        'app\driver\middleware\CheckApi'=>['except' => ['login','loginByPassword','me','myInfo','order','confirmRecept','customerSearch','startJob','jobDone','returnStock','returnStockReason']],
    ];

    /**
     * 分页数量
     * @var string
     */
    protected $pageSize = '';

    /**
     * 构造方法
     * @access public
     * @param  App $app 应用对象
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $this->app->request;

        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {

    }

    /**
     * @param string $template
     * @return string
     * @throws \Exception
     * @author: LuckyHhy <jackhhy520@qq.com>
     * @describe:
     */
    protected function fetch(string $template = '')
    {
        return View::fetch($template);
    }
}
