<?php
// +----------------------------------------------------------------------
// | Created by PHPstorm: [ JRK丶Admin ]
// +----------------------------------------------------------------------
// | Copyright (c) 2019~2022 [LuckyHHY] All rights reserved.
// +----------------------------------------------------------------------
// | SiteUrl: http://www.luckyhhy.cn
// +----------------------------------------------------------------------
// | Author: LuckyHhy <jackhhy520@qq.com>
// +----------------------------------------------------------------------
// | Date: 2020/6/25 0025
// +----------------------------------------------------------------------
// | Description:  
// +----------------------------------------------------------------------

namespace app\common\listener;


class AppInit
{
    public function handle(){
        // 设置mbstring字符编码
        mb_internal_encoding('UTF-8');
        $this->initSystemConst();
    }

    /**
     * @author: msrenly <1169656090@qq.com>
     * @date: 2021/8/25 10:26
     * @describe:初始化系统常量
     */
    private function initSystemConst(){
//        !defined('SITE_URL') && define('SITE_URL', 'https://www.cooltechsolution.com');//网址
        !defined('SITE_URL') && define('SITE_URL', 'http://192.168.50.105');//网址
        !defined('VERSION') && define('VERSION', env("admin.version","1.0")); //版本号
        !defined('_NAME') && define('_NAME', env("admin.name",'Member System')); //系统名称
        !defined('DS') && define('DS', DIRECTORY_SEPARATOR);
        !defined('DS_CONS') && define('DS_CONS', '\\');
        !defined('PICTURE_URL') && define('PICTURE_URL', SITE_URL);
    }

}