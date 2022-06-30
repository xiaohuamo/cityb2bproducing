<?php
declare (strict_types = 1);

namespace app\driver\controller;

use think\facade\Db;
use think\Request;
use app\common\service\UploadFileService;
use app\product\validate\IndexValidate;
use app\driver\validate\DriverValidate;
use app\model\{Order, Truck, User, TruckJob, WjCustomerCoupon, OrderReturn, OrderReturnDetailInfo};
use think\route\dispatch\Controller;

class Driver extends Base
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
     * 我的页面
     * @return \think\Response
     */
    public function myInfo()
    {
        // 模板输出
        return $this->fetch('my_info');
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

    /**
     * 开始工作页面
     * @return \think\Response
     */
    public function startJob()
    {
        // 模板输出
        return $this->fetch('start_job');
    }

    /**
     * 工作完成页面
     * @return \think\Response
     */
    public function jobDone()
    {
        // 模板输出
        return $this->fetch('job_done');
    }

    /**
     * 返回货物页面
     * @return \think\Response
     */
    public function returnStock()
    {
        // 模板输出
        return $this->fetch('return_stock');
    }

    //获取用户登录信息
    public function loginInfo(Request $request)
    {
        $truck = new Truck();
        $data = $truck->getTruckInfo($request->businessId,$request->user_id);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    /**
     * 保存base64文件
     * @param FJ_img base64文件
     */
    public function uploadBase64Picture(Request $request)
    {
        $base64_img = $request->param('file');
        $up_dir = '/thumbnails/'.date('y-m').'/avatar';//存放在当前目录的upload文件夹下
        $root_path = app()->getRootPath() . 'public';//存储图片的根目录
        $head_path = $root_path . $up_dir;
        if(!file_exists($head_path)){
            mkdir($head_path,0777,true);
        }
        if(preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_img, $result)){
            $type = $result[2];
            if(in_array($type,array('pjpeg','jpeg','jpg','gif','bmp','png'))){
                $file_name = microtime().'.'.$type;
                $new_file = $head_path.'/'.$file_name;
                if(file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_img)))){
                    $img_path = str_replace('../../..', '', $new_file);
                    $image = \think\Image::open($img_path);
                    // 按照原图的比例生成一个最大为100*100的缩略图并保存为thumb.png
                    $image->thumb(100, 100)->save($new_file);
                    $res = [
                        'dir' => $up_dir.'/'.$file_name,
                    ];
                    //如果有旧图片，并将原图删除
                    $avatar = User::getVal(['id'=>$request->user_id],'avatar');
                    if(!empty($avatar)&&file_exists($root_path.$avatar)){
                        unlink($root_path.$avatar);
                    }
                    //图片上传成功，将图片保存到数据库
                    User::getUpdate(['id'=>$request->user_id],['avatar'=>$res['dir']]);
                    return show(config('status.code')['success']['code'],'success.',$res);
                }else{
                    return show(config('status.code')['upload_error']['code'],config('status.code')['upload_error']['msg']);
                }
            }else{
                //文件类型错误
                return show(config('status.code')['upload_error']['code'],config('status.code')['upload_error']['msg']);
            }
        }else{
            //文件错误
            return show(config('status.code')['upload_error'],'Image upload failed.');
        }
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
    public function deliveryDate(Request $request)
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date']);
        $logistic_delivery_date = $param['logistic_delivery_date']??'';

        $businessId = $request->businessId;
        $user_id = $request->user_id;//当前操作用户

        $Order = new Order();
        $logistic_truck_No = $this->getlogistic_truck_No($businessId,$user_id);
        $res = $Order->getDriverDeliveryDate($businessId,$logistic_truck_No,$logistic_delivery_date);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$res);
    }

    /**
     * 获取司机订单信息
     * @return \think\response\Json
     */
    public function driverOrder(Request $request)
    {
        //接收参数
        $param = $request->only(['logistic_delivery_date','o_sort','o_sort_type','search']);
        $param['o_sort'] = $param['o_sort']??0;//排序字段
        $param['o_sort_type'] = $param['o_sort_type']??1;//1-正向排序 2-反向排序
        $param['search'] = $param['search']??'';//搜索内容
        $businessId = $request->businessId;
        $user_id = $request->user_id;//当前操作用户

        $Order = new Order();

        $logistic_truck_No = $this->getlogistic_truck_No($businessId,$user_id);
        //获取对应日期的总橡树和已完成的箱数
        $box_count = $Order->getDriverOrderCount($businessId,$param['logistic_delivery_date'],$logistic_truck_No,1);
        //获取对应日期的总的订单数和已完成的订单数
        $all_order_count = $Order->getDriverOrderCount($businessId,$param['logistic_delivery_date'],$logistic_truck_No,2);
        //获取对应日期的加工订单
        $order = $Order->getDriverOrderList($param['logistic_delivery_date'],$businessId,$logistic_truck_No,$param['o_sort'],$param['o_sort_type'],$param['search']);
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
    public function changeReceiptStatus(Request $request)
    {
        //接收参数
        $param = $request->only(['orderId']);

        $businessId = $request->businessId;
        $user_id = $request->user_id;//当前操作用户

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
    public function orderDetail(Request $request)
    {
        //接收参数
        $param = $request->only(['orderId']);
        $orderId = $param['orderId']??'';

        $Order = new Order();
        $WjCustomerCoupon = new WjCustomerCoupon();

        $order_info = $Order->getOrderInfo($orderId);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$order_info);
    }

    /**
     * 文件上传
     *
     * @return \think\Response
     */
    public function uploadImage(Request $request)
    {
        //接收表单上传的文件
        $files = request()->file();
        //对上传文件进行验证
        $businessId = $request->businessId;
        $root_path = app()->getRootPath() . 'public';//存储图片的根目录
        $thumb = "/thumbnails/$businessId/".date('y-m')."/photograph";
        //判断文件是否存在，不存在就创建
        file_exists($thumb)?'':mkdir($thumb,0777,true);
        try {
            validate(['picture'=>'fileExt:jpg,png,jpeg'])->check($files);
            //将原图上传保存
            $file_name = md5((string)microtime()).'.'.request()->file('image')->extension();
            $picture = \think\facade\Filesystem::disk('public')->putFileAs($thumb,request()->file('image'),$file_name);
        }catch (\think\exception\ValidateException $e){
            return show(config('status.code')['picture_error']['code'],$e->getMessage());
        }
        //组装路径（文件名）
        $new_file_name = md5((string)microtime()).'.'.request()->file('image')->extension();
        //组装文件路径
        $file=$thumbPath = $thumb.'/'.$new_file_name;
        //对图片进行压缩
        try {
            //找到原图对原图进行压缩处理
            $image = \think\Image::open($picture);
            // 按照原图的比例生成一个最大为200*200的缩略图并保存为thumb.png
            $image->thumb(200,200)->save($root_path.$thumbPath);
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
     * 更新订单收货图片信息
     * @param Request $request
     */
    public function updateOrderRceiptPicture(Request $request)
    {
        //接收参数
        $param = $request->only(['orderId','receipt_picture']);

        $validate = new DriverValidate();
        if (!$validate->scene('updateOrderRceiptPicture')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }
        $info = Order::getOne(['orderId' => $param['orderId']],"coupon_status,receipt_picture,driver_receipt_status");
        if(empty($info)){
            return show(config('status.code')['param_error']['code'], config('status.code')['param_error']['msg']);
        }
        if($info['coupon_status'] != 'c01'){
            return show(config('status.code')['finish_order_error']['code'], config('status.code')['finish_order_error']['msg']);
        }
        //将图片存入数据库
        $res = Order::getUpdate(['orderId' => $param['orderId']],['receipt_picture' => $param['receipt_picture']]);
        if($res !== false){
            if(!empty($info['receipt_picture'])){
                //并将原图删除
                $root_path = app()->getRootPath() . 'public';//存储图片的根目录
                $picture = $root_path.$info['receipt_picture'];
                if(file_exists($picture)){
                    unlink($picture);
                }
            }
            return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
        } else {
            return show(config('status.code')['system_error']['code'],config('status.code')['system_error']['msg']);
        }
    }

    /**
     * 确定送到货物
     */
    public function confirmOrderFinish()
    {
        //接收参数
        $param = $this->request->only(['orderId']);
        $validate = new DriverValidate();
        if (!$validate->scene('confirmOrderFinish')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        $info = Order::getOne(['orderId' => $param['orderId']],"coupon_status,receipt_picture,driver_receipt_status");
        if(empty($info)){
            return show(config('status.code')['param_error']['code'], config('status.code')['param_error']['msg']);
        }
//        if($info['driver_receipt_status'] == 0){
//            return show(config('status.code')['finish_order_status_error']['code'], config('status.code')['finish_order_status_error']['msg']);
//        }
        if($info['coupon_status'] != 'c01'){
            return show(config('status.code')['finish_order_error']['code'], config('status.code')['finish_order_error']['msg']);
        }
        //更改订单状态
        Order::getUpdate(['orderId' => $param['orderId']],[
            'coupon_status' => 'b01',
        ]);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$info);
    }

    //获取司机工作信息
    public function truckJobInfo(Request $request)
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date']);
        $validate = new DriverValidate();
        if (!$validate->scene('truckJobInfo')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }
        $TruckJob = new TruckJob();
        $data = $TruckJob->getTruckJobInfo($request->businessId,$request->user_id,$param['logistic_delivery_date']);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    /**
     * 开始工作接口
     * @param Request $request
     * @return \think\response\Json
     */
    public function doStartJob(Request $request)
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','start_kile_metre', 'start_temprature', 'start_truck_check', 'start_truck_check_content']);
        $param['start_truck_check_content'] = $param['start_truck_check_content']??'';
        $validate = new DriverValidate();
        if (!$validate->scene('doStartJob')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        $businessId = $request->businessId;
        $user_id = $request->user_id;//当前操作用户

        $Truck = new Truck();
        $TruckJob = new TruckJob();
        //1.获取司机信息
        $info = $Truck->getTruckInfo($businessId, $user_id);
        if (empty($info)) {
            return show(config('status.code')['param_error']['code'], config('status.code')['param_error']['msg']);
        }
        //更改订单状态
        $param['truck_id'] = $info['truck_id'];
        $res = $TruckJob->createJobData($param, $businessId, $user_id, 1);
        if ($res) {
            return show(config('status.code')['success']['code'], config('status.code')['success']['msg']);
        } else {
            return show(config('status.code')['system_error']['code'], config('status.code')['system_error']['msg']);
        }
    }

    /**
     * 结束工作接口
     * @param Request $request
     * @return \think\response\Json
     */
    public function doJobDone(Request $request)
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','end_kile_metre','end_temprature','end_truck_check','end_truck_check_content']);
        $param['end_truck_check_content'] = $param['end_truck_check_content']??'';
        $validate = new DriverValidate();
        if (!$validate->scene('doJobDone')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        $businessId = $request->businessId;
        $user_id = $request->user_id;//当前操作用户

        $Truck = new Truck();
        $TruckJob = new TruckJob();
        //1.获取司机信息
        $info = $Truck->getTruckInfo($businessId, $user_id);
        if (empty($info)) {
            return show(config('status.code')['param_error']['code'], config('status.code')['param_error']['msg']);
        }
        //更改订单状态
        $param['truck_id'] = $info['truck_id'];
        $res = $TruckJob->createJobData($param, $businessId, $user_id, 2);
        if ($res) {
            return show(config('status.code')['success']['code'], config('status.code')['success']['msg']);
        } else {
            return show(config('status.code')['system_error']['code'], config('status.code')['system_error']['msg']);
        }
    }

    /**
     * 获取订单明细信息
     */
    public function orderItemDetails(Request $request)
    {
        //接收参数
        $param = $request->only(['orderId']);
        $orderId = $param['orderId']??'';

        $Order = new Order();
        $WjCustomerCoupon = new WjCustomerCoupon();

        $order_info = $Order->getOrderInfo($orderId);
        $detail = $WjCustomerCoupon->getOrderItemDetails($orderId);
        $data = [
            'order_info' => $order_info,
            'order_return_info' => OrderReturn::getOne(['orderId'=>$orderId],'is_approved'),//订单退货信息
            'item_detail' => $detail
        ];
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    /**
     * 获取退回原因
     */
    public function returnStockReason(Request $request)
    {
        $data = config('config.RETURN_REASON');
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    /**
     * 退货
     * @param Request $request
     * @return \think\response\Json
     */
    public function doReturnStock(Request $request)
    {
        //接收参数
        $param = $this->request->only(['orderId','return_data','reasonType']);
//        halt($param);
        $validate = new DriverValidate();
        if (!$validate->scene('doReturnStock')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        $businessId = $request->businessId;
        $user_id = $request->user_id;//当前操作用户

        //1.获取当前订单是否存在
        $order_info = Order::getOne(['orderId'=>$param['orderId']],'id,orderId');
        if(empty($order_info)){
            return show(config('status.code')['param_error']['code'], config('status.code')['param_error']['msg']);
        }

        //2.查询对应的订单明细是否存在
        $item_id_arr = array_column($param['return_data'],'item_order_id');
        $wcc_list = WjCustomerCoupon::getAll([
            ['order_id','=',$param['orderId']],
            ['customer_buying_quantity','>',0],
            ['id','in',$item_id_arr],
        ],'id');
        if(count($wcc_list) != count($item_id_arr)){
            return show(config('status.code')['param_error']['code'], config('status.code')['param_error']['msg']);
        }

        //3.查询该订单是否已退货并审核过了
        $OrderReturn_data = OrderReturn::getOne(['orderId'=>$param['orderId']],'id,is_approved');
        if(!empty($OrderReturn_data) && $OrderReturn_data['is_approved'] == 1){
            return show(config('status.code')['order_return_approved']['code'], config('status.code')['order_return_approved']['msg']);
        }

        //4.获取已申请退货的订单明细
        $ordi_data = [];
        $ordi_item_order_id_arr = [];//存储已退货的id
        $OrderReturn = new OrderReturn();
        $OrderReturnDetailInfo = new OrderReturnDetailInfo();
        if(!empty($OrderReturn_data)){
            $ordi_data = $OrderReturnDetailInfo->getColumn(['order_return_id'=>$OrderReturn_data['id']], '*', 'item_order_id');
            $ordi_item_order_id_arr = array_column($ordi_data,'item_order_id');
        }
        try{
            Db::startTrans();
            $time = time();
            if (!empty($OrderReturn_data)) {
                $order_return_id = $OrderReturn_data['id'];
            } else {
                //1.插入订单退货数据
                $order_return_id = Db::name('order_return')->insertGetId([
                    'orderId' => $param['orderId'],
                    'returnType' => 1,
                    'gen_date' => $time,
                    'create_userId' => $user_id,
                    'approve_userId' => 0,
                    'approve_date' => 0
                ]);
            }
            //2.插入退货明细数据
            $return_data = [];
            foreach ($param['return_data'] as $k=>$v){
                $return_data[] = [
                    'order_return_id' => $order_return_id,
                    'item_order_id' => $v['item_order_id'],
                    'return_qty' => $v['return_qty'],
                    'reasonType' => $v['reasonType'],
                    'note' => $v['note']
                ];
            }
            $insert_return_data = $update_return_data = $delete_return_id_arr = [];
            foreach ($return_data as $k=>$v){
                if (in_array($v['item_order_id'],$ordi_item_order_id_arr)) {
                    $v['id'] = $ordi_data[$v['item_order_id']]['id'];
                    $update_return_data[] = $v;
                } else {
                    if($v['return_qty'] > 0){
                        $insert_return_data[] = $v;
                    }
                }
            }
            //判断是否有需要删除的退货信息
            if(!empty($ordi_data)){
                foreach($ordi_data as $k=>$v){
                    if(!in_array($v['item_order_id'],$item_id_arr)){
                        $delete_return_id_arr[] = $v['id'];
                    }
                }
            }
            if(!empty($insert_return_data)){
                Db::name('order_return_detail_info')->insertAll($insert_return_data);
            }
            if(!empty($update_return_data)){
                $OrderReturnDetailInfo->saveAll($update_return_data);
            }
            if(!empty($delete_return_id_arr)){
                $OrderReturnDetailInfo->deleteAll(['id'=>$delete_return_id_arr]);
            }
            Db::commit();
            return show(config('status.code')['success']['code'], config('status.code')['success']['msg']);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return show(config('status.code')['system_error']['code'], $e->getMessage());
        }
    }
}
