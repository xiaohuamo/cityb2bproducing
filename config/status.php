<?php
// +----------------------------------------------------------------------
// | 该文件主要存放业务状态码相关的配置
// +----------------------------------------------------------------------

return [
    //api接口状态码设置
    'code' => [
        'param_error' => [
            'code' => 100,//参数错误
            'msg' => 'Param error'
        ],
        'success' => [
            'code' => 200,//成功
            'msg' => 'Success'
        ],
        'system_error' => [
            'code' => 500,//系统错误
            'msg' => 'System error'
        ],
        'account_or_pwd_error' => [
            'code' => 101,//账号或密码不正确
            'msg' => 'Username or Password is wrong!',//账号不存在
        ],
        'account_approved_error' => [
            'code' => 102,//会员未经系统批准，无法登录！
            'msg' => 'Member is not approved by system ,can not login!'
        ],
        'account_suspend_error' => [
            'code' => 103,//会员暂停，请联系ubonus
            'msg' => 'Member is suspend ,please contact ubonus'
        ],
        'pincode_error' => [
            'code' => 104,//pincode输入错误
            'msg' => 'Pincode is wrong!'
        ],
        'pincode_error_limit' => [
            'code' => 105,//pincode输入错误次数过多
            'msg' => 'Too many errors, please try again later'
        ],
    ]
];
