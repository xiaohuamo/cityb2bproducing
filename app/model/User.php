<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\Model;

/**
 * @mixin \think\Model
 */
class User extends Model
{
    use modelTrait;
}
