<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\facade\Db;
use think\Model;

/**
 * @mixin \think\Model
 */
class DispatchingBehaviorLog extends Model
{
    use modelTrait;

    /**
     * 用户行为日志记录
     * @return array
     */
    public function addBehaviorLog($user_id,$business_userId,$behavior_type,$delivery_date,$data)
    {
        $data = [
            'userId' => $user_id,
            'business_userId' => $business_userId,
            'behavior_type' => $behavior_type,
            'delivery_date' => $delivery_date,
            'orderId' => $data['orderId'] ?? 0,
            'wj_customer_coupon_id' =>  $data['wj_customer_coupon_id'] ?? 0,
            'new_customer_buying_quantity' =>  $data['new_customer_buying_quantity'] ?? 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $res = self::createData($data);
        return $res;
    }

    /**
     * 获取用户日志
     * @return array
     */
    public function getLogData($businessId,$user_id,$param)
    {
        $where = "business_userId=$businessId and delivery_date={$param['logistic_delivery_date']} and orderId={$param['orderId']}";
        if($param['wcc_id'] > 0){
            $where .= " and (wj_customer_coupon_id=0 or wj_customer_coupon_id={$param['wcc_id']})";
        }
        $data = Db::name('dispatching_behavior_log')
            ->alias('dbl')
            ->field('dbl.*,u.name,u.nickname')
            ->leftJoin('user u','u.id = dbl.userId')
            ->where($where)
            ->order('created_at asc')
            ->select()->toArray();
        foreach ($data as &$v){
            $v['behavior_type_desc'] = behaviorType($v['behavior_type']);
            $v['name'] = $v['nickname']?:$v['name'];
        }
        return $data;
    }
}
