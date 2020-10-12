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

namespace linjialiang\captcha;

class CaptchaController
{
    public function index(Captcha $captcha, $codeId)
    {
        return $captcha->create($codeId);
    }
}
