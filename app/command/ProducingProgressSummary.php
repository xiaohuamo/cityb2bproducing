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
        $today_time = strtotime('2022-02-14');//strtotime(date('Y-m-d',time()));
        //供应商id目前先写死，后期优化
        $businessId = 319188;
        $Order = $Order->addOrderGoodsToProgress($businessId,$today_time);
        // 指令输出
        $output->writeln('producingprogresssummary');
    }
}
