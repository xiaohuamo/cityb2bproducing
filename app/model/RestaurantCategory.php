<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\Model;
use think\facade\Db;

/**
 * @mixin \think\Model
 */
class RestaurantCategory extends Model
{
    use modelTrait;

    /**
     * 获取大类
     * @param $businessId
     */
    public function getCategory($businessId)
    {
        $where = "restaurant_id=$businessId and (parent_category_id is null or parent_category_id=0) and length(category_en_name)>0 and isdeleted=0 and isHide=0";
        $category = Db::name('restaurant_category')->field('id,category_en_name')->where($where)->order('category_sort_id asc')->select();
        return $category;
    }

    /**
     * @param $businessId
     * @param string $logistic_delivery_date
     * @param string $logistic_truck_No
     */
    public function getOrderCategory($businessId,$logistic_delivery_date='',$logistic_truck_No=''){
        $map = "(o.status=1 or o.accountPay=1) and (o.coupon_status='c01' or o.coupon_status='b01')";
        $where = [
            ['o.business_userId', '=', $businessId],
            ['wcc.customer_buying_quantity','>',0],
        ];
        if(!empty($logistic_delivery_date)){
            $where[] = ['o.logistic_delivery_date','=',$logistic_delivery_date];
        }
        if(!empty($logistic_truck_No)){
            $where[] = ['o.logistic_truck_No','=',$logistic_truck_No];
        }
        $category = Db::name('wj_customer_coupon')
            ->alias('wcc')
            ->field('rc.id,rc.category_en_name')
            ->leftJoin('order o','o.orderId = wcc.order_id')
            ->leftJoin('restaurant_menu rm','wcc.restaurant_menu_id = rm.id')
            ->leftJoin('restaurant_category rc','rm.restaurant_category_id = rc.id')
            ->where($map)
            ->where($where)
            ->group('rm.restaurant_category_id')
            ->order('rc.category_sort_id asc')
            ->select();
        return $category;
    }
}
