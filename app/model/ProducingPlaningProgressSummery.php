<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\facade\Db;
use think\Model;
use app\model\StaffRoles;

/**
 * @mixin \think\Model
 */
class ProducingPlaningProgressSummery extends Model
{
    use modelTrait;

    /**
     * 将订单商品数据加入生产流程表
     * @param $businessId 供应商id
     * @return array
     */
    public function addProgressSummary($data)
    {
        //1.查询该数据是否已存在，不存在新增，存在则更新
        $info = self::is_exist([
            ['business_userId', '=', $data['business_userId']],
            ['delivery_date', '=', $data['delivery_date']],
            ['product_id', '=', $data['product_id']],
            ['guige1_id', '=', $data['guige1_id']],
            ['isdeleted','=',0]
        ]);
        $res = true;
        if ($info) {
            //1.判断数据是否有变动
            if($info['sum_quantities'] != $data['sum_quantities']){
                $update_data['sum_quantities'] = $data['sum_quantities'];
                if($info['isDone']==1 && $info['sum_quantities'] < $data['sum_quantities']){
                    $update_data['isDone'] = 0;
                }
                if($data['sum_quantities'] == 0){
                    $update_data['isdeleted'] = 1;
                }
                $res = self::getUpdate(['id' => $info['id']], $update_data);
                //查询该产品是否需要下架
//                $is_has_data = self::is_exist([
//                    ['business_userId', '=', $data['business_userId']],
//                    ['delivery_date', '=', $data['delivery_date']],
//                    ['product_id', '=', $data['product_id']],
//                    ['isdeleted','=',0]
//                ]);
//                if(empty($is_has_data)){
//                    //查询该产品是否还有二级类目
//                    $map = [
//                        ['rm.id', '=', $data['product_id']],
//                        ['rm.proucing_item','=',1],
//                    ];
//                    $two_cate_count = Db::name('restaurant_menu')
//                        ->alias('rm')
//                        ->leftJoin('restaurant_menu_option rmo','rm.menu_option = rmo.restaurant_category_id')
//                        ->where($map)
//                        ->where('length( rmo.menu_cn_name )> 0 OR length( rmo.menu_en_name )> 0')
//                        ->count();
//                    if($two_cate_count == 0 || $two_cate_count == 1){
//                        ProducingPlaningSelect::deleteAll([
//                            ['business_userId', '=', $data['business_userId']],
//                            ['delivery_date', '=', $data['delivery_date']],
//                            ['product_id', '=', $data['product_id']],
//                        ]);
//                    }
//                }
            }
        } else {
            $res = self::createData($data);
        }
        return $res;
    }

    /**
     * 获取加工产品信息(一级类目)
     * @param $businessId  供应商id
     * @param $userId  用户id
     * @param string $logistic_delivery_date  配送日期
     * @param string $operator_user_id 操作员工id
     * @param int $goods_sort 产品排序
     * @param string $category_id 分类id
     * @param int $sorce 调用接口来源 1-inidata 2-current Plan
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getGoodsOneCate($businessId,$userId,$logistic_delivery_date='',$operator_user_id='',$goods_sort=0,$category_id='',$source=1)
    {
        //如果是管理者，则需要获取全部的包括待分配的产品
        $StaffRoles = new StaffRoles();
        $is_permission = 1;//$StaffRoles->getProductPlaningPermission($userId);
        $where = $this->getGoodsCondition($businessId,$logistic_delivery_date,$operator_user_id,'',$is_permission,$userId);
        $map = [];
        if($category_id){
            $map[] = ['rm.restaurant_category_id','=',$category_id];
        }
        switch($goods_sort){
            case 1:
                $order_by = 'isDone desc,rc.category_sort_id asc,rm.menu_order_id asc';
                break;
            case 2:
                $order_by = 'rm.menu_id asc';
                break;
            default:$order_by = 'isDone asc,rc.category_sort_id asc,rm.menu_order_id asc';
        }
        if ($is_permission == 1) {
            $goods_one_cate = Db::name('producing_planing_select')
                ->alias('pps')
                ->field('pps.product_id,IF(ppps.sum_quantities>=0,sum(ppps.sum_quantities),0) sum_quantities,IF(ppps.finish_quantities>=0,sum(ppps.finish_quantities),0) finish_quantities,IFNULL(ppps.isDone,-1) isDone,IFNULL(ppps.operator_user_id,-1) operator_user_id,rm.menu_en_name,rm.unit_en,rm.menu_id,rc.id cate_id,rc.category_en_name')
                ->leftJoin('producing_planing_progress_summery ppps',"ppps.delivery_date=$logistic_delivery_date and ppps.business_userId=$businessId and ppps.product_id = pps.product_id and ppps.isdeleted=0")
                ->leftJoin('restaurant_menu rm','pps.product_id = rm.id')
                ->leftJoin('restaurant_category rc','rm.restaurant_category_id = rc.id')
                ->where($where)
                ->where(['rm.proucing_item'=>1])
                ->where($map)
                ->group('product_id')
                ->order($order_by)
                ->select()->toArray();
        } else {
            $goods_one_cate = Db::name('producing_planing_progress_summery')
                ->alias('pps')
                ->field('pps.product_id,sum(pps.sum_quantities) sum_quantities,sum(pps.finish_quantities) finish_quantities,pps.isDone,pps.operator_user_id,rm.menu_en_name,rm.unit_en,rm.menu_id,rc.id cate_id,rc.category_en_name')
                ->leftJoin('restaurant_menu rm','pps.product_id = rm.id')
                ->leftJoin('restaurant_category rc','rm.restaurant_category_id = rc.id')
                ->where($where)
                ->where(['rm.proucing_item'=>1])
                ->where($map)
                ->group('product_id')
                ->order($order_by)
                ->select()->toArray();
        }
        foreach($goods_one_cate as &$v){
            $v['sum_quantities'] = floatval($v['sum_quantities']);
            $v['finish_quantities'] = floatval($v['finish_quantities']);
            //获取是否有二级分类
            if ($is_permission == 1) {
                $map = [
                    ['rm.id', '=', $v['product_id']],
                    ['rm.proucing_item','=',1],
                ];
                $two_cate_done_info = Db::name('restaurant_menu')
                    ->alias('rm')
                    ->field('IFNULL(ppps.operator_user_id,-1) operator_user_id,IFNULL(ppps.isDone,-1) isDone')
                    ->leftJoin('restaurant_menu_option rmo','rm.menu_option = rmo.restaurant_category_id')
                    ->leftJoin('producing_planing_progress_summery ppps',"ppps.delivery_date=$logistic_delivery_date and ppps.business_userId=$businessId and ppps.product_id = rm.id and ppps.isdeleted=0")
                    ->where($map)
                    ->where('length( rmo.menu_cn_name )> 0 OR length( rmo.menu_en_name )> 0')
                    ->select()->toArray();
            } else {
                $map = [
                    ['pps.product_id', '=', $v['product_id']],
                    ['pps.guige1_id', '>', 0],
                ];
                $two_cate_done_info = Db::name('producing_planing_progress_summery')->alias('pps')->field('operator_user_id,isDone')->where($where)->where($map)->select()->toArray();
            }
            $v['is_has_two_cate'] = count($two_cate_done_info)>0 ? 1 : 2;//1-有二级分类 2-没有二级分类
            //判断加工状态 0-未加工 1-自己正在加工 2-其他人正在加工 3-加工完成
            $v['status'] = $this->getProcessStatus($v,$userId,1,$two_cate_done_info,$source);
            $v['is_lock'] = $v['operator_user_id']>0&&$v['isDone']==0 ? 1 : 0;
            //获取该产品是否设置置顶
            $top_info = Db::name('restaurant_menu_top')->where(['userId'=>$userId,'business_userId'=>$businessId,'product_id'=>$v['product_id']])->find();
            $v['is_set_top'] = $top_info ? 1 : 0;//是否设置置顶 1设置 0未设置
            //如果查询的是current Plan的结果，获取该产品当前的所有操作员
            $v['operator_user'] = [];
            if($source == 2){
                $ou_where = [
                    ['ppps.product_id', '=', $v['product_id']],
                    ['ppps.operator_user_id', '>', 0],
                ];
                $v['operator_user'] = Db::name('producing_planing_progress_summery')
                    ->alias('ppps')
                    ->field('operator_user_id,u.name,u.nickname,u.displayName')
                    ->leftJoin('user u','u.id = ppps.operator_user_id')
                    ->where($ou_where)
                    ->select()->toArray();
                foreach ($v['operator_user'] as &$vv){
                    $vv['user_name'] = $vv['nickname'] ?: $vv['name'];
                }
            }
        }
        return $goods_one_cate;
    }

    /**
     * 获取商品二级分类
     * @param $where
     * @return array|\think\Collection|Db[]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getGoodsTwoCate($businessId,$userId,$logistic_delivery_date='',$operator_user_id='',$product_id)
    {
        //如果是管理者，则需要获取全部的包括待分配的产品
        $StaffRoles = new StaffRoles();
        $is_permission = $StaffRoles->getProductPlaningPermission($userId);
        $where = $this->getGoodsCondition($businessId,$logistic_delivery_date,$operator_user_id,$product_id,$is_permission,$userId);
        if ($is_permission == 1) {
            $goods_two_cate = Db::name('restaurant_menu')
                ->alias('rm')
                ->field('rm.id product_id,rmo.id guige1_id,IF(ppps.sum_quantities>=0,ppps.sum_quantities,0) sum_quantities,IF(ppps.finish_quantities>=0,ppps.finish_quantities,0) finish_quantities,IFNULL(ppps.isDone,-1) isDone,IFNULL(ppps.operator_user_id,-1) operator_user_id,rm.unit_en,rmo.menu_en_name guige_name')
                ->leftJoin('restaurant_menu_option rmo','rm.menu_option = rmo.restaurant_category_id')
                ->leftJoin('producing_planing_progress_summery ppps',"ppps.delivery_date=$logistic_delivery_date and ppps.business_userId=$businessId and ppps.product_id = rm.id and ppps.guige1_id = rmo.id and ppps.isdeleted=0")
                ->where([
                    ['rm.id','=',$product_id],
                    ['rm.proucing_item','=',1],
                ])
                ->where('length( rmo.menu_cn_name )> 0 OR length( rmo.menu_en_name )> 0')
                ->select()->toArray();
        } else {
            $goods_two_cate = Db::name('producing_planing_progress_summery')
                ->alias('pps')
                ->field('pps.product_id,pps.guige1_id,pps.sum_quantities,pps.finish_quantities,pps.isDone,pps.operator_user_id,rm.unit_en,rmo.menu_en_name guige_name')
                ->leftJoin('restaurant_menu rm','pps.product_id = rm.id')
                ->leftJoin('restaurant_menu_option rmo','pps.guige1_id = rmo.id')
                ->where($where)
                ->where(['rm.proucing_item'=>1])
                ->group('product_id,guige1_id')
                ->select()->toArray();
        }
        foreach($goods_two_cate as &$v){
            //判断加工状态 0-未加工 1-自己正在加工 2-其他人正在加工 3-加工完成
            $v['sum_quantities'] = floatval($v['sum_quantities']);
            $v['finish_quantities'] = floatval($v['finish_quantities']);
            $v['status'] = $this->getProcessStatus($v,$userId,2);
            $v['is_lock'] = $v['operator_user_id']>0&&$v['isDone']==0 ? 1 : 0;
        }
        return $goods_two_cate;
    }

    /**
     * 查询条件判断
     * @param $businessId
     * @param $userId
     * @param string $logistic_delivery_date
     * @param string $operator_user_id
     * @param string $product_id
     */
    public function getGoodsCondition($businessId,$logistic_delivery_date='',$operator_user_id='',$product_id='',$is_permission,$userId)
    {
        $where = "pps.business_userId=$businessId";
        if($logistic_delivery_date){
            $where .= " and pps.delivery_date=$logistic_delivery_date";
        }
        if($product_id){
            $where .= " and pps.product_id=$product_id";
            $where .= " and pps.guige1_id>0";
        }
        if($is_permission == 1){
            //锁定产品即为锁定加工单
            if($operator_user_id){
//                $where .= " and (ppps.operator_user_id=$operator_user_id or pps.userId=$userId)";
                $where .= " and ppps.operator_user_id=$operator_user_id";
            }
        }else{
            $where .= " and pps.isdeleted=0";
            //锁定产品即为锁定加工单，只针对管理员有作用
            if($operator_user_id && $is_permission==1){
                $where .= " and pps.operator_user_id=$operator_user_id";
            }
        }
        return $where;
    }

    /**
     * 获取当前加工状态
     * @param $data 产品数据
     * @param $userId 当前用户id
     * @param int $type 1-一级类目 2-二级类目
     * @param array $two_cate_done_info 二级类目完成情况
     * @return int
     */
    public function getProcessStatus($data,$userId,$type=1,$two_cate_done_info=[],$source=1)
    {
        //判断加工状态 0-未加工 1-自己正在加工 2-其他人正在加工 3-加工完成 4-待分配
        if($type==1 && $data['is_has_two_cate'] == 2 || $type==2){
            if($data['isDone'] == 0){
                if($data['operator_user_id'] > 0){
                    $status = $data['operator_user_id']==$userId ? 1 : 2;
                } else {
                    $status = 0;
                }
            }elseif($data['isDone'] == 1){
                $status = 3;
            }else{
                $status = 4;
            }
        } else {
            //如果一级分类中，有二级分类，需要根据二级分类中的所有来判断一级的状态
            if($type==1 && $data['is_has_two_cate'] == 1){
                //查询该产品所有的二级状态
                $two_cate_done_unique = array_unique(array_column($two_cate_done_info,'isDone'));
                $operator_user_id_arr = array_column($two_cate_done_info,'operator_user_id');
//                dump($two_cate_done_unique);
                if(count($two_cate_done_unique) == 1){
                    if($two_cate_done_unique[0] == 0){
                        $status = $this->productStatusAccordGuige($userId,$operator_user_id_arr,$source);
                    }elseif($two_cate_done_unique[0] == 1){
                        $status = 3;
                    }else{
                        $status = 4;
                    }
                }else{
                    //判断未完成的规格中加工状态
                    $operator_user_id_arr = [];
                    foreach($two_cate_done_info as $v){
                        if($v['isDone'] == 0 || $v['isDone'] == -1){
                            $operator_user_id_arr[] = $v['operator_user_id'];
                        }
                    }
                    $status = $this->productStatusAccordGuige($userId,$operator_user_id_arr,$source);
                }
            }
        }
        return $status;
    }

    /**
     * 根据规格加工状态，判断当前产品显示的状态
     * @param $user_id
     * @param $operator_user_id_arr
     */
    public function productStatusAccordGuige($userId,$operator_user_id_arr,$source)
    {
        if(count($operator_user_id_arr) > 0){//有人正在操作
            //如果当前用户正在加工该产品，状态为：正在加工中2
            //$source=1:如果当前用户没加工该产品，判断所有规格是否都有人在加工，所有规格都被其他人加工，状态为：其他人加工中2。否则，状态为：待加工0
            //$source=2:如果当前用户没加工该产品，判断是否有其他人在加工，有则灰闪；没有则粉色
            if(in_array($userId,$operator_user_id_arr)){
                $status = 1;//表示正在加工中
            }else{
                if($source == 1){
                    if(in_array(0,$operator_user_id_arr)){
                        $status = 0;//表示待加工
                    }elseif(in_array(-1,$operator_user_id_arr)){
                        $status = 4;//待分配加工数量
                    }else{
                        $status = 2;//其他人加工中
                    }
                } else {
                    //1.判断是否有其他人在加工 1-是 2-否
                    $is_has_other = 2;
                    foreach ($operator_user_id_arr as $v){
                        if($v>0){
                            $is_has_other = 1;
                            $status = 2;//其他人加工中
                            break;
                        }
                    }
                    if($is_has_other == 2){
                        if(in_array(0,$operator_user_id_arr)){
                            $status = 0;//表示待加工
                        }elseif(in_array(-1,$operator_user_id_arr)){
                            $status = 4;//待分配加工数量
                        }
                    }
                }
            }
        }else{//所有规格都无人加工
            $status = 0;
        }
        return $status;
    }
}
