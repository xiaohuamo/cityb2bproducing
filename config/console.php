<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'producingprogresssummary' => 'app\command\ProducingProgressSummary',//实时更新生产进程数据
        'dispatchingprogresssummary' => 'app\command\DispatchingProgressSummary', //实时更新拣货员进程数据
        'dealproductstockstatus' => 'app\command\DealProductStockStatus', //实时更新生产端库存分配完成的状态，将生产状态改为已完成
    ],
];
