<?php
namespace app\job;

use think\queue\Job;
use app\model\{DispatchingProgressSummery, DispatchingBehaviorLog, WjCustomerCoupon};
use think\queue\job\Redis;

class JobLockOrder
{
    //php think queue:listen --queue lockOrder
    public function fire(Job $job, $data){
        try{
            $redis = redis_connect();
//            dump($redis);
            //这里执行具体的任务
            if ($job->attempts() > 1) {
                //通过这个方法可以检查这个任务已经重试了几次了
                $redis->set($data['uniqid'],json_encode(show_arr(config('status.code')['lock_error']['code'],config('status.code')['lock_error']['msg'])));
                $job->delete();
                return;
            }
            $dps_info = DispatchingProgressSummery::getOne([
                'business_id' => $data['dps_info']['business_id'],
                'delivery_date' => $data['dps_info']['delivery_date'],
                'orderId' => $data['dps_info']['orderId'],
                'isdeleted' => 0
            ]);
            if (!$dps_info) {
                $redis->set($data['uniqid'],json_encode(show_arr(config('status.code')['param_error']['code'],config('status.code')['param_error']['msg'])));
                return;
            }
            //如果该产品已加工完，不可重复点击加工
            if ($dps_info['isDone'] == 1) {
                $redis->set($data['uniqid'],json_encode(show_arr(config('status.code')['lock_processed_error']['code'],config('status.code')['lock_processed_error']['msg'])));
                return;
            }
            //1.查询该商品是否有人在操作
            if($dps_info['operator_user_id'] > 0){
                //如果操作人是一个人，提示请勿重复操作
                if ($dps_info['operator_user_id'] == $data['user_id']) {
                    $redis->set($data['uniqid'],json_encode(show_arr(config('status.code')['lock_own_error']['code'],config('status.code')['lock_own_error']['msg'])));
                    return;
                } else {
                    //如果不是一个人在操作提示已有其他人在操作
                    $redis->set($data['uniqid'],json_encode(show_arr(config('status.code')['lock_other_error']['code'],config('status.code')['lock_other_error']['msg'])));
                    return;
                }
            }
            //2.锁定当前操作人
            $udpate_data = ['operator_user_id'=>$data['user_id']];
            $res = DispatchingProgressSummery::getUpdate(['id' => $data['dps_info']['id']],$udpate_data);
            //3.锁定订单明细当前操作人
            $wcc_where = [
                ['o.business_userId','=',$data['dps_info']['business_id']],
                ['o.logistic_delivery_date','=',$data['dps_info']['delivery_date']],
                ['wcc.order_id','=',$dps_info['orderId']],
                ['wcc.dispatching_is_producing_done','<>',1]
            ];
            $wcc_data = ['wcc.dispatching_operator_user_id' => $data['user_id']];
            $WjCustomerCoupon = new WjCustomerCoupon();
            $WjCustomerCoupon->updateWccData($wcc_where,$wcc_data);
            //添加用户行为日志
            $DispatchingBehaviorLog = new DispatchingBehaviorLog();
            $DispatchingBehaviorLog->addBehaviorLog($data['user_id'],$data['dps_info']['business_id'],1,$data['dps_info']['delivery_date'],$data['dps_info']);
//            writeLog($res,'queue-job-lockproduct');
            if ($res) {
                $redis->set($data['uniqid'],json_encode(show_arr(config('status.code')['success']['code'],config('status.code')['success']['msg'])));
            } else {
                $redis->set($data['uniqid'],json_encode(show_arr(config('status.code')['lock_error']['code'],config('status.code')['lock_error']['msg'])));
            }
            //如果任务执行成功后 记得删除任务，不然这个任务会重复执行，直到达到最大重试次数后失败后，执行failed方法
            $job->delete();
            return;
        }catch (\Exception $e){
            $redis->set($data['uniqid'],json_encode(show_arr(config('status.code')['lock_error']['code'],config('status.code')['lock_error']['msg'])));
            $job->delete();
            return;
        }
    }
}
