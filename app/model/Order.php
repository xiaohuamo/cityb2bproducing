<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\Model;
use think\facade\Db;

/**
 * @mixin \think\Model
 */
class Order extends Model
{
    use modelTrait;

    /**
     * 获取cc_order可以配送的日期
     */
    public function getDeliveryDate($businessId)
    {
        $sql_cc_order_avaliabe_date ='SELECT DISTINCT logistic_delivery_date  from cc_order left join cc_wj_customer_coupon b on orderId=b.order_id where cc_order.coupon_status="c01" and logistic_delivery_date >'.(time()-3600*24*7). ' and ';
        $sql_cc_order_avaliabe_date .= '  ( business_userId = '.$businessId ;
        $sql_cc_order_avaliabe_date .= '  or b.business_id = '.$businessId ;
        $sql_cc_order_avaliabe_date .='  	or b.business_id in (select customer_id  from cc_factory2c_list where factroy_id ='.$businessId.')';
        $sql_cc_order_avaliabe_date .='  	or b.business_id in (select customer_id  from cc_factory_2blist where factroy_id ='.$businessId.'))';
        $sql_cc_order_import_avaliabe_date ='SELECT DISTINCT logistic_delivery_date  from cc_order_import where logistic_delivery_date >'.(time()-3600*24*7). ' and ( business_userId = '.$businessId.' or business_userId in (select business_id  from cc_dispatching_centre_customer_list where dispatching_centre_id ='.$businessId.'))';
        $sql_union = 'select DISTINCT  logistic_delivery_date from (select * from( ('. $sql_cc_order_avaliabe_date.') union ('.$sql_cc_order_import_avaliabe_date.')) as d ) as c';
        return $sql_union;
        $res = Db::query($sql_union);
        Db::name('order')->where([
            ['business_userId', '=', $businessId],
            ['coupon_status', '=', 'c01'],
            ['']
        ])->group('logistic_delivery_date')->cloumn('logistic_delivery_date');
        return $res;
    }
}
