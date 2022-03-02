<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\Model;
use think\facade\Db;

/**
 * @mixin \think\Model
 */
class RestaurantMenu extends Model
{
    use modelTrait;

    public function getCateProduct($businessId,$user_id,$category_id)
    {
        $data = Db::name('restaurant_menu')
            ->alias('rm')
            ->field('rm.id product_id,rm.menu_en_name,rm.unit_en,rmt.id,IF(rmt.id>0,1,0) as is_set_top')
            ->leftJoin('restaurant_menu_top rmt',"rmt.product_id=rm.id and userId=$user_id and business_userId=$businessId")
            ->where([
                ['rm.restaurant_category_id','=',$category_id],
                ['rm.proucing_item','=',1],
                ['rm.isDeleted','=',0]
            ])
            ->select()->toArray();
        return $data;
    }
}
