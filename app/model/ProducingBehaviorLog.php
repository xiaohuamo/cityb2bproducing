<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\Model;

/**
 * @mixin \think\Model
 */
class ProducingBehaviorLog extends Model
{
    use modelTrait;

    /**
     * 用户行为日志记录
     * @return array
     */
    public function addProducingBehaviorLog($user_id,$business_userId,$behavior_type,$delivery_date,$data)
    {
        $data = [
            'userId' => $user_id,
            'business_userId' => $business_userId,
            'behavior_type' => $behavior_type,
            'delivery_date' => $delivery_date,
            'product_id' => $data['product_id'] ?? 0,
            'guige1_id' =>  $data['guige1_id'] ?? 0,
            'wj_customer_coupon_id' =>  $data['wj_customer_coupon_id'] ?? 0,
            'new_customer_buying_quantity' =>  $data['new_customer_buying_quantity'] ?? 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $res = self::createData($data);
        return $res;
    }
}
