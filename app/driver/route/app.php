<?php
use think\facade\Request;
use think\facade\Route;
//页面路由
//司机页面-我的
Route::get('me', 'Driver/me');


//接口路由
//账号登录
Route::post('loginByPassword', 'Login/loginByPassword');
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
