<?php
use think\facade\Request;
use think\facade\Route;
//页面路由
//登录
Route::get('login', 'Login/index');
//生产员页面
Route::get('index', 'Index/index');
//打印标签页面（废弃）
Route::get('labelprint', 'Index/labelprint');
//预生产页面
Route::get('preProduct', 'PreProduct/index');
//拣货员页面
Route::get('picking', 'Picking/index');


//接口路由
//账号登录
Route::post('loginByPassword', 'Login/loginByPassword');
//pincode登录
Route::post('loginByPincode', 'Index/loginByPincode');
//pincode登录
Route::post('loginInfo', 'Index/loginInfo');
//退出登录
Route::post('loginOut', 'Index/loginOut');
//退出pincode登录
Route::post('loginOutPincode', 'Index/loginOutPincode');
//设置pincode
Route::post('setPincode', 'Index/setPincode');
//修改pincode
Route::post('editPincode', 'Index/editPincode');
//获取加工日期
Route::post('deliveryDate', 'Index/deliveryDate');
//获取客户数据
Route::post('customer', 'Index/customer');
//获取司机数据
Route::post('drivers', 'Index/drivers');
//获取初始化数据
Route::post('inidata', 'Index/inidata');
//切换商品时获取对应的数据
Route::post('changeGoods', 'Index/changeGoods');
//切换商品时获取对应的加工明细单
Route::post('productOrderList', 'Index/productOrderList');
//锁定加工状态
Route::post('lockProduct', 'Index/lockProduct');
//获取锁定结果
Route::post('lockProductResult', 'Index/lockProductResult');
//解锁
Route::post('unlockProduct', 'Index/unlockProduct');
//修改加工数量
Route::post('editBuyingQuantity', 'Index/editBuyingQuantity');
//加工状态变更
Route::post('changeProductOrderStatus', 'Index/changeProductOrderStatus');
//获取当前加工产品
Route::post('currentStockProduct', 'Index/currentStockProduct');
//获取一级加工类目
Route::post('category', 'Index/category');
//获取一级加工类目产品信息
Route::post('categoryProduct', 'Index/categoryProduct');
//获取置顶
Route::post('topProduct', 'Index/topProduct');
//设置置顶
Route::post('setTopProduct', 'Index/setTopProduct');
//获取加工日志
Route::post('logData', 'Index/logData');
//test
Route::post('test', 'Index/test');

//预加工项目路由--------start
//获取加工日期
Route::post('prepDeliveryDate', 'PreProduct/deliveryDate');
//获取操作员数据
Route::post('operator', 'PreProduct/operator');
//获取初始化数据
Route::post('prepInidata', 'PreProduct/inidata');
//切换商品时获取对应的数据
Route::post('prepChangeGoods', 'PreProduct/changeGoods');
//切换商品时获取对应的加工明细单
Route::post('prepProductOrderList', 'PreProduct/productOrderList');
//锁定加工状态
Route::post('prepLockProduct', 'PreProduct/lockProduct');
//获取锁定结果
Route::post('prepLockProductResult', 'PreProduct/lockProductResult');
//解锁
Route::post('prepUnlockProduct', 'PreProduct/unlockProduct');
//修改加工数量
Route::post('prepEditBuyingQuantity', 'PreProduct/editBuyingQuantity');
//加工状态变更
Route::post('prepChangeProductOrderStatus', 'PreProduct/changeProductOrderStatus');
//获取当前加工产品
Route::post('prepCurrentStockProduct', 'PreProduct/currentStockProduct');
//设置预加工
Route::post('setProductPlaning', 'PreProduct/setProductPlaning');
//添加预加工订单
Route::post('addOrderProductPlaning', 'PreProduct/addOrderProductPlaning');
//获取加工日志
Route::post('prepLogData', 'PreProduct/logData');
//添加预加工数量日志记录
Route::post('addProcessQuantityLog', 'PreProduct/addProcessQuantityLog');
//获取预加工数量日志记录
Route::post('processQuantityLog', 'PreProduct/processQuantityLog');
//预加工项目路由--------end

//拣货员项目路由--------start
//获取日期
Route::post('pickDeliveryDate', 'Picking/deliveryDate');
//获取初始化数据
Route::post('pickInidata', 'Picking/inidata');
//切换司机时获取司机信息
Route::post('changeTruck', 'Picking/changeTruck');
//获取订单明细信息
Route::post('productOrderDetailList', 'Picking/productOrderDetailList');
//锁定订单
Route::post('lockOrder', 'Picking/lockOrder');
//解锁订单
Route::post('unlockOrder', 'Picking/unlockOrder');
//获取锁定结果
Route::post('pickLockProductResult', 'Picking/lockProductResult');
//获取锁定结果
Route::post('pickChangeProductOrderStatus', 'Picking/changeProductOrderStatus');
//获取锁定结果
Route::post('pickLogData', 'Picking/logData');
//修改数量
Route::post('pickEditBuyingQuantity', 'Picking/editBuyingQuantity');
//拣货员项目路由--------end

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
