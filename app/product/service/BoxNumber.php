<?php
namespace app\product\service;

use think\facade\Db;

class BoxNumber
{
    /**
     * 获取订单需要的总箱数
     * @param $orderId 订单id
     */
    public function getOrderBoxes($orderId)
    {
        $where = [
            ['o.orderId', '=', $orderId],
            ['wcc.customer_buying_quantity', '>', 0]
        ];
        //1.获取该订单所有的加工明细单数据
        $order = Db::name('wj_customer_coupon')
            ->alias('wcc')
            ->field('wcc.id,wcc.menu_id,wcc.restaurant_menu_id product_id,wcc.guige1_id,wcc.customer_buying_quantity,wcc.new_customer_buying_quantity,rm.unitQtyPerBox,rm.overflowRate,rm.restaurant_category_id cate_id')
            ->leftJoin('order o','wcc.order_id = o.orderId')
            ->leftJoin('restaurant_menu rm','rm.id = wcc.restaurant_menu_id')
            ->where($where)
            ->order('rm.menu_id asc,wcc.id asc')
            ->select()->toArray();
        $box_data = [];
        foreach($order as &$v){
            //2.获取该产品实际的数量
            $customer_buying_quantity = $v['new_customer_buying_quantity']>=0?$v['new_customer_buying_quantity']:$v['customer_buying_quantity'];
            $unitQtyPerBox = $v['unitQtyPerBox'];//每箱可装数量
            $overflowRate = $v['overflowRate'];//溢出率
            //3.根据实际购买数量计算实际需要的箱数
            $productBoxNumber = $this->productBoxNumber($customer_buying_quantity,$unitQtyPerBox,$overflowRate);
            $v = array_merge($v,$productBoxNumber);
        }
//        dump($order);
        $boxesNumber = $this->orderBoxNumber($order);
        return $boxesNumber;
    }

    /**
     * 计算订单所需的总箱数
     * @param Array $order 订单数据
     * @return Array
     */
    public function orderBoxNumber($order)
    {
        $orderboxnumber = 0;//订单总箱数
        $splicingboxnumber_arr = [];//存储待拼箱的数据
        $splicing_arr = [];//存储拼箱成功的数据
        foreach ($order as $v){
            $orderboxnumber += $v['boxnumber'];
            if($v['splicingboxnumber'] > 0){
                $splicingboxnumber_arr[] = $v;
            }
        }
        //如果有需要拼箱的数据，则计算拼箱的最优方案
        if($splicingboxnumber_arr){
            $splicing_index_arr = $this->permutations($splicingboxnumber_arr);
        }else{
            $splicing_index_arr = [];
        }
        $splicingboxnumber = count($splicing_index_arr);
        foreach ($splicing_index_arr as $k=>$v){
            foreach ($v as $kk=>$vv){
                $splicing_arr[$k][$kk] = $splicingboxnumber_arr[$vv];
            }
        }
        $orderboxnumber += $splicingboxnumber;
        return [
            'orderboxnumber' => $orderboxnumber,//该订单的总箱数
            'splicingboxnumber' => $splicingboxnumber,//需要拼箱的箱数
            'splicing_arr' => $splicing_arr,//需要拼箱的数据
            'order' => $order,//当前订单数据
        ];
    }

    /**
     * 计算单个订单明细所需的箱数
     * @param float $customer_buying_quantity 实际购买数量
     * @param float $unitQtyPerBox 单位数量（没箱可装数量）
     * @param float $overflowRate 溢出率（计算每箱最多可容纳的数量）
     * @return Array
     */
    public function productBoxNumber($customer_buying_quantity,$unitQtyPerBox,$overflowRate)
    {
        //根据实际购买数量计算实际需要的箱数
        if($customer_buying_quantity < $unitQtyPerBox){
            $boxnumber = 0;
            $splicingboxnumber = (float)number_format(ceil($customer_buying_quantity / ($unitQtyPerBox * (1 + $overflowRate / 100))*100)/100, 2);
        } else {
            //1-1.计算出整箱数
            $boxnumber = intval($customer_buying_quantity / $unitQtyPerBox);
            //1-2.计算出剩余重量
            $restnums = (float)number_format($customer_buying_quantity - $boxnumber * $unitQtyPerBox, 2);
            //$splicingboxnumber 记录需要拼箱的数量
            //如果没有剩余重量，则该产品所需箱数正好是整箱数
            if ($restnums == 0) {
                $splicingboxnumber = 0;
            } else {
                //如果有余数，通过比较溢出率，如果在溢出范围内，则不用多占用一箱，否则需要拼箱
                //1.判断溢出率，如果按照溢出率，正好够装满最后一箱，则总箱数就是当前整箱数
                //2.判断溢出率，如果按照溢出率，$restnums>$maxoverflownums,则计算出剩余需要拼箱的数量
                $maxoverflownums = $overflowRate * $unitQtyPerBox / 100;
                if ($restnums <= $maxoverflownums) {
                    $splicingboxnumber = 0;
                } else {
                    $splicingboxnumber = (float)number_format(ceil($restnums / ($unitQtyPerBox * (1 + $overflowRate / 100))*100)/100, 2);
                }
            }
        }
        return [
            'boxnumber' => $boxnumber,//该产品所需整箱数
            'splicingboxnumber' => $splicingboxnumber,//该产品所需拼箱数量
        ];
    }

    /**
     * @todo    Combination of an array
     *
     * @access  public
     * @param   array       $sort       An array of combinations
     * @param   int         $num        Elements to be taken
     * @return  array       $result     Combined array
     * */
    public function Combination($sort, $num)
    {
        $result = $data = array();
        if( $num == 1 ) {
            return $sort;
        }
        foreach( $sort as $k=>$v ) {
            unset($sort[$k]);
            $data   = $this->Combination($sort,$num-1);
            foreach($data as $row) {
                $result[] = $v.','.$row;
            }
        }
        return $result;
    }

    /**
     * 获取所有的最有组合
     * @param $splicingboxnumber_arr 需要排列的数组
     */
    public function permutations($splicingboxnumber_arr)
    {
        $result = array();//存储所有的组合方案
        if(empty($splicingboxnumber_arr)) {
            return $result;
        }
        $a = array_keys($splicingboxnumber_arr);//需要组合排序的数据的key值;
        $d = array();//存储所有可能的组合排序
        $r = 1;//组合排序综合最大不能超过1
        for($i=1; $i<=count($a); $i++) {
            foreach($this->Combination($a, $i) as $v){
                $v = explode(',',(string)$v);
                //取出组合中大于0小于1的所有组合
                //取出所有组合中的拼接箱数
                $splicingboxnumbers = [];
                foreach ($v as $vv){
                    $splicingboxnumbers[] = $splicingboxnumber_arr[$vv]['splicingboxnumber'];
                }
                if($r - array_sum($splicingboxnumbers)>=0){
                    $d[join(',', $v)] = $r - array_sum($splicingboxnumbers);
                }
            }
        }
        asort($d);
        //查找出最优的方案，如果差值相同的，则按照分类是一组的优先
        $all_combination = array_keys($d);//所有组合的键值
        $result = $this->combinationResult($a,$d,$splicingboxnumber_arr);
        return $result;
    }

    /**
     * 获取组合结果
     * @param $a 需要排列的数据
     * @param $d 目前该数据所有的排列组合
     * @param $splicingboxnumber_arr 需要拼箱的数组
     * @return mixed
     */
    public function combinationResult($a,$d,$splicingboxnumber_arr,$result = array())
    {
        $cb_data = [];//当前获取的组合数据
        if(empty($a)){
            return $result;
        }
        //查找出最优的方案，如果差值相同的，则按照分类是一组的优先
        $all_combination = array_keys($d);//所有组合的键值
        $same_sort[] = $all_combination[0];//存储差值相同的组合
        foreach ($all_combination as $dk=>$dv){
            if($dk>0 && $d[$dv] == $d[$all_combination[0]]){
                $same_sort[] = $dv;
            }
        }
        if(count($same_sort)>1){
            $cate_id_arr = [];//存储每个排列组合的分类
            $same_cate_count = [];//存储每个排列组合的分类中相同分类的个数
            $max_cate_count = [];//存储每个排雷组合的分类中相同分类的最大数值
            foreach($same_sort as $ssk=>$ssv){
                $ssv_arr = explode(',',$ssv);
                foreach ($ssv_arr as $sav){
                    $cate_id_arr[$ssk][] = $splicingboxnumber_arr[$sav]['cate_id'];
                }
                $same_cate_count[$ssk][] = array_count_values($cate_id_arr[$ssk]);
                $max_cate_count[$ssk][] = max($same_cate_count[$ssk]);
            }
            //获取到最优解
            $key = array_search(max($max_cate_count),$max_cate_count,true);
            $cb_data = $same_sort[$key];
        }else{
            $cb_data = $same_sort[0];
        }
        $result[] = array_map('intval',explode(',',$cb_data));
        //查找到第一个最优组合，然后获取剩余的排序组合
        $cb_data_arr = explode(',',$cb_data);
        $new_a = array_values(array_diff($a,$cb_data_arr));
        if($new_a){
            //获取数组中剩余的数据继续调用此方法，直到没有数据
            $new_d = [];//存储剩余数据的排列组合
            foreach ($d as $dk=>$dv){
                $dk_arr = explode(',',$dk);
                if(empty(array_diff($dk_arr,$new_a))){
                    $new_d[$dk] = $dv;
                }
            }
            return $this->combinationResult($new_a,$new_d,$splicingboxnumber_arr,$result);
        }else{
            return $result;
        }
    }
}
