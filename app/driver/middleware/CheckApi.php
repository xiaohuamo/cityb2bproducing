<?php


namespace app\driver\middleware;


use app\driver\service\JwtService;
use think\facade\Request;

class CheckApi
{

    /**
     * @param $request
     * @param \Closure $next
     * @return mixed
     * @describe:校验token
     */
    public function handle($request, \Closure $next)
    {
        $token = Request::header('Authorization');
        try {
            if ($token) {
                if (count(explode('.', $token)) <> 3) {
                    return show(config('status.code')['token_error']['code'], config('status.code')['token_error']['msg']);
                }
                $jwtAuth = JwtService::getInstance();
                $decoded = $jwtAuth->setToken($token)->decode();
                //验证token
                $arr = (array)$decoded;
                if(isset($arr['data'])){
                    $user_info = (array)$arr['data'];
                    $request->businessId = $user_info['businessId'];
                    $request->user_id = $user_info['uid'];
                    return $next($request);
                }else{
                    return show(config('status.code')['token_error']['code'], config('status.code')['token_error']['msg']);
                }
            } else {
                return show(config('status.code')['token_error']['code'], config('status.code')['token_error']['msg']);
            }
        } catch(Exception $e) { //其他错误
            return show(config('status.code')['token_error']['code'], $e->getMessage());
        }
    }
}
