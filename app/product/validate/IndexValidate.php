<?php
declare (strict_types = 1);

namespace app\product\validate;

use think\Validate;

class IndexValidate extends Validate
{

    protected $rule = [
        'id' => 'require',//加工明细id
        'is_producing_done'  =>  'require|in:0,1,2',//是否已处理 1已处理 0未处理 2-正在处理
        'product_id' => 'require',//产品id
        'logistic_delivery_date' => 'require',//配送日期
        'new_customer_buying_quantity' => 'require|float',
        'action_type' => 'require|in:1,2',//设置置顶操作 1-设置置顶 2-取消置顶
        'quantity' => 'require|float',//预加工数量
        'orderId' => 'require',//订单id
        'receipt_picture' => 'require',//图片
        'num' => 'require',
        'id_arr' => 'require|array',
        'print_type' => 'require|in:0,1,2,3,4',//打印类型 1-Fit print all 2-fit print 3-print order 4-blank label
        'logistic_schedule_id' => 'require',//司机调度id
    ];

    protected $scene = [
        'changeProductOrderStatus' => ['id','is_producing_done'],//修改加工状态
        'lockProduct' => ['logistic_delivery_date','product_id'],//锁定产品
        'editBuyingQuantity' => ['id','new_customer_buying_quantity'],//修改加工数量
        'setProductTop' => ['product_id','action_type'],//设置置顶
        'setProductPlaning' => ['logistic_delivery_date','product_id','action_type'],//设置预加工产品
        'addOrderProductPlaning' => ['logistic_delivery_date','product_id','guige1_id','quantity','action_type'],//添加预加工订单
        'lockOrder' => ['logistic_delivery_date','orderId'],//锁定订单
        'confirmOrderFinish' => ['orderId','receipt_picture'],//确定完成送货校验
        'lockNoneProcessedProduct' => ['logistic_delivery_date','logistic_truck_No','product_id','logistic_schedule_id'],//锁定非加工产品
        'changeNoneProcessedProductOrderStatus' => ['logistic_delivery_date','logistic_truck_No','product_id','is_producing_done','logistic_schedule_id'],//更改非加工产品的状态
        'lockProductItem' => ['logistic_delivery_date','logistic_truck_No','product_id','guige1_id','logistic_schedule_id'],//拣货端锁定产品
        'changePickAllProductOrderStatus' => ['logistic_delivery_date','logistic_truck_No','product_id','guige1_id','is_producing_done','logistic_schedule_id'],//修改加工状态
        'editBoxNumber' => ['id','num'],//修改box数量
        'orderBoxsNumber' => ['id_arr','print_type'],//获取并修改订单box数量
    ];

    /**
     * 错误信息
    */
    protected $message = [

    ];
}
