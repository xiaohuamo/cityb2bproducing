<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\facade\Db;
use think\Model;

/**
 * @mixin \think\Model
 */
class ProducingProgressSummery extends Model
{
    use modelTrait;

    /**
     * 将订单商品数据加入生产流程表
     * @param $businessId 供应商id
     * @return array
     */
    public function addProgressSummary($data)
    {
        //1.查询该数据是否已存在，不存在新增，存在则更新
        $info = self::is_exist([
            ['business_userId', '=', $data['business_userId']],
            ['delivery_date', '=', $data['delivery_date']],
            ['product_id', '=', $data['product_id']],
            ['guige1_id', '=', $data['guige1_id']]
        ]);
        if ($info) {
            $res = self::getUpdate(['id' => $info['id']], $data);
        } else {
            $res = self::createData($data);
        }
        return $res;
    }

    /**
     * 获取加工产品信息(一级类目)
     * @param $businessId 供应商id
     * @param string $logistic_delivery_date  配送日期
     * @param string $logistic_driver_no 配送司机id
     * @return array
     */
    public function getGoodsOneCate($businessId,$userId,$logistic_delivery_date='',$logistic_driver_no='')
    {
        $where = [
            ['pps.business_userId', '=', $businessId],
        ];
        if($logistic_delivery_date){
            $where[] = ['pps.delivery_date','=',$logistic_delivery_date];
        }
        if($logistic_driver_no){
            $order_where = [
                ['business_userId', '=', $businessId],
                ['coupon_status', '=', 'c01']
            ];
            if($logistic_delivery_date){
                $order_where[] = ['logistic_delivery_date','=',$logistic_delivery_date];
            }
            if($logistic_driver_no){
                $order_where[] = ['logistic_driver_no','=',$logistic_driver_no];
            }
            //查询该司机运送的商品信息
            $order_id_arr = Db::name('order')->where($order_where)->column('orderId');
//            Db::name('wj_customer_coupon')->where([['order_id','in',$order_id_arr]])->column('');
        }
        $goods_one_cate = Db::name('producing_progress_summery')
            ->alias('pps')
            ->field('pps.product_id,pps.sum_quantities,pps.finish_quantities,pps.isDone,pps.operator_user_id,rm.menu_en_name,rm.unit_en')
            ->leftJoin('restaurant_menu rm','pps.product_id = rm.id')
            ->where($where)
            ->group('product_id')
            ->order('isDone asc')
            ->select()->toArray();
        foreach($goods_one_cate as &$v){
            $v['sum_quantities'] = floatval($v['sum_quantities']);
            $v['finish_quantities'] = floatval($v['finish_quantities']);
            //获取是否有二级分类
            $where = [
                ['pps.product_id', '=', $v['product_id']],
                ['pps.guige1_id', '>', 0],
            ];
            $count = Db::name('producing_progress_summery')->alias('pps')->where($where)->count();
            $v['is_has_two_cate'] = $count>0 ? 1 : 2;//1-有二级分类 2-没有二级分类
            //判断加工状态 -1-需要根据二级类目加工 0-未加工 1-自己正在加工 2-其他人正在加工 3-加工完成
            $v['status'] = $this->getProcessStatus($v,$userId,1);
        }
        return $goods_one_cate;
    }

    /**
     * 获取商品二级分类
     * @param $where
     * @return array|\think\Collection|Db[]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getGoodsTwoCate($businessId,$userId,$logistic_delivery_date='',$logistic_driver_no='',$product_id)
    {
        $where = [
            ['pps.business_userId', '=', $businessId],
            ['pps.product_id', '=', $product_id],
            ['pps.guige1_id', '>', 0],
        ];
        if($logistic_delivery_date){
            $where[] = ['pps.delivery_date','=',$logistic_delivery_date];
        }
        if($logistic_driver_no){
            $order_where = [
                ['business_userId', '=', $businessId],
                ['coupon_status', '=', 'c01']
            ];
            if($logistic_delivery_date){
                $order_where[] = ['logistic_delivery_date','=',$logistic_delivery_date];
            }
            if($logistic_driver_no){
                $order_where[] = ['logistic_driver_no','=',$logistic_driver_no];
            }
            //查询该司机运送的商品信息
            $order_id_arr = Db::name('order')->where($order_where)->column('orderId');
//            Db::name('wj_customer_coupon')->where([['order_id','in',$order_id_arr]])->column('');
        }
        $goods_two_cate = Db::name('producing_progress_summery')
            ->alias('pps')
            ->field('pps.product_id,pps.guige1_id,pps.sum_quantities,pps.finish_quantities,pps.isDone,pps.operator_user_id,rm.unit_en,rmo.menu_en_name guige_name')
            ->leftJoin('restaurant_menu rm','pps.product_id = rm.id')
            ->leftJoin('restaurant_menu_option rmo','pps.guige1_id = rmo.id')
            ->where($where)
            ->group('product_id,guige1_id')
            ->select()->toArray();
        foreach($goods_two_cate as &$v){
            //判断加工状态 -1-需要根据二级类目加工 0-未加工 1-自己正在加工 2-其他人正在加工 3-加工完成
            $v['sum_quantities'] = floatval($v['sum_quantities']);
            $v['finish_quantities'] = floatval($v['finish_quantities']);
            $v['status'] = $this->getProcessStatus($v,$userId,2);
        }
        return $goods_two_cate;
    }

    /**
     * 获取当前加工状态
     * @param $data 产品数据
     * @param $userId 当前用户id
     * @param int $type 1-一级类目 2-二级类目
     * @return int
     */
    public function getProcessStatus($data,$userId,$type=1)
    {
        $status = -1;//判断加工状态 -1-需要根据二级类目加工 0-未加工 1-自己正在加工 2-其他人正在加工 3-加工完成
        if($type==1 && $data['is_has_two_cate'] == 2 || $type==2){
            if($data['isDone'] == 0){
                if($data['operator_user_id'] > 0){
                    $status = $data['operator_user_id']==$userId ? 1 : 2;
                } else {
                    $status = 0;
                }
            }else{
                $status = 3;
            }
        } else {
            $status = -1;
        }
        return $status;
    }
}
