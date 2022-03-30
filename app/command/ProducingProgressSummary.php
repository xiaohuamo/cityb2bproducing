<?php
declare (strict_types = 1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\Facade\Db;
use app\model\{
    Order,
    ProducingProgressSummery
};

class ProducingProgressSummary extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('producingprogresssummary')
            ->addArgument('businessId', Argument::OPTIONAL, "businessId")
            ->setDescription('the producingprogresssummary command');
    }

    protected function execute(Input $input, Output $output)
    {
        //查询当前所有在线的商家id
        $new_businessId = $input->getArgument('businessId');
        //默认获取当天所有的加工订单
        $today_time = strtotime(date('Y-m-d', time()));
        if(empty($new_businessId)){
            //获取当前汇总表中的所有供应商id
            $businessId_arr = Db::name('producing_progress_summery')->group('business_userId')->column('business_userId');
            foreach ($businessId_arr as $v) {
                $this->addData($today_time,$v,1);
            }
        } else {
            $this->addData($today_time,$new_businessId,2);
        }
        // 指令输出
        $output->writeln('producingprogresssummary');
    }

    /**
     * 更新加工数据
     * @param $today_time
     * @param $businessId
     * @param $type 1自动更新 2新增商家数据更新
     */
    public function addData($today_time,$businessId,$type)
    {
        $Order = new Order();
        //获取订单加工的最新日期
        $logistic_delivery_date = Order::where(['business_userId' => $businessId])->order('logistic_delivery_date desc')->value('logistic_delivery_date');
        //获取两个日期的差值
        $diffDays = ($logistic_delivery_date - $today_time) / 86400;
        if ($diffDays >= 0) {
            for ($i = 0; $i <= $diffDays; $i++) {
                $time = strtotime("+$i day", $today_time);
                $Order->addOrderGoodsToProgress($businessId, $time,$type);
            }
            //加载前7天的数据
            for($i=1;$i<=7;$i++){
                $time = strtotime("-$i day",$today_time);
                $Order->addOrderGoodsToProgress($businessId,$time,$type);
            }
        }
    }
}
