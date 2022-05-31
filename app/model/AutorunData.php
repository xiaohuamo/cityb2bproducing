<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\Model;

/**
 * @mixin \think\Model
 */
class AutorunData extends Model
{
    use modelTrait;

    public function addAutorunData($wcc_info,$user_id)
    {
        $ard_info = self::getOne(['data_type'=>100,'ref_id' => $wcc_info['id'],'ref_value1' => $wcc_info['business_userId'],'status' => 0]);
        if(empty($ard_info)){
            self::createData([
                'data_type' => 100,
                'ref_id' => $wcc_info['id'],
                'ref_value1' => $wcc_info['business_userId'],
                'status' => 0,
                'gen_date' => time(),
                'operator_user_id' => $user_id
            ]);
        }
    }
}
