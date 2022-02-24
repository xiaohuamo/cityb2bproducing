<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\facade\Db;
use think\Model;

/**
 * @mixin \think\Model
 */
class RestaurantMenuTop extends Model
{
    use modelTrait;

    /**
     * 获取置顶信息
     * @param $businessId 供应商id
     * @param string $logistic_delivery_date  配送日期
     * @param string $logistic_truck_No 配送司机id
     * @return array
     */
    public function getTopProduct($businessId, $userId, $logistic_delivery_date, $logistic_truck_No='')
    {
        //获取置顶产品id
        $product_id_arr = Db::name('restaurant_menu_top')->where(['userId'=>$userId,'business_userId'=>$businessId])->column('product_id');
        $product_top = [];
        if($product_id_arr){
            $ProducingProgressSummery = new ProducingProgressSummery();
            $where = $ProducingProgressSummery->getGoodsCondition($businessId,$logistic_delivery_date,$logistic_truck_No);
            $where[] = ['pps.product_id','in',$product_id_arr];
            $order_by = 'isDone asc,rc.category_sort_id asc,rm.menu_order_id asc';
            $product_top = Db::name('producing_progress_summery')
                ->alias('pps')
                ->field('pps.product_id,pps.sum_quantities,pps.finish_quantities,pps.isDone,pps.operator_user_id,rm.menu_en_name,rm.unit_en,rm.menu_id')
                ->leftJoin('restaurant_menu rm','pps.product_id = rm.id')
                ->leftJoin('restaurant_category rc','rm.restaurant_category_id = rc.id')
                ->where($where)
                ->group('product_id')
                ->order($order_by)
                ->select()->toArray();
            foreach($product_top as &$v){
                $v['sum_quantities'] = floatval($v['sum_quantities']);
                $v['finish_quantities'] = floatval($v['finish_quantities']);
                //获取是否有二级分类
                $map = [
                    ['pps.product_id', '=', $v['product_id']],
                    ['pps.guige1_id', '>', 0],
                ];
                $two_cate_done = Db::name('producing_progress_summery')->alias('pps')->where($where)->where($map)->column('isDone');
                $v['is_has_two_cate'] = count($two_cate_done)>0 ? 1 : 2;//1-有二级分类 2-没有二级分类
                //判断加工状态 -1-需要根据二级类目加工 0-未加工 1-自己正在加工 2-其他人正在加工 3-加工完成
                $v['status'] = $ProducingProgressSummery->getProcessStatus($v,$userId,1,$two_cate_done);
                $v['is_lock'] = $v['operator_user_id']>0&&$v['isDone']==0 ? 1 : 0;
            }
        }
        return $product_top;
    }
}
