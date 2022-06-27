<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\Model;

/**
 * @mixin \think\Model
 */
class Truck extends Model
{
    use modelTrait;


    public function getTruckInfo($businessId,$user_id)
    {
        $info = Truck::alias('t')
            ->field('t.id truck_id,t.business_id,t.current_driver user_id,t.truck_no logistic_truck_No,t.truck_name,t.plate_number,u.avatar,u.contactPersonFirstname,u.contactPersonLastname')
            ->leftjoin('user u','u.id=t.current_driver')
            ->where([
                ['t.business_id','=',$businessId],
                ['t.current_driver','=',$user_id],
                ['t.isAvaliable','=',1]
            ])
            ->find();
        if($info){
            $info['name'] = $info['contactPersonFirstname'].' '.$info['contactPersonLastname'];
        }
        return $info;
    }
}
