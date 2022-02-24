<?php
namespace app\job;

use think\queue\Job;
use app\model\{
    ProducingBehaviorLog,
    ProducingProgressSummery
};
use think\queue\job\Redis;

class JobLockProduct
{
    //php think queue:work --queue lockProduct
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
            //如果该产品已加工完，不可重复点击加工
            if ($data['pps_info']['isDone'] == 1) {
                return show(config('status.code')['lock_processed_error']['code'], config('status.code')['lock_processed_error']['msg']);
            }
            //1.查询该商品是否有人在操作
            if($data['pps_info']['operator_user_id'] > 0){
                //如果操作人是一个人，提示请勿重复操作
                if ($data['pps_info']['operator_user_id'] == $data['user_id']) {
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
            $res = ProducingProgressSummery::getUpdate(['id' => $data['pps_info']['id']],$udpate_data);
            //添加用户行为日志
            $ProducingBehaviorLog = new ProducingBehaviorLog();
            $ProducingBehaviorLog->addProducingBehaviorLog($data['user_id'],$data['pps_info']['business_userId'],1,$data['pps_info']['delivery_date'],$data['pps_info']);
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
