<?php
namespace app\job;

use think\queue\Job;
use app\product\service\OrderItemStatus;
use app\model\{
    DispatchingProgressSummery
};

class JobDealProductStockStatus
{
    //php think queue:listen --queue dealProductStockStatus
    public function fire(Job $job, $data){
        try{
            //这里执行具体的任务
            if ($job->attempts() > 3) {
                //通过这个方法可以检查这个任务已经重试了几次了
                $job->delete();
                return;
            }

            //修改订单明细的状态
            $OrderItemStatus = new OrderItemStatus();
            $redis = redis_connect();
            if($data['is_producing_done'] == 5){
                $data['is_producing_done'] = 1;//需要将状态变为已完成，当成参数传递'
                $res = $OrderItemStatus->ProducingOrderItemStatusChange($data['business_userId'],$data['operator_user_id'],$data);
            }
            //拣货端状态是5的，针对的是生产端未分配库存的产品，因此一旦生产端生产完成，则拣货端的拣货状态变为5，订单拣货和产品拣货的人员都是生产端的操作人员
            if($data['dispatching_is_producing_done'] == 5){
                $data['is_producing_done'] = 1;
                $res = $OrderItemStatus->PickingOrderItemStatusChange($data['business_userId'],$data['dispatching_operator_user_id'],$data);
            }
//            writeLog($res,'queue-job');
            //如果任务执行成功后 记得删除任务，不然这个任务会重复执行，直到达到最大重试次数后失败后，执行failed方法
            $job->delete();
            return;
        }catch (\Exception $e){
            $job->delete();
            return;
        }
    }
}
