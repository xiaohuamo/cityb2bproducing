<?php
namespace app\job;

use think\queue\Job;
use app\model\{
    WjCustomerCoupon,
    NoneprocessedProducingBehaviorLog
};
use think\queue\job\Redis;

class JobLockNoneProcessedProduct
{
    //php think queue:listen --queue lockNoneProcessedProduct
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

            $WjCustomerCoupon = new WjCustomerCoupon();
            $product_data = $WjCustomerCoupon->getNoneProcessedData($data['businessId'],$data['user_id'],$data['data']['logistic_delivery_date'],$data['data']['logistic_truck_No'],[$data['data']['product_id']],$data['data']['logistic_schedule_id']);
            if (!$product_data) {
                $redis->set($data['uniqid'],json_encode(show_arr(config('status.code')['param_error']['code'],config('status.code')['param_error']['msg'])));
            }
            //如果该产品已配货完成，不可重复点击加工
            if ($product_data[$data['data']['product_id']]['isDone'] == 1) {
                $redis->set($data['uniqid'],json_encode(show_arr(config('status.code')['lock_processed_error']['code'],config('status.code')['lock_processed_error']['msg'])));
                return;
            }
            //1.查询该商品是否有人在操作
            if($product_data[$data['data']['product_id']]['operator_user_id'] > 0){
                //如果操作人是一个人，提示请勿重复操作
                if ($product_data[$data['data']['product_id']]['operator_user_id'] == $data['user_id']) {
                    $redis->set($data['uniqid'],json_encode(show_arr(config('status.code')['lock_own_error']['code'],config('status.code')['lock_own_error']['msg'])));
                    return;
                } else {
                    //如果不是一个人在操作提示已有其他人在操作
                    $redis->set($data['uniqid'],json_encode(show_arr(config('status.code')['lock_other_error']['code'],config('status.code')['lock_other_error']['msg'])));
                    return;
                }
            }
            //2.将当前产品未完成的产品锁定当前操作人
            $res = $WjCustomerCoupon->updateNoneProcessedData($data['businessId'],$data['data']['logistic_delivery_date'],$data['data']['logistic_truck_No'],$data['data']['product_id'],$data['user_id'],1,$data['data']['logistic_schedule_id']);
            //添加用户行为日志
            $NoneprocessedProducingBehaviorLog = new NoneprocessedProducingBehaviorLog();
            $NoneprocessedProducingBehaviorLog->addProducingBehaviorLog($data['user_id'],$data['businessId'],1,$data['data']['logistic_delivery_date'],$data['data']);
//            writeLog($res,'queue-job-locknoneproduct');
            if ($res !== false) {
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
