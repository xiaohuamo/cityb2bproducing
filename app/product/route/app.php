<?php
use think\facade\Request;
use think\facade\Route;
//页面路由
//登录
Route::get('login', 'Login/index');
Route::get('index', 'Index/index');

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
