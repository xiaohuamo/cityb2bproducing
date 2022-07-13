<?php
declare (strict_types = 1);

namespace app\driver\validate;

use think\Validate;

class DriverValidate extends Validate
{

    protected $rule = [
        'orderId' => 'require',//账号
        'receipt_picture'  =>  'require',//密码
        'logistic_delivery_date'  =>  'require',//配送日期
        'logistic_schedule_id' => 'require',//调度id
        'start_kile_metre' => 'require|float',//开始里程
        'start_temprature' => 'require|float',//开始工作-车厢温度
        'start_truck_check' => 'require|in:0,1',//开始工作-是否检查车辆 0-否 1-是
        'start_truck_check_content' => 'require',//开始工作-车辆问题描述
        'end_kile_metre' => 'require|float',//结束里程
        'end_temprature' => 'require|float',//结束工作-车厢温度
        'end_truck_check' => 'require|in:0,1',//结束工作-是否检查车辆 0-否 1-是
        'end_truck_check_content' => 'require',//结束工作-车辆问题描述
        'return_data' => 'require|array',//退货数据
        'reasonType' => 'require|in:1,2,3,4,5'
    ];

    protected $scene = [
        'updateOrderRceiptPicture' => ['orderId','receipt_picture'],//更新收货图片
        'confirmOrderFinish' => ['orderId'],//确认完成收货
        'confirmAllOrderFinish' => ['logistic_delivery_date','logistic_schedule_id'],//确认全部订单完成收货
        'truckJobInfo' => ['logistic_delivery_date','logistic_schedule_id'],//获取车辆信息
        'doStartJob' => ['logistic_delivery_date','logistic_schedule_id','start_kile_metre','start_temprature','start_truck_check'],//开始工作
        'doJobDone' => ['logistic_delivery_date','logistic_schedule_id','end_kile_metre','end_temprature','end_truck_check'],//结束工作
        'doReturnStock' => ['orderId','return_data'],//退货
    ];

    /**
     * 错误信息
     */
    protected $message = [
        'start_kile_metre.require' => 'please input vechile start kilo metres.',
        'start_temprature.require' => ' please input vechile start temprature.',
        'end_kile_metre.require' => 'please input vechile end kilo metres.',
        'end_temprature.require' => ' please input vechile end temprature.',
    ];
}
