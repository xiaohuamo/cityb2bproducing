<?php
use think\facade\Request;
use think\facade\Route;
//页面路由
//登录
Route::get('login', 'Login/login');
//司机页面-我的
Route::get('me', 'Driver/me');
//司机页面-我的信息
Route::get('myInfo', 'Driver/myInfo');
//司机页面-收货页面
Route::get('order', 'Driver/order');
//司机页面-确认收货
Route::get('confirmRecept', 'Driver/confirmRecept');
//司机页面-客户查询
Route::get('customerSearch', 'Driver/customerSearch');
//司机页面-开始工作
Route::get('startJob', 'Driver/startJob');
//司机页面-工作结束
Route::get('jobDone', 'Driver/jobDone');
//司机页面-返回货物页面
Route::get('returnStock', 'Driver/returnStock');


//接口路由
//司机端项目路由--------start
//登录
Route::post('loginByPassword', 'Login/loginByPassword');
//登录信息
Route::post('driverLoginInfo', 'Driver/loginInfo');
//登录信息
Route::post('uploadBase64Picture', 'Driver/uploadBase64Picture');
//获取司机配送日期
Route::post('driverDeliveryDate', 'Driver/deliveryDate');
//获取订单
Route::post('driverOrder', 'Driver/driverOrder');
//获取导航订单
Route::post('driverNavOrder', 'Driver/driverNavOrder');
//修改订单状态
Route::post('changeReceiptStatus', 'Driver/changeReceiptStatus');
//修改该调度的订单全部状态
Route::post('changeAllReceiptStatus', 'Driver/changeAllReceiptStatus');
//获取订单详情
Route::post('driverOrderDetail', 'Driver/orderDetail');
//图片上传
Route::post('uploadImage', 'Driver/uploadImage');
//修改店铺图片
Route::post('updateStorePicture', 'Driver/updateStorePicture');
//确定货物送到
Route::post('updateOrderRceiptPicture', 'Driver/updateOrderRceiptPicture');
//确定货物送到
Route::post('confirmOrderFinish', 'Driver/confirmOrderFinish');
//确定全部货物送到
Route::post('confirmAllOrderFinish', 'Driver/confirmAllOrderFinish');
//获取司机工作信息
Route::post('truckJobInfo', 'Driver/truckJobInfo');
//开始工作
Route::post('doStartJob', 'Driver/doStartJob');
//结束工作
Route::post('doJobDone', 'Driver/doJobDone');
//获取订单退回信息
Route::post('orderItemDetails', 'Driver/orderItemDetails');
//获取退回原因
Route::post('returnStockReason', 'Driver/returnStockReason');
//退货
Route::post('doReturnStock', 'Driver/doReturnStock');
//司机端项目路由--------end

//miss 路由
Route::miss(function() {
    if(app()->request->isOptions()){
        return \think\Response::create('ok')->code(200)->header([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Authori-zation,Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-Requested-With',
            'Access-Control-Allow-Methods' => 'GET,POST,PATCH,PUT,DELETE,OPTIONS,DELETE',
        ]);
    }else{
        return \think\Response::create()->code(404);
    }
});
