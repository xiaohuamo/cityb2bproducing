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
//拣货员页面-按照订单拣货
Route::get('pickingOrder', 'Picking/index');
//拣货员页面-按照产品拣货
Route::get('picking', 'PickingItem/index');
//司机页面-我的
Route::get('me', 'Driver/me');
//司机页面-收货页面
Route::get('order', 'Driver/order');
//司机页面-确认收货
Route::get('confirmRecept', 'Driver/confirmRecept');
//司机页面-客户查询
Route::get('customerSearch', 'Driver/customerSearch');


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

//拣货员项目路由(按照订单分)--------start
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
//获取配送订单的类目
Route::post('pickCategory', 'Picking/category');
//获取对应类目的订单产品
Route::post('pickCategoryProduct', 'Picking/categoryProduct');
//配货端锁定非加工产品
Route::post('lockNoneProcessedProduct', 'Picking/lockNoneProcessedProduct');
//配货端锁定非加工产品-获取锁定结果
Route::post('lockNoneProcessedProductResult', 'Picking/lockNoneProcessedProductResult');
//配货端解锁非加工产品
Route::post('unlockNoneProcessedProduct', 'Picking/unlockNoneProcessedProduct');
//配货端非加工产品总配货状态
Route::post('changeNoneProcessedProductStatus', 'Picking/changeNoneProcessedProductStatus');
//配货端非加工产品日志
Route::post('noneProcessedLogData', 'Picking/noneProcessedLogData');
//拣货员项目路由(按照订单分)--------end

//拣货员项目路由(按照产品分)--------start
//获取初始化数据
Route::post('pickItemInidata', 'PickingItem/inidata');
//切换司机时获取司机信息
Route::post('changeCate', 'PickingItem/changeCate');
//获取订单明细信息
Route::post('productItemOrderDetailList', 'PickingItem/productItemOrderDetailList');
//锁定订单
Route::post('lockProductItem', 'PickingItem/lockProductItem');
//解锁订单
Route::post('unlockProductItem', 'PickingItem/unlockProductItem');
//获取锁定结果
Route::post('lockProductItemResult', 'PickingItem/lockProductItemResult');
//修改加工明细单状态
Route::post('pickChangeProductItemStatus', 'PickingItem/pickChangeProductItemStatus');
//批量修改加工明细单状态
Route::post('pickAllChangeProductItemStatus', 'PickingItem/pickAllChangeProductItemStatus');
//修改数量
Route::post('pickEditProductItemBuyingQuantity', 'PickingItem/editBuyingQuantity');
//获取锁定结果
Route::post('pickProductItemLogData', 'PickingItem/pickProductItemLogData');
//修改箱数
Route::post('pickItemEditBoxNumber', 'PickingItem/editBoxNumber');
//修改箱数
Route::post('orderBoxsNumber', 'PickingItem/orderBoxsNumber');
//拣货员项目路由(按照产品分)--------end
Route::post('testOrderBox', 'Login/test');


//司机端项目路由--------start
//登录信息
Route::post('driverLoginInfo', 'Driver/loginInfo');
//获取司机配送日期
Route::post('driverDeliveryDate', 'Driver/deliveryDate');
//获取订单
Route::post('driverOrder', 'Driver/driverOrder');
//修改订单状态
Route::post('changeReceiptStatus', 'Driver/changeReceiptStatus');
//获取订单详情
Route::post('driverOrderDetail', 'Driver/orderDetail');
//图片上传
Route::post('uploadImage', 'Driver/uploadImage');
//确定货物送到
Route::post('confirmOrderFinish', 'Driver/confirmOrderFinish');
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
