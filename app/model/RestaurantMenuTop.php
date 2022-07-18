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
    public function getTopProduct($businessId, $userId, $logistic_delivery_date, $logistic_truck_No='',$logistic_schedule_id=0)
    {
        //获取置顶产品id
        $product_top = Db::name('restaurant_menu_top')
            ->alias('rmt')
            ->field('rmt.product_id,rm.menu_en_name,rm.unit_en,rm.menu_id')
            ->leftJoin('restaurant_menu rm','rmt.product_id = rm.id')
            ->where(['rmt.userId'=>$userId,'rmt.business_userId'=>$businessId])
            ->order('rm.menu_order_id asc')
            ->select()->toArray();
        $product_id_arr = array_column($product_top,'product_id');
        if($product_id_arr){
            //1.查询当前置顶的产品对应筛选条件下的加工状态
            $ProducingProgressSummery = new ProducingProgressSummery();
            $where = $ProducingProgressSummery->getGoodsCondition($businessId,$logistic_delivery_date,$logistic_truck_No);
            $where[] = ['pps.product_id','in',$product_id_arr];
            $product_top_progress = Db::name('producing_progress_summery')
                ->alias('pps')
                ->where($where)
                ->group('product_id')
                ->column('pps.product_id,pps.isDone,pps.operator_user_id','pps.product_id');
            $product_top_progress_id_arr = array_keys($product_top_progress);
            foreach($product_top_progress as &$v){
                $v['is_progress'] = 1;//判断当前筛选条件下是否是需要加工产品，若没加工需要置灰
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
            foreach($product_top as &$v){
                if(in_array($v['product_id'],$product_top_progress_id_arr)){
                    $v['is_progress'] = 1;//判断当前筛选条件下是否是需要加工产品，若没加工需要置灰 1-有 2-没有
                    $v = array_merge($v,$product_top_progress[$v['product_id']]);
                }else{
                    $v['is_progress'] = 2;
                }
            }
        }
        return $product_top;
    }
}
