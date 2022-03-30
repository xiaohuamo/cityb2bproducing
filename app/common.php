<?php
// 应用公共文件

use app\common\service\RedisService;

if (! function_exists('show')) {
    /**
     * 通用化API数据格式输出
     * @param $status
     * @param string $message 提示信息
     * @param array $data
     * @param int $httpStatus
     * @return \think\response\Json
     * @author: msrenly <1169656090@qq.com>
     * @describe:封装API数据返回格式
     */
    function show($status = 200, $message = 'success', $data = [])
    {

        $result = [
            "status" => $status,
            "message" => $message,
            "result" => $data
        ];
        return json($result);
    }
}

if (! function_exists('show_arr')) {
    /**
     * 通用化API数据格式输出
     * @param $status
     * @param string $message
     * @param array $data
     * @param int $httpStatus
     * @return \think\response\Json
     * @author: msrenly <1169656090@qq.com>
     * @describe:封装API数据返回数组格式
     */
    function show_arr($status = 200, $message = 'success', $data = [])
    {

        $result = [
            "status" => $status,
            "message" => $message,
            "result" => $data
        ];
        return $result;
    }
}

if (!function_exists('encryptdata')) {
    //md5加密
    function encryptdata($str){
        return md5(config('config.GLOBALS')['KEY_'].$str.config('config.GLOBALS')['_KEY']);
    }
}

if (!function_exists('redis_connect')) {
    //redis连接
    function redis_connect(){
        $config = config('cache.stores')['redis'];
        $config['auth'] = $config['password'];
        $attr = [
            'db_id' => $config['select'],
            'timeout' => $config['timeout'],
        ];
        $res = RedisService::getInstance($config,$attr);
        return $res;
    }
}

if (!function_exists('date_day')) {
    //获取标记时间为哪天
    function date_day($second1, $second2){
        $diffDays = ($second1 - $second2) / 86400;
        switch( $diffDays ) {
            case 0:
                $date_day = "Today";
                break;
            case -1:
                $date_day = "Yesterday";
                break;
            case +1:
                $date_day = "Tomorrow";
                break;
            default:
                $date_day = date('l',$second1);
        }
        return $date_day;
    }
}

if (!function_exists('writeLog')) {
    /**
     * @param $param
     * @param string $file 文件夹
     * @describe:生成日志
     */
    function writeLog($param, $file = '')
    {
        $filename = date("Y_m_d", time());
        $root = app()->getRootPath();
        if (empty($root)) {
            $myfile = $filename . ".txt";
        } else {
            $f = app()->getRootPath() . 'Logs/' . $file . "/";
            if (!is_dir($f)) {
                @mkdir($f, 0777, true);
            }
            $myfile = $root . 'Logs/' . $file . "/" . $filename . ".txt";
        }
        if (is_array($param)) {
            $param = json_encode($param, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE);
        }
        @file_put_contents(
            $myfile,
            "执行日期：" . "\r\n" . date('Y-m-d H:i:s', time()) . ' ' . "\n" . $param . "\r\n",
            FILE_APPEND
        );
    }
}

if (!function_exists('makeNum')) {
    /**
     * @describe:生成编码
     */
    function makeNum()
    {
        $num = date('YmdHis').mt_rand(100000,999999);
        return $num;
    }
}

if (!function_exists('behaviorType')) {
    /**
     * @describe:行为描述
     */
    function behaviorType($type)
    {
        //行为类型 1-上锁 2-解锁 3-处理加工明细单 4-返回继续处理加工明细单 5-修改加工数量 6-添加预加工产品明细订单 7-修改预加工订单数量
        switch ($type){
            case 1: $desc = 'Locked';break;
            case 2: $desc = 'Unlock';break;
            case 3: $desc = 'Processed';break;
            case 4: $desc = 'PeProcess';break;
            case 5: $desc = 'Modify the processing quantity';break;
            case 6: $desc = 'Add planing quantity';break;
            case 7:  $desc = 'Modify planing quantity';break;
            default:$desc = '';
        }
        return $desc;
    }
}

if (! function_exists('parseName')) {
    /**
     * @param $name
     * @param int $type
     * @param bool $ucfirst
     * @return string
     * @author: LuckyHhy <jackhhy520@qq.com>
     * @describe:
     */
    function parseName($name, $type = 0, $ucfirst = true)
    {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name);

            return $ucfirst ? ucfirst($name) : lcfirst($name);
        }
        return strtolower(trim(preg_replace('/[A-Z]/', '_\\0', $name), '_'));
    }
}



