<?php
declare (strict_types = 1);

namespace app\common\traits;

trait modelTrait
{
    /**
     * 所有继承该trait的模型，均可使用。
     * 用法：Model::is_exist(条件数组)
     * @param array $where
     * @return mixed    返回模型实例
     */
    static public function is_exist(array $where,string $field='*')
    {
        return self::where($where)->field($field)->find();
    }

    /**
     * 创建单条数据
     * @param array $data
     * @return mixed
     */
    static public function createData(array $data)
    {
        return self::create($data);
    }

    /**
     * 获取单条数据
     * @param array|int $where 查询条件或主键查询
     * @return static
     */
    static public function getOne($where, $field='*')
    {
        return self::where($where)->field($field)->find();
    }

    /**
     * 获取多条数据
     * @param array|int $where 查询条件或主键查询
     * @return static
     */
    static public function getAll($where=[], $field='*')
    {
        return self::where($where)->field($field)->select()->toArray();
    }

    /**
     * 分页获取多条数据
     * @param array|int $where 查询条件或主键查询
     * @return static
     */
    static public function getPage($where=[], $field='*', $start=0,$end=50)
    {
        return self::where($where)->field($field)->limit($start, $end)->select()->toArray();
    }

    /**
     * 删除数据
     * @param array|int $where 查询条件
     * @return static
     */
    static public function deleteAll($where=[])
    {
        return self::where($where)->delete();
    }

    /**
     * 获取单个字段的值
     * @param array|int $where 查询条件
     * @return static
     */
    static public function getVal($where=[], $field='*')
    {
        return self::where($where)->value($field);
    }

    /**
     * 获取总和
     * @param array|int $where 查询条件
     * @return static
     */
    static public function getSum($where=[], $field)
    {
        return self::where($where)->sum($field);
    }
}
