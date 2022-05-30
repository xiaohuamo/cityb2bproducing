<?php
declare (strict_types = 1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;
use app\model\WjCustomerCoupon;
use think\facade\Queue;

class DealProductStockStatus extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('dealproductstockstatus')
            ->setDescription('the dealproductstockstatus command');
    }

    protected function execute(Input $input, Output $output)
    {
        //查询当前所有库存分配完成的订单明细和生产端未分配的产品加工完成之后，拣货端状态=5
        $where = "wcc.is_producing_done=5 or wcc.dispatching_is_producing_done=5";
        $list = WjCustomerCoupon::alias('wcc')
            ->field('wcc.id,wcc.is_producing_done,wcc.dispatching_is_producing_done,wcc.operator_user_id,wcc.dispatching_operator_user_id,wcc.dispatching_item_operator_user_id,o.business_userId')
            ->leftJoin('order o','o.orderId = wcc.order_id')
            ->where($where)
            ->select()->toArray();
        foreach($list as $k=>$v){
            $isPushed = Queue::push('app\job\JobDealProductStockStatus', $v, 'dealProductStockStatus');
            // database 驱动时，返回值为 1|false  ;   redis 驱动时，返回值为 随机字符串|false
            if ($isPushed !== false) {
                echo date('Y-m-d H:i:s') . " a new Job is Pushed to the MQ" . "<br>";
            } else {
                echo 'Oops, something went wrong.';
            }
        }
        // 指令输出
        $output->writeln('dealproductstockstatus');
    }
}
