<?php
// +----------------------------------------------------------------------
// | 该文件主要存放业务状态码相关的配置
// +----------------------------------------------------------------------
define('M_SITE_URL', 'https://m.cityb2b.com/');
define('D_SITE_URL', 'https://d.cityb2b.com/');
define('M_SERVER_NAME', 'm.cityb2b.com/');
define('D_SERVER_NAME', 'd.cityb2b.com/');

return [
    //加密前后辍，不能修改
    'GLOBALS' => [
        'KEY_' => 'abc',
        '_KEY' => 'def',
    ],
];
