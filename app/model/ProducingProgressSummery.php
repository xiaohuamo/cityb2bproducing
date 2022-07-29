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
        $pps_info = ProducingProgressSummery::getOne([
            'business_userId'=>$data['business_userId'],
            'delivery_date'=>$data['delivery_date'],
            'product_id'=>$data['product_id'],
            'guige1_id'=>$data['guige1_id'],
            'isdeleted'=>0
        ]);
        if (!empty($pps_info)) {
            //1.判断数据是否有变动
            if($pps_info['sum_quantities'] != $data['sum_quantities'] || $pps_info['finish_quantities'] != $data['finish_quantities']){
                $update_data['sum_quantities'] = $data['sum_quantities'];
                $update_data['finish_quantities'] = $data['finish_quantities'];
                if($data['isDone']==1 && $update_data['finish_quantities'] < $update_data['sum_quantities']){
                    $update_data['isDone'] = 0;
                }
                if($update_data['finish_quantities'] >= $update_data['sum_quantities']){
                    $update_data['isDone'] = 1;
                }
                $res = self::getUpdate(['id' => $data['pps_id']], $update_data);
            }
        } else {
            $data['isDone'] = 0;
            $res = self::createData($data);
        }
        return $res;
    }

    /**
     * 获取加工产品信息(一级类目)
     * @param $businessId  供应商id
     * @param $userId  用户id
     * @param string $logistic_delivery_date  配送日期
     * @param string $logistic_truck_No 配送司机id
     * @param int $goods_sort 产品排序
     * @param string $category_id 分类id
     * @param int $sorce 调用接口来源 1-inidata 2-current Stock
     * @param int $logistic_schedule_id 调度id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getGoodsOneCate($businessId,$userId,$logistic_delivery_date,$logistic_truck_No='',$goods_sort=0,$category_id='',$source=1,$logistic_schedule_id=0)
    {
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
        if ($logistic_schedule_id>0) {
            $where = [
                ['o.business_userId', '=', $businessId],
                ['o.logistic_delivery_date','=',$logistic_delivery_date],
                ['o.logistic_schedule_id','=',$logistic_schedule_id],
                ['o.logistic_truck_no','=',$logistic_truck_No],
                ['wcc.customer_buying_quantity','>',0],
            ];
            $goods_one_cate = Db::name('wj_customer_coupon')
                ->alias('wcc')
                ->field('wcc.restaurant_menu_id product_id,sum(wcc.customer_buying_quantity) sum_quantities,IFNULL(sum(wcc_done.customer_buying_quantity),0.00) finish_quantities,IF(( sum( wcc.customer_buying_quantity )-sum( wcc_done.customer_buying_quantity )=0),1,0) isDone,pps.operator_user_id,rm.menu_en_name,rm.unit_en,rm.menu_id,rc.id cate_id,rc.category_en_name,pis.stock_qty')
                ->leftJoin('wj_customer_coupon wcc_done','wcc.id = wcc_done.id and wcc_done.is_producing_done = 1')
                ->leftJoin('order o','wcc.order_id = o.orderId')
                ->leftJoin('restaurant_menu rm','wcc.restaurant_menu_id = rm.id')
                ->leftJoin('restaurant_category rc','rm.restaurant_category_id = rc.id')
                ->leftJoin('producing_progress_summery pps',"pps.business_userId = $businessId and pps.delivery_date=$logistic_delivery_date and pps.product_id=wcc.restaurant_menu_id and pps.guige1_id=wcc.guige1_id and pps.isdeleted=0")
                ->leftJoin('producing_item_stock pis',"pis.item_id = pps.product_id and pis.spec_id = pps.guige1_id and pis.factory_id = $businessId")
                ->where($where)
                ->where(['rm.proucing_item'=>1])
                ->where($map)
                ->group('wcc.restaurant_menu_id,pps.product_id')
                ->order($order_by)
                ->select()->toArray();
        } else {
            $where = $this->getGoodsCondition($businessId,$logistic_delivery_date,$logistic_truck_No);
            $goods_one_cate = Db::name('producing_progress_summery')
                ->alias('pps')
                ->field('pps.product_id,sum(pps.sum_quantities) sum_quantities,sum(pps.finish_quantities) finish_quantities,pps.isDone,pps.operator_user_id,rm.menu_en_name,rm.unit_en,rm.menu_id,rc.id cate_id,rc.category_en_name,pis.stock_qty')
                ->leftJoin('restaurant_menu rm','pps.product_id = rm.id')
                ->leftJoin('restaurant_category rc','rm.restaurant_category_id = rc.id')
                ->leftJoin('producing_item_stock pis',"pis.item_id = pps.product_id and pis.spec_id = pps.guige1_id and pis.factory_id = $businessId")
                ->where($where)
                ->where(['rm.proucing_item'=>1])
                ->where($map)
                ->group('product_id')
                ->order($order_by)
                ->select()->toArray();
        }
        foreach($goods_one_cate as &$v){
            $v['sum_quantities'] = floatval($v['sum_quantities']);
            $v['finish_quantities'] = floatval($v['finish_quantities']);
            //获取是否有二级分类
            if ($logistic_schedule_id>0) {
                $map = [
                    ['wcc.restaurant_menu_id', '=', $v['product_id']],
                    ['wcc.guige1_id', '>', 0],
                ];
                $two_cate_done_info = Db::name('wj_customer_coupon')
                    ->alias('wcc')
                    ->field('IF(( sum( wcc.customer_buying_quantity )-sum( wcc_done.customer_buying_quantity )=0),1,0) isDone,pps.operator_user_id')
                    ->leftJoin('wj_customer_coupon wcc_done','wcc.id = wcc_done.id and wcc_done.is_producing_done = 1')
                    ->leftJoin('order o','wcc.order_id = o.orderId')
                    ->leftJoin('producing_progress_summery pps',"pps.business_userId = $businessId and pps.delivery_date=$logistic_delivery_date and pps.product_id=wcc.restaurant_menu_id and pps.guige1_id=wcc.guige1_id and pps.isdeleted=0")
                    ->where($where)
                    ->where($map)
                    ->group('wcc.restaurant_menu_id,pps.product_id,wcc.guige1_id')
                    ->select()->toArray();
            } else {
                $map = [
                    ['pps.product_id', '=', $v['product_id']],
                    ['pps.guige1_id', '>', 0],
                ];
                $two_cate_done_info = Db::name('producing_progress_summery')->alias('pps')->field('operator_user_id,isDone')->where($where)->where($map)->select()->toArray();
            }
            $v['is_has_two_cate'] = count($two_cate_done_info)>0 ? 1 : 2;//1-有二级分类 2-没有二级分类
            //判断加工状态 0-未加工 1-自己正在加工 2-其他人正在加工 3-加工完成
            $v['status'] = $this->getProcessStatus($v,$userId,1,$two_cate_done_info);
            $v['is_lock'] = $v['operator_user_id']>0&&$v['isDone']==0 ? 1 : 0;
            //获取该产品是否设置置顶
            $top_info = Db::name('restaurant_menu_top')->where(['userId'=>$userId,'business_userId'=>$businessId,'product_id'=>$v['product_id']])->find();
            $v['is_set_top'] = $top_info ? 1 : 0;//是否设置置顶 1设置 0未设置
            //如果查询的是current Plan的结果，获取该产品当前的所有操作员
            $v['operator_user'] = [];
            if($source == 2){
                $ou_where = [
                    ['pps.business_userId', '=', $businessId],
                    ['pps.delivery_date', '=', $logistic_delivery_date],
                    ['pps.product_id', '=', $v['product_id']],
                    ['pps.operator_user_id', '>', 0],
                    ['pps.isdeleted', '=', 0]
                ];
                $v['operator_user'] = Db::name('producing_progress_summery')
                    ->alias('pps')
                    ->field('operator_user_id,u.name,u.nickname,u.displayName')
                    ->leftJoin('user u','u.id = pps.operator_user_id')
                    ->where($ou_where)
                    ->group('operator_user_id')
                    ->select()->toArray();
                foreach ($v['operator_user'] as &$vv){
                    $vv['user_name'] = $vv['nickname'] ?: $vv['name'];
                }
            }
            //如果该产品没有二级分类，则展示该产品的库存,不展示用-1判断
            if($v['is_has_two_cate'] == 2){
                $v['left_stock'] = floatval($v['stock_qty']?:0);//剩余库存库存量 -1-不展示 >=0-剩余库存量
            }else{
                $v['left_stock'] = -1;
            }
        }
        return $goods_one_cate;
    }

    /**
     * 获取产品的二级类目完成情况
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function productTwoCateDoneInfo($businessId,$userId,$logistic_delivery_date,$logistic_truck_No='',$product_id_arr,$logistic_schedule_id=0)
    {
        //获取是否有二级分类
        if ($logistic_schedule_id>0) {
            $where = [
                ['o.business_userId', '=', $businessId],
                ['o.logistic_delivery_date','=',$logistic_delivery_date],
                ['o.logistic_schedule_id','=',$logistic_schedule_id],
                ['o.logistic_truck_no','=',$logistic_truck_No],
                ['wcc.customer_buying_quantity','>',0],
                ['wcc.restaurant_menu_id', 'in', $product_id_arr],
            ];
            $goods_one_cate = Db::name('wj_customer_coupon')
                ->alias('wcc')
//                ->field('wcc.restaurant_menu_id product_id,sum(wcc.customer_buying_quantity) sum_quantities,IFNULL(sum(wcc_done.customer_buying_quantity),0.00) finish_quantities,IF(( sum( wcc.customer_buying_quantity )-sum( wcc_done.customer_buying_quantity )=0),1,0) isDone,pps.operator_user_id')
                ->leftJoin('wj_customer_coupon wcc_done','wcc.id = wcc_done.id and wcc_done.is_producing_done = 1')
                ->leftJoin('order o','wcc.order_id = o.orderId')
                ->leftJoin('producing_progress_summery pps',"pps.business_userId = $businessId and pps.delivery_date=$logistic_delivery_date and pps.product_id=wcc.restaurant_menu_id and pps.guige1_id=wcc.guige1_id and pps.isdeleted=0")
                ->where($where)
                ->group('wcc.restaurant_menu_id,pps.product_id')
//                ->select()->toArray();
                ->column("wcc.restaurant_menu_id product_id,sum(wcc.customer_buying_quantity) sum_quantities,IFNULL(sum(wcc_done.customer_buying_quantity),0.00) finish_quantities,IF((sum(wcc.customer_buying_quantity)-sum(wcc_done.customer_buying_quantity)=0),(select 1),(select 0)) isDone,pps.operator_user_id","wcc.restaurant_menu_id");
        } else {
            $where = [
                ['business_userId', '=', $businessId],
                ['delivery_date','=',$logistic_delivery_date],
                ['product_id', 'in', $product_id_arr],
                ['isdeleted', '=', 0],
            ];
            $goods_one_cate = Db::name('producing_progress_summery')
                ->alias('pps')
//                ->field('pps.product_id,sum(pps.sum_quantities) sum_quantities,sum(pps.finish_quantities) finish_quantities,pps.isDone,pps.operator_user_id')
                ->where($where)
                ->group('product_id')
//                ->select()->toArray();
                ->column('pps.product_id,sum(pps.sum_quantities) sum_quantities,sum(pps.finish_quantities) finish_quantities,pps.isDone,pps.operator_user_id','pps.product_id');
        }
        foreach ($goods_one_cate as &$v){
            if ($logistic_schedule_id>0) {
                $map = [
                    ['wcc.restaurant_menu_id', '=', $v['product_id']],
                    ['wcc.guige1_id', '>', 0],
                ];
                $two_cate_done_info = Db::name('wj_customer_coupon')
                    ->alias('wcc')
                    ->field('IF(( sum( wcc.customer_buying_quantity )-sum( wcc_done.customer_buying_quantity )=0),1,0) isDone,pps.operator_user_id')
                    ->leftJoin('wj_customer_coupon wcc_done', 'wcc.id = wcc_done.id and wcc_done.is_producing_done = 1')
                    ->leftJoin('order o', 'wcc.order_id = o.orderId')
                    ->leftJoin('producing_progress_summery pps', "pps.business_userId = $businessId and pps.delivery_date=$logistic_delivery_date and pps.product_id=wcc.restaurant_menu_id and pps.guige1_id=wcc.guige1_id and pps.isdeleted=0")
                    ->where($where)
                    ->where($map)
                    ->group('wcc.restaurant_menu_id,pps.product_id,wcc.guige1_id')
                    ->select()->toArray();
            } else {
                $map = [
                    ['pps.product_id', '=', $v['product_id']],
                    ['pps.guige1_id', '>', 0],
                ];
                $two_cate_done_info = Db::name('producing_progress_summery')->alias('pps')->field('operator_user_id,isDone')->where($where)->where($map)->select()->toArray();
            }
            $v['is_has_two_cate'] = count($two_cate_done_info)>0 ? 1 : 2;//1-有二级分类 2-没有二级分类
            //判断加工状态 0-未加工 1-自己正在加工 2-其他人正在加工 3-加工完成
            $v['status'] = $this->getProcessStatus($v,$userId,1,$two_cate_done_info);
            //如果查询的是current Plan的结果，获取该产品当前的所有操作员
            $v['operator_user'] = [];
            $ou_where = [
                ['pps.business_userId', '=', $businessId],
                ['pps.delivery_date', '=', $logistic_delivery_date],
                ['pps.product_id', '=', $v['product_id']],
                ['pps.operator_user_id', '>', 0],
                ['pps.isdeleted', '=', 0]
            ];
            $v['operator_user'] = Db::name('producing_progress_summery')
                ->alias('pps')
                ->field('operator_user_id,u.name,u.nickname,u.displayName')
                ->leftJoin('user u','u.id = pps.operator_user_id')
                ->where($ou_where)
                ->group('operator_user_id')
                ->select()->toArray();
            foreach ($v['operator_user'] as &$vv){
                $vv['user_name'] = $vv['nickname'] ?: $vv['name'];
            }
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
    public function getGoodsTwoCate($businessId,$userId,$logistic_delivery_date,$logistic_truck_No='',$product_id,$logistic_schedule_id=0)
    {
        if ($logistic_schedule_id>0) {
            $where = [
                ['o.business_userId', '=', $businessId],
                ['o.logistic_delivery_date','=',$logistic_delivery_date],
                ['o.logistic_schedule_id','=',$logistic_schedule_id],
                ['o.logistic_truck_no','=',$logistic_truck_No],
                ['wcc.restaurant_menu_id','=',$product_id],
                ['wcc.guige1_id','>',0]
            ];
            $goods_two_cate = Db::name('wj_customer_coupon')
                ->alias('wcc')
                ->field('wcc.restaurant_menu_id product_id,wcc.guige1_id,sum(wcc.customer_buying_quantity) sum_quantities,IFNULL(sum(wcc_done.customer_buying_quantity),0.00) finish_quantities,IF(( sum( wcc.customer_buying_quantity )-sum( wcc_done.customer_buying_quantity )=0),1,0) isDone,pps.operator_user_id,rm.unit_en,rmo.menu_en_name guige_name,pis.stock_qty')
                ->leftJoin('wj_customer_coupon wcc_done','wcc.id = wcc_done.id and wcc_done.is_producing_done = 1')
                ->leftJoin('order o','wcc.order_id = o.orderId')
                ->leftJoin('restaurant_menu rm','wcc.restaurant_menu_id = rm.id')
                ->leftJoin('restaurant_menu_option rmo','wcc.guige1_id = rmo.id')
                ->leftJoin('producing_progress_summery pps',"pps.business_userId = $businessId and pps.delivery_date=$logistic_delivery_date and pps.product_id=wcc.restaurant_menu_id and pps.guige1_id = wcc.guige1_id and pps.isdeleted=0")
                ->leftJoin('producing_item_stock pis',"pis.item_id = pps.product_id and pis.spec_id = pps.guige1_id and pis.factory_id = $businessId")
                ->where($where)
                ->where(['rm.proucing_item'=>1])
                ->group('wcc.restaurant_menu_id,wcc.guige1_id,pps.product_id')
                ->select()->toArray();
        } else {
            $where = $this->getGoodsCondition($businessId,$logistic_delivery_date,$logistic_truck_No,$product_id);
            $goods_two_cate = Db::name('producing_progress_summery')
                ->alias('pps')
                ->field('pps.product_id,pps.guige1_id,pps.sum_quantities,pps.finish_quantities,pps.isDone,pps.operator_user_id,rm.unit_en,rmo.menu_en_name guige_name,pis.stock_qty')
                ->leftJoin('restaurant_menu rm','pps.product_id = rm.id')
                ->leftJoin('restaurant_menu_option rmo','pps.guige1_id = rmo.id')
                ->leftJoin('producing_item_stock pis',"pis.item_id = pps.product_id and pis.spec_id = pps.guige1_id and pis.factory_id = $businessId")
                ->where($where)
                ->where(['rm.proucing_item'=>1])
                ->group('product_id,guige1_id')
                ->select()->toArray();
        }
        foreach($goods_two_cate as &$v){
            //判断加工状态 0-未加工 1-自己正在加工 2-其他人正在加工 3-加工完成
            $v['sum_quantities'] = floatval($v['sum_quantities']);
            $v['finish_quantities'] = floatval($v['finish_quantities']);
            $v['status'] = $this->getProcessStatus($v,$userId,2);
            $v['is_lock'] = $v['operator_user_id']>0&&$v['isDone']==0 ? 1 : 0;
            $v['left_stock'] = floatval($v['stock_qty']?:0);
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
            ['pps.isdeleted', '=', 0]
        ];
        if($logistic_delivery_date){
            $where[] = ['pps.delivery_date','=',$logistic_delivery_date];
        }
        if($product_id){
            $where[] = ['pps.product_id','=',$product_id];
            $where[] = ['pps.guige1_id','>',0];
        }
//        if($logistic_truck_No){
//            $map = 'o.status=1 or o.accountPay=1';
//            $order_where = [
//                ['o.business_userId', '=', $businessId],
//                ['o.coupon_status', '=', 'c01']
//            ];
//            if($logistic_delivery_date){
//                $order_where[] = ['o.logistic_delivery_date','=',$logistic_delivery_date];
//            }
//            $order_where[] = ['o.logistic_truck_No','=',$logistic_truck_No];
//            //如果没有规格id,查询的是该司机所有的产品
//            if(empty($product_id)){
//                $product_id_arr = Db::name('wj_customer_coupon')
//                    ->alias('wcc')
//                    ->leftJoin('order o','o.orderId = wcc.order_id')
//                    ->where($order_where)
//                    ->where($map)
//                    ->group('restaurant_menu_id')->column('restaurant_menu_id');
//                $where[] = ['pps.product_id','in',$product_id_arr];
//            } else {
//                $order_where[] = ['wcc.restaurant_menu_id','=',$product_id];
//                $guige1_id_arr = Db::name('wj_customer_coupon')
//                    ->alias('wcc')
//                    ->leftJoin('order o','o.orderId = wcc.order_id')
//                    ->where($order_where)
//                    ->where($map)
//                    ->group('guige1_id')->column('guige1_id');
//                $where[] = ['pps.guige1_id','in',$guige1_id_arr];
//            }
//        }
        return $where;
    }

    /**
     * 获取当前加工状态
     * @param $data 产品数据
     * @param $userId 当前用户id
     * @param int $type 1-一级类目 2-二级类目 3-需要根据子类的完成情况判断状态
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
            if($type==1 && $data['is_has_two_cate'] == 1 || $type==3){
                //查询该产品所有的二级状态
                $two_cate_done_unique = array_unique(array_column($two_cate_done_info,'isDone'));
                $operator_user_id_arr = array_column($two_cate_done_info,'operator_user_id');
//                dump($two_cate_done_unique);
                if(count($two_cate_done_unique) == 1){
                    if($two_cate_done_unique[0] == 0||$two_cate_done_unique[0] == 2){
                        $status = $this->productStatusAccordGuige($userId,$operator_user_id_arr);
                    }else{
                        $status = 3;
                    }
                }else{
                    //判断未完成的规格中加工状态
                    $operator_user_id_arr = [];
                    foreach($two_cate_done_info as $v){
                        if($v['isDone'] == 0||$v['isDone'] == 2){
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
            //如果当前用户没加工该产品，判断所有规格是否都有人在加工，所有规格都被其他人加工，状态为：其他人加工中2。否则，状态为：待加工0
            if(in_array($userId,$operator_user_id_arr)){
                $status = 1;//表示正在加工中
            }else{
                if(in_array(0,$operator_user_id_arr)){
                    $status = 0;//表示待加工
                }else{
                    $status = 2;//其他人加工中
                }
            }
        }else{//所有规格都无人加工
            $status = 0;
        }
        return $status;
    }
}
