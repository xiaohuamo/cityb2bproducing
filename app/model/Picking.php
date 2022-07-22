<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\facade\Db;
use think\Model;

/**
 * @mixin \think\Model
 */
class Picking extends Model
{
    use modelTrait;

    /**
     * 获取订单信息
     * @param $orderId  订单id
     * @return array
     */
    public function getOrderInfo($orderId)
    {
        $where = [
            ['p.orderId', '=', $orderId],
        ];
        //获取加工明细单数据
        $order = Db::name('picking')
            ->alias('p')
            ->field('p.id,p.orderId,p.business_userId,p.coupon_status,p.displayName,p.first_name,p.last_name,p.address,p.receipt_picture,p.phone,p.userId,uf.user_id,uf.factory_id,uf.nickname,uf.pic')
            ->leftJoin('user_factory uf','uf.user_id = p.userId and factory_id=p.business_userId')
            ->leftJoin('user u','u.id = p.business_userId')
            ->where($where)
            ->find();
        if($order){
            $Order = new Order();
            $name = $Order->getCustomerName($order);
            $order['name'] = $name;
        }
        return $order;
    }
}
