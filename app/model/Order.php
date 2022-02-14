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
        ])->field("logistic_delivery_date,FROM_UNIXTIME(logistic_delivery_date,'%Y-%m-%d') date,2 as is_default")->group('logistic_delivery_date')->order('logistic_delivery_date asc')->select()->toArray();
        //获取默认显示日期,距离今天最近的日期，将日期分为3组，今天之前，今天，今天之后距离今天最近的日期的key值
        $today_time = strtotime(date('Y-m-d',time()));
        $today_before_k = $today_k = $today_after_k = [];
        foreach($date_arr as $k=>$v){
            if($v['logistic_delivery_date']-$today_time <= 0){
                $today_before_k = $k;
            }
            if($v['logistic_delivery_date']-$today_time == 0){
                $today_k = $k;
            }
            if($v['logistic_delivery_date']-$today_time > 0){
                $today_after_k = $k;
                break;
            }
            $date_arr[$k]['date_day'] = date_day($v['logistic_delivery_date'],$today_time);
        }
        if($today_k){
            $date_arr[$today_k]['is_default'] = 1;
            $default = $date_arr[$today_k];
        }elseif($today_after_k){
            $date_arr[$today_after_k]['is_default'] = 1;
            $default = $date_arr[$today_after_k];
        }else{
            $date_arr[$today_before_k]['is_default'] = 1;
            $default = $date_arr[$today_before_k];
        }
        return ['list' => $date_arr,'default' => $default];
    }
}
