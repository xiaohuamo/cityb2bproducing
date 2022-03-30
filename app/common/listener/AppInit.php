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
        !defined('M_SITE_URL') && define('M_SITE_URL', 'https://m.cityb2b.com/');//生产网址
        !defined('D_SITE_URL') && define('D_SITE_URL', 'https://d.cityb2b.com/');//拣货员网址
        !defined('M_SERVER_NAME') && define('M_SERVER_NAME', 'm.cityb2b.com');//生产域名
        !defined('D_SERVER_NAME') && define('D_SERVER_NAME', 'd.cityb2b.com');//拣货员域名
        !defined('DS') && define('DS', DIRECTORY_SEPARATOR);
        !defined('DS_CONS') && define('DS_CONS', '\\');
        !defined('PICTURE_URL') && define('PICTURE_URL', SITE_URL);
    }

}
