<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\Model;

/**
 * @mixin \think\Model
 */
class TruckDriverSchedule extends Model
{
    use modelTrait;

    public function getTruckScheduleInfo($businessId,$user_id,$logistic_delivery_date,$logistic_schedule_id)
    {
        $info = TruckDriverSchedule::alias('tds')
            ->field('t.business_id,tds.driver_id user_id,tds.schedule_id logistic_schedule_id,tds.truck_id logistic_truck_No,t.truck_name,t.plate_number,u.avatar,u.contactPersonFirstname,u.contactPersonLastname,u.contactPersonNickName')
            ->leftjoin('truck t','t.truck_no=tds.truck_id and business_id='.$businessId)
            ->leftjoin('user u','u.id=tds.driver_id')
            ->where([
                ['tds.factory_id','=',$businessId],
                ['tds.driver_id','=',$user_id],
                ['tds.delivery_date','=',$logistic_delivery_date],
                ['tds.schedule_id','=',$logistic_schedule_id],
            ])
            ->find();
        if($info){
            $info['name'] = $info['contactPersonNickName'];
        }
        return $info;
    }
}
