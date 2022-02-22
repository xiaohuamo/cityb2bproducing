<?php
declare (strict_types = 1);

namespace app\product\validate;

use think\Validate;

class IndexValidate extends Validate
{

    protected $rule = [
        'id' => 'require',//加工明细id
        'is_producing_done'  =>  'require|in:0,1',//是否已处理 1已处理 0未处理
        'product_id' => 'require',//产品id
        'logistic_delivery_date' => 'require',//配送日期
        'new_customer_buying_quantity' => 'require|float'
    ];

    protected $scene = [
        'changeProductOrderStatus' => ['id','is_producing_done'],//修改加工状态
        'lockProduct' => ['logistic_delivery_date','product_id'],//锁定产品
        'editBuyingQuantity' => ['id','new_customer_buying_quantity']
    ];

    /**
     * 错误信息
    */
    protected $message = [

    ];
}
