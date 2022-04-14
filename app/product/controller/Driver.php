<?php
declare (strict_types = 1);

namespace app\product\controller;

use think\Request;
use app\common\service\UploadFileService;
use app\product\validate\IndexValidate;
use app\model\{Order, Truck, User};

class Driver extends AuthBase
{
    /**
     * 我的页面
     * @return \think\Response
     */
    public function me()
    {
        // 模板输出
        return $this->fetch('me');
    }

    /**
     * 收货页面
     * @return \think\Response
     */
    public function order()
    {
        // 模板输出
        return $this->fetch('order');
    }

    /**
     * 确认收货页面
     * @return \think\Response
     */
    public function confirmRecept()
    {
        // 模板输出
        return $this->fetch('confirm_recept');
    }

    /**
     * 客户查询页面
     * @return \think\Response
     */
    public function customerSearch()
    {
        // 模板输出
        return $this->fetch('customer_search');
    }

    //获取用户登录信息
    public function loginInfo()
    {
        //接收参数
        $param = $this->request->only(['type','business_id','user_id']);
        $param['type'] = $param['type']??'';//传递类型 loginCheck：登录校验
        $param['business_id'] = $param['business_id']??0;
        $param['user_id'] = $param['user_id']??0;

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();//当前操作用户
        if ($param['type'] == 'loginCheck') {
            if ($businessId != $param['business_id'] || $user_id != $param['user_id']) {
                $this->clearCookie();
                return show(config('status.code')['log_out']['code'],config('status.code')['log_out']['msg']);
            }
        }

        $truck = new Truck();
        $data = $truck->getTruckInfo($businessId,$user_id);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    /**
     * 获取当前用户的司机编号
     * @param $businessId
     * @param $user_id
     */
    public function getlogistic_truck_No($businessId,$user_id)
    {
        $logistic_truck_No = Truck::getVal([
            'business_id' => $businessId,
            'current_driver' => $user_id,
            'isAvaliable' => 1
        ],'truck_no');
        $logistic_truck_No = empty($logistic_truck_No) ? -1 : $logistic_truck_No;
        return $logistic_truck_No;
    }

    //获取可配送的加工日期
    public function deliveryDate()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date']);
        $logistic_delivery_date = $param['logistic_delivery_date']??'';

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();//当前操作用户

        $Order = new Order();
        $logistic_truck_No = $this->getlogistic_truck_No($businessId,$user_id);
        $res = $Order->getDriverDeliveryDate($businessId,$logistic_truck_No,$logistic_delivery_date);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$res);
    }

    /**
     * 获取司机订单信息
     * @return \think\response\Json
     */
    public function driverOrder()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','o_sort','o_sort_type']);
        $param['o_sort'] = $param['o_sort']??0;//排序字段
        $param['o_sort_type'] = $param['o_sort_type']??1;//1-正向排序 2-反向排序

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();//当前操作用户

        $Order = new Order();

        $logistic_truck_No = $this->getlogistic_truck_No($businessId,$user_id);
        //获取对应日期的总橡树和已完成的箱数
        $box_count = $Order->getDriverOrderCount($businessId,$param['logistic_delivery_date'],$logistic_truck_No,1);
        //获取对应日期的总的订单数和已完成的订单数
        $all_order_count = $Order->getDriverOrderCount($businessId,$param['logistic_delivery_date'],$logistic_truck_No,2);
        //获取对应日期的加工订单
        $order = $Order->getDriverOrderList($param['logistic_delivery_date'],$businessId,$logistic_truck_No,$param['o_sort'],$param['o_sort_type']);
        $data = [
            'box_count' => $box_count,
            'all_order_count' => $all_order_count,
            'order' => $order,
        ];
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    /**
     * 修改收货状态
     */
    public function changeReceiptStatus()
    {
        //接收参数
        $param = $this->request->only(['orderId']);

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();//当前操作用户

        $logistic_truck_No = $this->getlogistic_truck_No($businessId,$user_id);

        //1.查询该订单是否存在
        $info = Order::is_exist(['business_userId'=>$businessId,'logistic_truck_No'=>$logistic_truck_No,'orderId'=>$param['orderId']]);
        if (!$info) {
            return show(config('status.code')['param_error']['code'], config('status.code')['param_error']['msg']);
        }
        //2.查询司机的状态是否正确
        if($info['driver_receipt_status'] == 1){
            return show(config('status.code')['driver_receipt_status_error']['code'], config('status.code')['driver_receipt_status_error']['msg']);
        }
        //3.修改订单状态
        Order::getUpdate(['id' => $info['id']],['driver_receipt_status' => 1]);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
    }

    /**
     * 获取订单详情
     */
    public function orderDetail()
    {
        //接收参数
        $param = $this->request->only(['orderId']);
        $orderId = $param['orderId']??'';

        $info = Order::getOne(['orderId' => $orderId],"orderId,coupon_status,displayName,first_name,last_name,address,receipt_picture,phone,'' as picture");
        if($info){
            $info['name'] = $info['displayName'] ?: $info['first_name'].' '.$info['last_name'];
        }
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$info);
    }

    /**
     * 文件上传
     *
     * @return \think\Response
     */
    public function uploadImage()
    {
        //接收表单上传的文件
        $files = request()->file();

        //对上传文件进行验证
        $businessId = $this->getBusinessId();
        $thumb = "logistic_pic/$businessId/";
        $date = date('Ymd');
        //判断文件是否存在，不存在就创建
        file_exists($thumb.$date)?'':mkdir($thumb.$date,0777,true);
        try {
            validate(['picture'=>'fileExt:jpg,png,jpeg'])->check($files);
            //将原图上传保存
            $picture = \think\facade\Filesystem::disk('public')->putFile($thumb,request()->file('picture'));
        }catch (\think\exception\ValidateException $e){
            return show(config('status.code')['picture_error']['code'],$e->getMessage());
        }

        //组装路径（文件名）
        $time = md5((string)time()).'.'.request()->file('picture')->extension();
        //组装文件路径
        $file=$thumbPath = $thumb.$date.'/'.$time;
        //对图片进行压缩
        try {
            //找到原图对原图进行压缩处理
            $image = \think\Image::open($picture);
            $image->thumb(100,100)->save($thumbPath);
            //并将原图删除
            if(file_exists($picture)){
                unlink($picture);
            }
            return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$file);
        }catch (\Exception $exception){
            return show(config('status.code')['picture_error']['code'],$exception->getMessage());
        }
    }

    /**
     * 确定送到货物
     */
    public function confirmOrderFinish()
    {
        //接收参数
        $param = $this->request->only(['orderId','receipt_picture']);
        $validate = new IndexValidate();
        if (!$validate->scene('confirmOrderFinish')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        $info = Order::getOne(['orderId' => $param['orderId']],"coupon_status,receipt_picture,driver_receipt_status");
        if(empty($info)){
            return show(config('status.code')['param_error']['code'], config('status.code')['param_error']['msg']);
        }
        if($info['driver_receipt_status'] == 0){
            return show(config('status.code')['finish_order_status_error']['code'], config('status.code')['finish_order_status_error']['msg']);
        }
        if($info['coupon_status'] != 'c01'){
            return show(config('status.code')['finish_order_error']['code'], config('status.code')['finish_order_error']['msg']);
        }
        //更改订单状态
        Order::getUpdate(['orderId' => $param['orderId']],[
            'coupon_status' => 'b01',
            'receipt_picture' => $param['receipt_picture']
        ]);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$info);
    }
}
