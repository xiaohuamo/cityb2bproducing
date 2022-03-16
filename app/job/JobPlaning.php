<?php
namespace app\job;

use think\queue\Job;
use app\model\{
    ProducingProgressSummery
};

class JobPlaning
{
    //php think queue:listen --queue producingProgressSummary
    public function fire(Job $job, $data){
        try{
            //这里执行具体的任务
            if ($job->attempts() > 3) {
                //通过这个方法可以检查这个任务已经重试了几次了
                $job->delete();
                return;
            }

            //将商品加工数据添加到汇总表中
            $ProducingProgressSummery = new ProducingProgressSummery();
            $res = $ProducingProgressSummery->addProgressSummary($data);
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
