<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\Model;

/**
 * @mixin \think\Model
 */
class ProducingItemStock extends Model
{
    use modelTrait;

    /**
     * 更新产品的库存
     * @param $data 更新数据
     * @param int $type 1-新增库存 2-减少库存
     */
    public function stockUpdate($data,$type=1)
    {
        $stock_info = self::getOne(['item_id'=>$data['product_id'],'spec_id'=>$data['guige1_id'],'factory_id'=>$data['businessId']]);
        if(empty($stock_info)){
            if($type == 1){
                self::createData([
                    'item_id'=>$data['product_id'],
                    'spec_id'=>$data['guige1_id'],
                    'factory_id'=>$data['businessId'],
                    'stock_qty'=>$data['quantity']
                ]);
            }
        }else{
            if($type == 1){
                $quantity = $stock_info['stock_qty']+$data['quantity'];
            }else{
                $quantity = $stock_info['stock_qty']-$data['quantity']>0?$stock_info['stock_qty']-$data['quantity']:0;
            }
            self::getUpdate(['id'=>$stock_info['id']],['stock_qty'=>$quantity]);
        }
    }
}
