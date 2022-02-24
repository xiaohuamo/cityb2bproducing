<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\Model;
use think\facade\Db;

/**
 * @mixin \think\Model
 */
class RestaurantCategory extends Model
{
    use modelTrait;

    /**
     * 获取大类
     * @param $businessId
     */
    public function getCategory($businessId)
    {
        $where = "restaurant_id=$businessId and (parent_category_id is null or parent_category_id=0) and length(category_en_name)>0 and isdeleted=0 and isHide=0";
        $category = Db::name('restaurant_category')->field('id,category_en_name')->where($where)->order('category_sort_id asc')->select();
        return $category;
    }
}
