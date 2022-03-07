<?php
declare (strict_types = 1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use app\model\{
    Order
};

class ProducingProgressSummary extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('producingprogresssummary')
            ->setDescription('the producingprogresssummary command');
    }

    protected function execute(Input $input, Output $output)
    {
        $Order = new Order();
        //默认获取当天所有的加工订单
        $today_time = strtotime(date('Y-m-d',time()));
        //供应商id目前先写死，后期优化
        $businessId = 319188;
        //获取订单加工的最新日期
        $logistic_delivery_date = Order::where(['business_userId'=>$businessId])->order('logistic_delivery_date desc')->value('logistic_delivery_date');
        //获取两个日期的差值
        $diffDays = ($logistic_delivery_date - $today_time) / 86400;
        if($diffDays >= 0){
            for($i=0;$i<=$diffDays;$i++){
                $time = strtotime("+$i day",$today_time);
                $Order->addOrderGoodsToProgress($businessId,$time);
            }
            //加载前7天的数据
//            for($i=0;$i<=7;$i++){
//                $time = strtotime("-$i day",$today_time);
//                $Order->addOrderGoodsToProgress($businessId,$time);
//            }
        }
        // 指令输出
        $output->writeln('producingprogresssummary');
    }
}
