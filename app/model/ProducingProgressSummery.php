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
     * @param string $logistic_truck_No 配送司机id
     * @return array
     */
    public function getGoodsOneCate($businessId,$userId,$logistic_delivery_date='',$logistic_truck_No='',$goods_sort=0,$category_id='')
    {
        $where = $this->getGoodsCondition($businessId,$logistic_delivery_date,$logistic_truck_No);
        $map = [];
        if($category_id){
            $map[] = ['rm.restaurant_category_id','=',$category_id];
        }
        switch($goods_sort){
            case 1:
                $order_by = 'isDone desc,rc.category_sort_id asc,rm.menu_order_id asc';
                break;
            case 2:
                $order_by = 'rm.menu_id asc';
                break;
            default:$order_by = 'isDone asc,rc.category_sort_id asc,rm.menu_order_id asc';
        }
        $goods_one_cate = Db::name('producing_progress_summery')
            ->alias('pps')
            ->field('pps.product_id,pps.sum_quantities,pps.finish_quantities,pps.isDone,pps.operator_user_id,rm.menu_en_name,rm.unit_en,rm.menu_id')
            ->leftJoin('restaurant_menu rm','pps.product_id = rm.id')
            ->leftJoin('restaurant_category rc','rm.restaurant_category_id = rc.id')
            ->where($where)
            ->where(['rm.proucing_item'=>1])
            ->where($map)
            ->group('product_id')
            ->order($order_by)
            ->select()->toArray();
        foreach($goods_one_cate as &$v){
            $v['sum_quantities'] = floatval($v['sum_quantities']);
            $v['finish_quantities'] = floatval($v['finish_quantities']);
            //获取是否有二级分类
            $map = [
                ['pps.product_id', '=', $v['product_id']],
                ['pps.guige1_id', '>', 0],
            ];
            $two_cate_done_info = Db::name('producing_progress_summery')->alias('pps')->field('operator_user_id,isDone')->where($where)->where($map)->select()->toArray();
            $v['is_has_two_cate'] = count($two_cate_done_info)>0 ? 1 : 2;//1-有二级分类 2-没有二级分类
            //判断加工状态 0-未加工 1-自己正在加工 2-其他人正在加工 3-加工完成
            $v['status'] = $this->getProcessStatus($v,$userId,1,$two_cate_done_info);
            $v['is_lock'] = $v['operator_user_id']>0&&$v['isDone']==0 ? 1 : 0;
            //获取该产品是否设置置顶
            $top_info = Db::name('restaurant_menu_top')->where(['userId'=>$userId,'business_userId'=>$businessId,'product_id'=>$v['product_id']])->find();
            $v['is_set_top'] = $top_info ? 1 : 0;//是否设置置顶 1设置 0未设置
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
    public function getGoodsTwoCate($businessId,$userId,$logistic_delivery_date='',$logistic_truck_No='',$product_id)
    {
        $where = $this->getGoodsCondition($businessId,$logistic_delivery_date,$logistic_truck_No,$product_id);
        $goods_two_cate = Db::name('producing_progress_summery')
            ->alias('pps')
            ->field('pps.product_id,pps.guige1_id,pps.sum_quantities,pps.finish_quantities,pps.isDone,pps.operator_user_id,rm.unit_en,rmo.menu_en_name guige_name')
            ->leftJoin('restaurant_menu rm','pps.product_id = rm.id')
            ->leftJoin('restaurant_menu_option rmo','pps.guige1_id = rmo.id')
            ->where($where)
            ->where(['rm.proucing_item'=>1])
            ->group('product_id,guige1_id')
            ->select()->toArray();
        foreach($goods_two_cate as &$v){
            //判断加工状态 0-未加工 1-自己正在加工 2-其他人正在加工 3-加工完成
            $v['sum_quantities'] = floatval($v['sum_quantities']);
            $v['finish_quantities'] = floatval($v['finish_quantities']);
            $v['status'] = $this->getProcessStatus($v,$userId,2);
            $v['is_lock'] = $v['operator_user_id']>0&&$v['isDone']==0 ? 1 : 0;
        }
        return $goods_two_cate;
    }

    /**
     * 查询条件判断
     * @param $businessId
     * @param $userId
     * @param string $logistic_delivery_date
     * @param string $logistic_truck_No
     * @param string $product_id
     */
    public function getGoodsCondition($businessId,$logistic_delivery_date='',$logistic_truck_No='',$product_id='')
    {
        $where = [
            ['pps.business_userId', '=', $businessId],
        ];
        if($logistic_delivery_date){
            $where[] = ['pps.delivery_date','=',$logistic_delivery_date];
        }
        if($product_id){
            $where[] = ['pps.product_id','=',$product_id];
            $where[] = ['pps.guige1_id','>',0];
        }
        if($logistic_truck_No){
            $map = 'o.status=1 or o.accountPay=1';
            $order_where = [
                ['o.business_userId', '=', $businessId],
                ['o.coupon_status', '=', 'c01']
            ];
            if($logistic_delivery_date){
                $order_where[] = ['o.logistic_delivery_date','=',$logistic_delivery_date];
            }
            $order_where[] = ['o.logistic_truck_No','=',$logistic_truck_No];
            //如果没有规格id,查询的是该司机所有的产品
            if(empty($product_id)){
                $product_id_arr = Db::name('wj_customer_coupon')
                    ->alias('wcc')
                    ->leftJoin('order o','o.orderId = wcc.order_id')
                    ->where($order_where)
                    ->where($map)
                    ->group('restaurant_menu_id')->column('restaurant_menu_id');
                $where[] = ['pps.product_id','in',$product_id_arr];
            } else {
                $order_where[] = ['wcc.restaurant_menu_id','=',$product_id];
                $guige1_id_arr = Db::name('wj_customer_coupon')
                    ->alias('wcc')
                    ->leftJoin('order o','o.orderId = wcc.order_id')
                    ->where($order_where)
                    ->where($map)
                    ->group('guige1_id')->column('guige1_id');
                $where[] = ['pps.guige1_id','in',$guige1_id_arr];
            }
        }
        return $where;
    }

    /**
     * 获取当前加工状态
     * @param $data 产品数据
     * @param $userId 当前用户id
     * @param int $type 1-一级类目 2-二级类目
     * @param array $two_cate_done_info 二级类目完成情况
     * @return int
     */
    public function getProcessStatus($data,$userId,$type=1,$two_cate_done_info=[])
    {
        //判断加工状态 0-未加工 1-自己正在加工 2-其他人正在加工 3-加工完成
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
            //如果一级分类中，有二级分类，需要根据二级分类中的所有来判断一级的状态
            if($type==1 && $data['is_has_two_cate'] == 1){
                //查询该产品所有的二级状态
                $two_cate_done_unique = array_unique(array_column($two_cate_done_info,'isDone'));
                $operator_user_id_arr = array_column($two_cate_done_info,'operator_user_id');
//                dump($two_cate_done_unique);
                if(count($two_cate_done_unique) == 1){
                    if($two_cate_done_unique[0] == 0){
                        $status = $this->productStatusAccordGuige($userId,$operator_user_id_arr);
                    }else{
                        $status = 3;
                    }
                }else{
                    //判断未完成的规格中加工状态
                    $operator_user_id_arr = [];
                    foreach($two_cate_done_info as $v){
                        if($v['isDone'] == 0){
                            $operator_user_id_arr[] = $v['operator_user_id'];
                        }
                    }
                    $status = $this->productStatusAccordGuige($userId,$operator_user_id_arr);
                }
            }
        }
        return $status;
    }

    /**
     * 根据规格加工状态，判断当前产品显示的状态
     * @param $user_id
     * @param $operator_user_id_arr
     */
    public function productStatusAccordGuige($userId,$operator_user_id_arr)
    {
        if(count($operator_user_id_arr) > 0){//有人正在操作
            //如果当前用户正在加工该产品，状态为：正在加工中2
            //如果当前用户没加工该产品，判断所有规格是否都有人在加工，所有规格都被其他人加工，状态为：其他人加工中3。否则，状态为：待加工0
            if(in_array($userId,$operator_user_id_arr)){
                $status = 1;//表示正在加工中
            }else{
                if(in_array(0,$operator_user_id_arr)){
                    $status = 0;//表示待加工
                }else{
                    $status = 3;//其他人加工中
                }
            }
        }else{//所有规格都无人加工
            $status = 0;
        }
        return $status;
    }
}
