<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
// | 修改说明：
// | 将 session 换成 cache 以适配前后端分离项目
// +----------------------------------------------------------------------

use linjialiang\captcha\facade\Captcha;
use think\Response;

/**
 * @param string $codeId
 * @param string $config
 * @return \think\Response
 */
function captcha($codeId, $config = null): Response
{
    return Captcha::create($codeId, $config);
}

/**
 * @param string $codeId
 * @param string $value
 * @return bool
 */
function captcha_check($codeId, $value)
{
    return Captcha::check($codeId, $value);
}
