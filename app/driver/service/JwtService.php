<?php
declare (strict_types = 1);

namespace app\driver\service;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Class JwtService
 * @package app\api\service
 * @describe: 单例 一次请求中所有出现jwt的地方都是一个用户
 */
class JwtService extends \think\Service
{

    // jwt token
    private $token;

    // jwt 过期时间
    private $expTime = 7*24*3600;         // jwt的过期时间，这个过期时间必须要大于签发时间

    // claim iss
    private $iss = 'cityb2b_member_system_iss';   // jwt签发组织

    // claim aud
    private $aud = 'cityb2b_member_system_aud';// 签发作者

    // claim businessId
    private $businessId;

    // claim uid
    private $uid;

    // key
    private $key = 'cityb2b_member_system_key';//jwt的签发密钥，验证token的时候需要用到

    // decode token
    private $decodeToken;

    // 单例模式JwtAuth句柄
    private static $instance;

    /**
     * @return JwtService
     * @describe:获取JwtAuth的句柄
     */
    public static function getInstance()
    {
        if(is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * JwtService constructor.
     * 私有化构造函数
     */
    private function __construct()
    {

    }

    /**
     *
     * @describe:私有化clone函数
     */
    private function __clone()
    {
        // TODO: Implement __clone() method.
    }

    /**
     * @return string
     * @describe:获取token
     */
    public function getToken()
    {
        return (string)$this->token;
    }

    /**
     * @param $token
     * @return $this
     * @describe:设置token
     */
    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @param $uid
     * @return $this
     * @describe:设置uid
     */
    public function setBusinessId($businessId)
    {
        $this->businessId = $businessId;
        return $this;
    }

    /**
     * @param $uid
     * @return $this
     * @describe:设置uid
     */
    public function setUid($uid)
    {
        $this->uid = $uid;
        return $this;
    }

    /**
     * @return mixed
     * @describe:获取uid
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * @return $this
     * @describe:编码jwt token
     */
    public function encode()
    {
        $time = time(); //签发时间
        $token_arr = array(
            "iss" => $this->iss, //签发组织
            "aud" => $this->aud, //签发作者
            "iat" => $time,      // jwt的签发时间
            "nbf" => $time,      // 定义在什么时间之前，该jwt都是不可用的.
            "exp" => $time+$this->expTime,     // jwt的过期时间，这个过期时间必须要大于签发时间
             "data"=>[           //记录的userid的信息，这里是自已添加上去的，如果有其它信息，可以再添加数组的键值对
                'uid'=>$this->uid,
                'businessId'=>$this->businessId,
            ],
        );
        $this->token = JWT::encode($token_arr, $this->key, 'HS256');
        return $this;
    }


    /**
     * @return \Lcobucci\JWT\Token
     * @describe:解码jwt token
     */
    public function decode()
    {
        if(!$this->decodeToken){
            JWT::$leeway = 60; // $leeway in seconds
            $this->decodeToken = JWT::decode($this->token, new Key($this->key,'HS256'));
        }
        return $this->decodeToken;
    }

    /**
     * @return bool
     * @describe:验证 token
     */
    public function validate()
    {
        $data = new ValidationData(); // It will use the current time to validate (iat, nbf and exp)
        $data->setIssuer($this->iss);
        $data->setAudience($this->aud);
        $data->setId($this->uid);

        return $this->decode()->validate($data);
    }

    /**
     * @return bool
     * @describe:verify token
     */
    public function verify()
    {
        $signer = new Sha256();
        return $this->decode()->verify($signer, $this->secrect);
    }

}
