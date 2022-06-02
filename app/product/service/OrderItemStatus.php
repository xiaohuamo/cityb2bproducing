<?php


namespace app\product\service;

use think\facade\Db;
use think\Model;
use app\model\{
    Order,
    WjCustomerCoupon,
    ProducingBehaviorLog,
    DispatchingBehaviorLog,
    ProducingProgressSummery,
    DispatchingProgressSummery,
    DispatchingItemBehaviorLog
};

class OrderItemStatus
{
    /**
     * 生产端订单明细状态=5的（库存分配完成后）变更为=1（已完成）
     * @param $businessId
     * @param $user_id
     * @param $param
     * @return array|\think\response\Json
     */
    public function ProducingOrderItemStatusChange($businessId,$user_id,$param)
    {
        try{
            Db::startTrans();

            //1.获取加工明细信息
            $WjCustomerCoupon = new WjCustomerCoupon();
            $wcc_info = $WjCustomerCoupon->getWccInfo($param['id'],$businessId);
            if (!$wcc_info) {
                return show_arr(config('status.code')['order_error']['code'], config('status.code')['order_error']['msg']);
            }
            if($wcc_info['is_producing_done'] == $param['is_producing_done']){
                return show_arr(config('status.code')['summary_done_error']['code'], config('status.code')['summary_done_error']['msg']);
            }
            //1.查询该产品是否在汇总表中
            $pps_info = ProducingProgressSummery::getOne([
                ['business_userId','=',$businessId],
                ['delivery_date','=',$wcc_info['logistic_delivery_date']],
                ['product_id','=',$wcc_info['product_id']],
                ['guige1_id','=',$wcc_info['guige1_id']],
                ['isdeleted','=',0],
            ],'id,finish_quantities,sum_quantities,operator_user_id,isDone');
            if(!$pps_info){
                return show_arr(config('status.code')['summary_error']['code'], config('status.code')['summary_error']['msg']);
            }
            //一.已处理和流程
            //1-2.该产品已处理完成，不可重复处理
//            if($pps_info['isDone'] == $param['is_producing_done']){
//                return show_arr(config('status.code')['repeat_done_error']['code'], config('status.code')['repeat_done_error']['msg']);
//            }
            //2.更新该产品加工数量和状态
            if($param['is_producing_done'] == 1){
                if($wcc_info['is_producing_done'] != 5){
                    return show_arr(config('status.code')['param_error']['code'],config('status.code')['param_error']['msg']);
                }
                WjCustomerCoupon::getUpdate(['id' => $wcc_info['id']],['is_producing_done'=>$param['is_producing_done']]);
                $finish_quantities = $pps_info['finish_quantities']+$wcc_info['customer_buying_quantity'];
                $pps_data['finish_quantities'] = $finish_quantities;
                if ($finish_quantities == $pps_info['sum_quantities']) {
                    $pps_data['isDone'] = 1;
                    if(empty($pps_data['operator_user_id'])){
                        $pps_data['operator_user_id'] = $wcc_info['operator_user_id'];
                    }
                }
                ProducingProgressSummery::getUpdate(['id' => $pps_info['id']],$pps_data);
                //3.判断该订单是否全部加工完毕
                //如果该产品对应规则的产品全部加工完毕，则更改订单加工状态
                $count = $WjCustomerCoupon->getWccOrderDone($wcc_info['order_id']);
                if($count == 0){
                    Order::getUpdate(['orderId' => $wcc_info['order_id']],[
                        'is_producing_done'=>1
                    ]);
                }
                Db::commit();
                $ProducingBehaviorLog = new ProducingBehaviorLog();
                $log_data = [
                    "product_id" => $wcc_info['product_id'],
                    "guige1_id" => $wcc_info['guige1_id'],
                    "wj_customer_coupon_id" => $param['id']
                ];
                $ProducingBehaviorLog->addProducingBehaviorLog($user_id,$businessId,10,$wcc_info['logistic_delivery_date'],$log_data);
                return show_arr(config('status.code')['success']['code'],config('status.code')['success']['msg']);
            }else{
                return show_arr(config('status.code')['param_error']['code'],config('status.code')['param_error']['msg']);
            }
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return show_arr(config('status.code')['system_error']['code'], $e->getMessage());
        }
    }

    /**
     * 拣货端状态变更
     * @param $businessId
     * @param $user_id
     * @param $param
     */
    public function PickingOrderItemStatusChange($businessId,$user_id,$param)
    {
        try{
            Db::startTrans();

            $WjCustomerCoupon = new WjCustomerCoupon();
            $DispatchingBehaviorLog = new DispatchingBehaviorLog();
            $DispatchingItemBehaviorLog = new DispatchingItemBehaviorLog();
            //1.获取加工明细信息
            $wcc_info = $WjCustomerCoupon->getWccInfo($param['id'],$businessId);
            if (!$wcc_info) {
                return show_arr(config('status.code')['order_error']['code'], config('status.code')['order_error']['msg']);
            }
            if($wcc_info['dispatching_is_producing_done'] == $param['is_producing_done']){
                return show_arr(config('status.code')['summary_done_error']['code'], config('status.code')['summary_done_error']['msg']);
            }
            //1.查询该产品是否在汇总表中
            $dps_info = DispatchingProgressSummery::getOne([
                ['business_id','=',$businessId],
                ['delivery_date','=',$wcc_info['logistic_delivery_date']],
                ['orderId','=',$wcc_info['order_id']],
                ['isdeleted','=',0],
            ],'id,finish_quantities,sum_quantities,operator_user_id,isDone');
            if(!$dps_info){
                return show_arr(config('status.code')['summary_error']['code'], config('status.code')['summary_error']['msg']);
            }
            //一.已处理流程
            if($param['is_producing_done'] == 1) {
                if($wcc_info['dispatching_is_producing_done'] != 5){
                    return show_arr(config('status.code')['param_error']['code'],config('status.code')['param_error']['msg']);
                }
                //2.更新该产品加工数量和状态
                WjCustomerCoupon::getUpdate(['id' => $wcc_info['id']], ['operator_user_id' => $user_id, 'dispatching_is_producing_done' => $param['is_producing_done']]);
                $finish_quantities = $dps_info['finish_quantities'] + 1;
                $dps_data['finish_quantities'] = $finish_quantities;
                if ($finish_quantities == $dps_info['sum_quantities']) {
                    $dps_data['isDone'] = 1;
                }
                DispatchingProgressSummery::getUpdate(['id' => $dps_info['id']], $dps_data);
                //3.判断该订单是否全部加工完毕
                //如果该订单所有明细是否产全部拣货完毕，则更改订单状态
                $count = $WjCustomerCoupon->getWccDispatchingOrderDone($wcc_info['order_id']);
                if ($count == 0) {
                    Order::getUpdate(['orderId' => $wcc_info['order_id']], [
                        'dispatching_is_producing_done' => 1
                    ]);
                }
                Db::commit();
                $log_data = [
                    "orderId" => $wcc_info['order_id'],
                    "wj_customer_coupon_id" => $param['id']
                ];
                $DispatchingBehaviorLog->addBehaviorLog($user_id, $businessId, 11, $wcc_info['logistic_delivery_date'], $log_data);
                $log_data = [
                    "product_id" => $wcc_info['product_id'],
                    "guige1_id" => $wcc_info['guige1_id'],
                    "wj_customer_coupon_id" => $param['id']
                ];
                $DispatchingItemBehaviorLog->addProducingBehaviorLog($user_id, $businessId, 11, $wcc_info['logistic_delivery_date'], $log_data);
                return show_arr(config('status.code')['success']['code'], config('status.code')['success']['msg']);
            }elseif($param['is_producing_done'] == 0){
                //2.更新该产品加工数量和状态
                $wcc_update_data = [
                    'dispatching_operator_user_id'=>0,
                    'dispatching_item_operator_user_id'=>0,
                    'dispatching_is_producing_done'=>0
                ];
                WjCustomerCoupon::getUpdate(['id' => $wcc_info['id']],$wcc_update_data);
                //如果该产品的拣货状态为5，说明更新订单状态程序还没执行，只需要更改订单明细即可
                if($wcc_info['dispatching_is_producing_done'] == 5){
                    Db::commit();
                    return show_arr(config('status.code')['success']['code'],config('status.code')['success']['msg']);
                }
                $finish_quantities = $dps_info['finish_quantities']-1;
                $dps_data['finish_quantities'] = $finish_quantities;
                $dps_data['operator_user_id'] = $user_id;
                //判断之前是否已加工完成，若加工完成，需要修改状态
                if($dps_info['finish_quantities'] == $dps_info['sum_quantities']){
                    $dps_data['isDone'] = 0;
                }
                DispatchingProgressSummery::getUpdate(['id' => $dps_info['id']],$dps_data);
                //3.判断该订单是否全部加工完毕
                //如果该产品对应规则的产品全部加工完毕，则需还原更改订单加工状态
                if($wcc_info['order_dispatching_is_producing_done'] == 1){
                    Order::getUpdate(['orderId' => $wcc_info['order_id']],[
                        'dispatching_is_producing_done'=>0
                    ]);
                }
                Db::commit();
                $log_data = [
                    "orderId" => $wcc_info['order_id'],
                    "wj_customer_coupon_id" => $param['id']
                ];
                $DispatchingBehaviorLog->addBehaviorLog($user_id,$businessId,12,$wcc_info['logistic_delivery_date'],$log_data);
                $log_data = [
                    "product_id" => $wcc_info['product_id'],
                    "guige1_id" => $wcc_info['guige1_id'],
                    "wj_customer_coupon_id" => $param['id']
                ];
                $DispatchingItemBehaviorLog->addProducingBehaviorLog($user_id, $businessId, 12, $wcc_info['logistic_delivery_date'],$log_data);
                return show_arr(config('status.code')['success']['code'],config('status.code')['success']['msg']);
            }else{
                return show_arr(config('status.code')['param_error']['code'],config('status.code')['param_error']['msg']);
            }
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return show_arr(config('status.code')['system_error']['code'], $e->getMessage());
        }
    }
}
