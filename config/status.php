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
            'msg' => 'pincode is wrong!'
        ],
        'pincode_error_limit' => [
            'code' => 105,//pincode输入错误次数过多
            'msg' => 'Too many errors, please try again later'
        ],
        'pincode_exist' => [
            'code' => 106,//pincode已存在
            'msg' => 'Please try anthor pincode'
        ],
        'old_pincode_error' => [
            'code' => 107,//pincode输入错误
            'msg' => 'Old pincode is wrong!'
        ],
        'match_pincode' => [
            'code' => 108,
            'msg' => 'The pincode entered twice do not match'
        ],
        'order_error' => [
            'code' => 109,
            'msg' => 'Parameter error'
        ],
        'summary_error' => [
            'code' => 110,
            'msg' => 'Parameter error'
        ],
        'summary_process_error' => [//该产品尚未加工
            'code' => 111,
            'msg' => 'This product has not yet been processed'
        ],
        'summary_done_error' => [
            'code' => 112,
            'msg' => 'This product has been processed'
        ],
        'repeat_done_error' => [
            'code' => 113,
            'msg' => 'Do not click repeatedly'
        ],
        'lock_error' => [
            'code' => 114,
            'msg' => 'The server is busy, please try again later'
        ],
        'lock_processed_error' => [
            'code' => 115,
            'msg' => 'This product has been processed'
        ],
        'lock_processed_error' => [
            'code' => 116,
            'msg' => 'This product has been processed'
        ],
        'lock_other_error' => [
            'code' => 117,
            'msg' => 'This product has been locked by other'
        ],
        'lock_own_error' => [
            'code' => 118,
            'msg' => 'You have locked this product'
        ],
        'lock_result_error' => [//获取队列结果，需要轮询获取
            'code' => 119,
            'msg' => 'Please get the result again'
        ],
        'unlock_user_error' => [//解锁失败
            'code' => 120,
            'msg' => 'You do not have permission to unlock'
        ],
        'lock_user_deal_error' => [//点击已处理时，该员工没有权限
            'code' => 121,
            'msg' => 'Someone is currently working on it and you cannot click'
        ],
        'no_need_refresh' => [//不需要更新界面信息
            'code' => 122,
            'msg' => 'No update required'
        ],
        'product_plan_approved_error' => [
            'code' => 123,//没有权限管理预加工产品
            'msg' => 'You do not have permission'
        ],
        'product_plan_has_add' => [
            'code' => 124,//您已添加预加工产品
            'msg' => 'This product has been added'
        ],
        'product_error' => [
            'code' => 125,//产品信息错误
            'msg' => 'product error'
        ],
        'order_product_exists' => [
            'code' => 126,//加工明细单已存在
            'msg' => 'order already exists'
        ],
        'order_product_not_exists' => [
            'code' => 127,//加工明细单不存在
            'msg' => 'order does not exists'
        ],
        'order_not_exists' => [
            'code' => 128,//订单不存在
            'msg' => 'order does not exist'
        ],
        'quantity_error' => [
            'code' => 129,//请填写加工数量
            'msg' => 'Please fill in the processing quantity'
        ],
        'preproduct_delete_error' => [
            'code' => 130,//预加工产品不可删除
            'msg' => 'Cannot be deleted'
        ],
        'preproduct_order_done_error' => [
            'code' => 131,//预加工单尚未加工完毕，不可填写分配加工数量
            'msg' => 'Please complete the order first'
        ],
        'distribute_quantity_error' => [
            'code' => 132,//分配加工数量不正确
            'msg' => 'Incorrect quantity to be processed'
        ],
        'driver_receipt_status_error' => [
            'code' => 133,//司机状态不正确
            'msg' => 'Incorrect driver status'
        ],
        'log_out' => [
            'code' => 134,//需要退出登录
            'msg' => 'please login again'
        ],
        'picture_error' => [
            'code' => 135,//图片错误
            'msg' => 'Please upload the image again'
        ],
        'finish_order_status_error' => [
            'code' => 136,//确定完成送货状态不正确
            'msg' => 'Please confirm receipt first'
        ],
        'finish_order_error' => [
            'code' => 137,//该订单已完成送货
            'msg' => 'You have completed the delivery'
        ],
        'boxnum_max_error' => [
            'code' => 138,//修改标签数最大限制
            'msg' => 'The number of boxes exceeds the maximum limit'
        ],
        'boxnum_min_error' => [
            'code' => 139,//修改标签数最小限制
            'msg' => 'The number of boxes exceeds the minimum limit'
        ],
        'boxnum_permission_error' => [
            'code' => 140,//不允许修改标签序号
            'msg' => 'You do not have permission to modify the label serial number'
        ],
        'item_boxnum_error' => [
            'code' => 141,//标签序号不正确
            'msg' => 'Incorrect label serial number'
        ],
        'pick_permission_error' => [//解锁失败
            'code' => 142,
            'msg' => 'You do not have permission to pick'
        ],
        'upload_error' => [
            'code' => 143,
            'msg' => 'Image upload failed'
        ],
        'token_error' => [
            'code' => 144,
            'msg' => 'token error'
        ],
        'order_return_approved' => [
            'code' => 145,
            'msg' => 'The return of the order has been completed, please do not apply again'
        ],
    ]
];
