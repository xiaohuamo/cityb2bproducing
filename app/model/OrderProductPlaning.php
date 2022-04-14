<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\facade\Db;
use think\Model;
use app\model\UserFactory;

/**
 * @mixin \think\Model
 */
class OrderProductPlaning extends Model
{
    use modelTrait;

    /**
     * 获取订单数（已加工订单数/总订单数）
     * @param $businessId  供应商id
     * @param string $logistic_delivery_date 配送日期
     * @param string $user_id 操作员用户id
     * @return array
     */
    public function getOrderCount($businessId,$logistic_delivery_date,$opeator_user_id='')
    {
        if($opeator_user_id){
            $op_where = [
                ['business_userId', '=', $businessId],
                ['delivery_date', '=', $logistic_delivery_date],
                ['operator_user_id', '=', $opeator_user_id],
                ['isdeleted', '=', 0]
            ];
            $order_count = Db::name('producing_planing_progress_summery')->where($op_where)->count();
            $op_where[] = ['isDone','=',1];
            $order_done_count = Db::name('producing_planing_progress_summery')->where($op_where)->count();
        } else {
            $where = [
                ['opp.business_userId', '=', $businessId],
                ['opp.logistic_delivery_date','=',$logistic_delivery_date],
                ['opp.coupon_status', '=', 'c01'],
                ['rm.proucing_item', '=', 1],
                ['oppd.coupon_status', '=', 'c01']
            ];
            //获取需要加工的订单总数
            $order_count = Db::name('order_product_planning_details')
                ->alias('oppd')
                ->leftJoin('order_product_planing opp','oppd.order_id = opp.orderId')
                ->leftJoin('restaurant_menu rm','rm.id = oppd.restaurant_menu_id')
                ->where($where)
                ->group('oppd.order_id')
                ->count();
            //获取已加工的订单总数
            $where[] = ['oppd.is_producing_done','=',1];
            $order_done_count = Db::name('order_product_planning_details')
                ->alias('oppd')
                ->leftJoin('order_product_planing opp','oppd.order_id = opp.orderId')
                ->leftJoin('restaurant_menu rm','rm.id = oppd.restaurant_menu_id')
                ->where($where)
                ->group('oppd.order_id')
                ->count();
        }
        return [
            'order_done_count' => $order_done_count,
            'order_count' => $order_count
        ];
    }

    /**
     * 获取产品加工订单
     * @param $businessId  供应商id
     * @param string $logistic_delivery_date 配送日期
     * @param string $opeator_user_id 操作员用户id
     * @return array
     */
    public function getProductOrderList($businessId,$user_id,$logistic_delivery_date='',$opeator_user_id='',$product_id='',$guige1_id='',$wcc_sort=0,$wcc_sort_type=1)
    {
        $where = [
            ['opp.business_userId', '=', $businessId],
            ['opp.coupon_status', '=', 'c01'],
            ['rm.proucing_item', '=', 1],
            ['oppd.coupon_status', '=', 'c01']
        ];
        if ($logistic_delivery_date) {
            $where[] = ['opp.logistic_delivery_date', '=', $logistic_delivery_date];
        }
        //目前操作员和产品规格锁定，所以订单查询只需按照日期，产品规格查询即可
//        if ($opeator_user_id) {
//            $where[] = ['oppd.operator_user_id','=',$opeator_user_id];
//        }
        if ($product_id > 0) {
            $where[] = ['oppd.restaurant_menu_id','=',$product_id];
        }
        if ($guige1_id > 0) {
            $where[] = ['oppd.guige1_id','=',$guige1_id];
        }
        switch ($wcc_sort){
            case 1:
                if($wcc_sort_type == 1){
                    $order_by = 'is_producing_done asc,id asc';
                }else{
                    $order_by = 'is_producing_done desc,id asc';
                }
                break;
            case 2:
                if($wcc_sort_type == 1) {
                    $order_by = 'is_producing_done desc,id asc';
                }else{
                    $order_by = 'is_producing_done asc,id asc';
                }
                break;
            default:
                if($wcc_sort_type == 1) {
                    $order_by = 'id asc,is_producing_done asc';
                }else{
                    $order_by = 'id desc,is_producing_done asc';
                }
        }
        //查询用户数剧是否存在
        $user_info = UserFactory::getOne(['factory_id'=>$businessId,'user_id'=>$user_id]);
        $nickname = User::getVal(['id'=>$user_id],'nickname');
        if(empty($user_info)){
            UserFactory::createData([
                'factory_id' => $businessId,
                'user_id' => $user_id,
                'nickname' => $nickname,
                'factory_sales_id' => $businessId,
                'xero_name' => '',
            ]);
        }
        //获取加工明细单数据
        $order = Db::name('order_product_planning_details')
            ->alias('oppd')
            ->field('oppd.id,oppd.restaurant_menu_id product_id,oppd.guige1_id,opp.orderId,opp.logistic_delivery_date,opp.logistic_sequence_No,uf.nickname,oppd.customer_buying_quantity,oppd.new_customer_buying_quantity,oppd.is_producing_done,1 as num1,pps.operator_user_id,pps.isDone,rm.unit_en')
            ->leftJoin('restaurant_menu rm','rm.id = oppd.restaurant_menu_id')
            ->leftJoin('order_product_planing opp','oppd.order_id = opp.orderId')
            ->leftJoin('user_factory uf','uf.user_id = opp.userId')
            ->leftJoin('producing_planing_progress_summery pps',"pps.delivery_date = opp.logistic_delivery_date and pps.business_userId=$businessId and pps.product_id=oppd.restaurant_menu_id and pps.guige1_id=oppd.guige1_id and pps.isdeleted=0")
            ->where($where)
            ->order($order_by)
            ->select()->toArray();
        foreach($order as &$v){
            $v['nickname'] = $v['nickname']?:$nickname;
            $v['new_customer_buying_quantity'] = $v['new_customer_buying_quantity']>=0?$v['new_customer_buying_quantity']:$v['customer_buying_quantity'];
            //判断当前加工明细是否被锁定
            $v['is_lock'] = 0;
            $v['lock_type'] = 0;
            if($v['operator_user_id'] > 0){
                if($v['isDone'] == 0){
                    $v['is_lock'] = 1;//是否被锁定，1锁定 2未锁定
                    $v['lock_type'] = $user_id == $v['operator_user_id']?1:2;//1-被自己锁定 2-被他人锁定
                }
            }
        }
        return $order;
    }
}
