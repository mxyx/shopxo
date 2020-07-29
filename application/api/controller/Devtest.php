<?php
// +----------------------------------------------------------------------
// | ShopXO 国内领先企业级B2C免费开源电商系统
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2019 http://shopxo.net All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Devil
// +----------------------------------------------------------------------
namespace app\api\controller;

use think\Db;
use app\service\ResourcesService;
use app\service\RegionService;

/**
 * 开发测试
 * @author   Devil
 * @blog     http://gong.gg/
 * @version  0.0.1
 * @datetime 2016-12-01T21:51:08+0800
 */
class Devtest extends Common
{
    /**
     * 构造方法
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-11-30
     * @desc    description
     */
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * 附件初始化 1.6升级运行
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  0.0.1
     * @datetime 2017-02-22T16:50:32+0800
     */
    public function Index()
    {
        if(input('pwd') != 'shopxo520')
        {
            die('非法访问');
        }

        $path_all = [
            'video' => __MY_ROOT_PUBLIC__.'static/upload/video/',
            'file' => __MY_ROOT_PUBLIC__.'static/upload/file/',
            'image' => __MY_ROOT_PUBLIC__.'static/upload/images/',
        ];
        foreach($path_all as $type=>$path)
        {
            $path = GetDocumentRoot() . (substr($path, 0, 1) == "/" ? "":"/") . $path;
            $handle = opendir($path);
            while(false !== ($file = readdir($handle)))
            {
                if($file != 'index.html' && $file != '.' && $file != '..' && substr($file, 0, 1) != '.')
                {
                    $ret = ResourcesService::AttachmentDiskFilesToDb($file);
                    if(isset($ret['msg']))
                    {
                        echo $ret['msg'];
                    }
                }
            }
        }
    }

    /**
     * 订单地址拆分到新的表，1.7升级1.8升级运行
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-12-13
     * @desc    description
     */
    public function OrderAddress()
    {
        if(input('pwd') != 'shopxo520')
        {
            die('非法访问');
        }
        
        // 状态
        $success = 0;
        $fail = 0;

        // 获取数据
        // 一次处理100条
        $prefix = config('database.prefix');
        $field = 'id, user_id, receive_address_id, receive_name, receive_tel, receive_province, receive_city, receive_county, receive_address';
        $sql = 'SELECT '.$field.' FROM '.$prefix.'order WHERE `id` NOT IN (SELECT `order_id` FROM '.$prefix.'order_address) LIMIT 500';
        $result = Db::query($sql);
        if(!empty($result))
        {
            foreach($result as $v)
            {
                $province_name = RegionService::RegionName($v['receive_province']);
                $city_name = RegionService::RegionName($v['receive_city']);
                $county_name = RegionService::RegionName($v['receive_county']);
                $data = [
                    'order_id'          => $v['id'],
                    'user_id'           => $v['user_id'],
                    'address_id'        => $v['receive_address_id'],
                    'name'              => $v['receive_name'],
                    'tel'               => $v['receive_tel'],
                    'province'          => $v['receive_province'],
                    'city'              => $v['receive_city'],
                    'county'            => $v['receive_county'],
                    'address'           => $v['receive_address'],
                    'province_name'     => empty($province_name) ? '' : $province_name,
                    'city_name'         => empty($city_name) ? '' : $city_name,
                    'county_name'       => empty($county_name) ? '' : $county_name,
                    'add_time'          => time(),
                ];
                if(Db::name('OrderAddress')->insert($data))
                {
                    $success++;
                } else {
                    $fail++;
                }
            }
        }
        echo 'count:'.count($result).', success:'.$success.', fail:'.$fail;
    }

    /**
     * 商品库存初始化
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-07-29
     * @desc    description
     */
    public function GoodsInventoryHandle()
    {
        $warehouse_id = 1;
        $warehouse = Db::name('Warehouse')->where(['id'=>$warehouse_id])->find();
        if(empty($warehouse))
        {
            $data = [
                'name'          => '默认仓库',
                'is_default'    => 1,
                'add_time'      => time(),
            ];
            $warehouse_id = Db::name('Warehouse')->insertGetId($data);
        }
    }

    /**
     * 支付日志处理
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-07-26
     * @desc    description
     */
    public function PayLogHandle()
    {
        if(input('pwd') != 'shopxo520')
        {
            die('非法访问');
        }

        // 状态
        $success = 0;
        $fail = 0;


        // 获取日志
        $data = Db::name('PayLog')->where(['is_handle'=>0])->limit(0, 500)->select();
        if(!empty($data))
        {
            $business_type_list = [
                0 => '默认',
                1 => '订单',
                2 => '充值',
                3 => '提现',
            ];
            foreach($data as $v)
            {
                $upd_data = [
                    'is_handle'     => 1,
                    'business_type' => isset($business_type_list[$v['business_type']]) ? $business_type_list[$v['business_type']] : $v['business_type'],
                    'status'        => (empty($v['pay_price']) || $v['pay_price'] <= 0) ? 0 : 1,
                ];

                // 未支付则关闭
                if(empty($v['pay_price']) || $v['pay_price'] <= 0)
                {
                    $upd_data['close_time'] = time();
                }

                // 支付时间
                if(!empty($v['pay_price']) && $v['pay_price'] > 0)
                {
                    $upd_data['pay_time'] = time();
                }

                // 更新操作
                if(Db::name('PayLog')->where(['is_handle'=>0, 'id'=>$v['id']])->update($upd_data))
                {
                    // 新增关联数据
                    if(DB::name('PayLogValue')->insert([
                        'pay_log_id'    => $v['id'],
                        'business_id'   => $v['order_id'],
                        'business_no'   => '',
                        'add_time'      => $v['add_time'],
                    ]))
                    {
                        $success++;
                    } else {
                        $fail++;
                    }
                } else {
                    $fail++;
                }
            }
        }
        echo 'count:'.count($data).', success:'.$success.', fail:'.$fail;
    }
}
?>