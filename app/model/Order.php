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
        $date_arr = Db::name('order')->where([
            ['business_userId', '=', $businessId],
            ['coupon_status', '=', 'c01'],
            ['logistic_delivery_date','>',time()-3600*24*7]
        ])->group('logistic_delivery_date')->fetchSql(true)->column('logistic_delivery_date');
        return $date_arr;
    }
}
