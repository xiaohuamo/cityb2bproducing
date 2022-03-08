<?php
use think\facade\Request;
use think\facade\Route;
//页面路由
//登录
Route::get('login', 'Login/index');
Route::get('index', 'Index/index');
Route::get('labelprint', 'Index/labelprint');

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
//test
Route::post('test', 'Index/test');

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
