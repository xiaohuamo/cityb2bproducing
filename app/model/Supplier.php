<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\Model;

/**
 * @mixin \think\Model
 */
class Supplier extends Model
{
    use modelTrait;

    /**
     * 获取商家权限
     * @param $business_id
     * @return int[]
     */
    public function getCompanyPermission($business_id)
    {
        //查询该商家的权限
        $company_type = self::getVal(['userId'=>$business_id],'company_type');
        //$company_type 商家类型 1-非生产商家(拥有订单拣货，产品拣货)  2-非即时生产(拥有预生产，订单拣货，产品拣货) 3-即时生产商家(拥有生产端，预生产，订单拣货，产品拣货)
        //$pannel_arr 存储可以进入的pannel权限 1-生产端 2-预生产 3-产品拣货 4-订单拣货
        switch ($company_type){
            case 1:$pannel_arr = [3,4];//非生产商家
                break;
            case 2:$pannel_arr = [2,3,4];//非即时生产
                break;
            case 3:$pannel_arr = [1,2,3,4];//即时生产
                break;
            default:$pannel_arr = [3,4];
        }
        return $pannel_arr;
    }
}
