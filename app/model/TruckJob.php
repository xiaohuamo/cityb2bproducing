<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\Model;

/**
 * @mixin \think\Model
 */
class TruckJob extends Model
{
    use modelTrait;


    public function getTruckJobInfo($businessId,$user_id,$logistic_delivery_date)
    {
        $info = Truck::alias('t')
            ->field('tj.*,t.business_id businessId,t.current_driver user_id,t.truck_no logistic_truck_No,t.truck_name,t.plate_number,u.contactPersonFirstname,u.contactPersonLastname')
            ->leftjoin('truck_job tj','tj.truck_id=t.id and tj.delivery_date='.$logistic_delivery_date)
            ->leftjoin('user u','u.id=t.current_driver')
            ->where([
                ['t.business_id','=',$businessId],
                ['t.current_driver','=',$user_id],
                ['t.isAvaliable','=',1]
            ])
            ->find();
        if($info){
            $info['name'] = $info['contactPersonFirstname'].' '.$info['contactPersonLastname'];
            if($info['truck_id']>0){
                //查询该司机的最后一次的结束里程数，作为开始里程数
                $last_end_kile_metre = self::where([
                    ['truck_id','=',$info['truck_id']],
                    ['start_job_time','<',$info['start_job_time']],
                ])->order('id desc')->value('end_kile_metre');
            }else{
                $last_end_kile_metre = '';
            }
            $info['start_kile_metre_num'] = $info['start_kile_metre'];
            $info['end_kile_metre_num'] = $info['end_kile_metre'];
            $info['start_kile_metre'] = is_numeric($info['start_kile_metre'])&&$info['start_kile_metre']>=0 ? $this->formatNumber($info['start_kile_metre']) : ($last_end_kile_metre ? number_format(floatval($last_end_kile_metre)) : '');
            $info['start_temprature'] = is_numeric($info['start_temprature']) ? floatval($info['start_temprature']) : '';
            $info['start_truck_check'] = $info['start_truck_check'] ?: 0;
            $info['end_kile_metre'] = is_numeric($info['end_kile_metre'])&&$info['end_kile_metre']>0 ? $this->formatNumber($info['end_kile_metre']) : '';
            $info['end_temprature'] = is_numeric($info['end_temprature']) ? floatval($info['end_temprature']) : '';
            $info['end_truck_check'] = $info['end_truck_check'] ?: 0;
        }
        return $info;
    }

    /**
     * 格式化显示数字
     * @param $number
     */
    public function formatNumber($number)
    {
        $remain = bcmod((string)($number*100),(string)100);
        $quo = number_format((float)$number);
        $data = $quo;
        if($remain>0){
            $data = $quo.'.'.$remain;
        }
        return $data;
    }

    /**
     * 生成工作数据
     * @param $data 工作数据
     * @param $businessId 商家id
     * @param $user_id 用户id
     * @param int $type 类型 1-start job  2-job done
     */
    public function createJobData($data,$businessId,$user_id,$type=1)
    {
        //1.查询该司机的工作数据今日是否存在，不存在新增，存在则编辑
        $job_info = self::getOne([
            'delivery_date' => $data['logistic_delivery_date'],
            'truck_id' => $data['truck_id'],
        ]);
        $time = time();
        if(empty($job_info)){
            $create_data = [
                'truck_id' => $data['truck_id'],
                'business_id' => $businessId,
                'current_driver' => $user_id,
                'delivery_date' => $data['logistic_delivery_date'],
            ];
            if($type == 1){
                $start_data = [
                    'start_kile_metre' => $data['start_kile_metre'],
                    'start_temprature' => $data['start_temprature'],
                    'start_truck_check' => $data['start_truck_check'],
                    'start_truck_check_content' => $data['start_truck_check_content'],
                    'start_job_time' => $time,
                    'end_temprature' => ''
                ];
                $create_data = array_merge($create_data,$start_data);
            }else{
                $end_data = [
                    'end_kile_metre' => $data['end_kile_metre'],
                    'end_temprature' => $data['end_temprature'],
                    'end_truck_check' => $data['end_truck_check'],
                    'end_truck_check_content' => $data['end_truck_check_content'],
                    'end_job_time' => $time,
                ];
                $create_data = array_merge($create_data,$end_data);
            }
            $res = self::createData($create_data);
        } else {
            if($type == 1){
                $update_data = [
                    'start_kile_metre' => $data['start_kile_metre'],
                    'start_temprature' => $data['start_temprature'],
                    'start_truck_check' => $data['start_truck_check'],
                    'start_truck_check_content' => $data['start_truck_check_content'],
                ];
                if($job_info['start_job_time']<=0){
                    $update_data['start_job_time'] = $time;
                }
            }else{
                $update_data = [
                    'end_kile_metre' => $data['end_kile_metre'],
                    'end_temprature' => $data['end_temprature'],
                    'end_truck_check' => $data['end_truck_check'],
                    'end_truck_check_content' => $data['end_truck_check_content'],
                ];
                if($job_info['end_job_time']<=0){
                    $update_data['end_job_time'] = $time;
                }
            }
             $res = self::getUpdate([
                 'id' => $job_info['id']
             ],$update_data);
        }
        return $res;
    }
}
