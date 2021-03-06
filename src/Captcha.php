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
// | 1、删除类变量的类型声明，以适配低版本 PHP
// | 2、将 session 换成 cache 以适配前后端分离项目
// +----------------------------------------------------------------------

namespace linjialiang\captcha;

use Exception;
use think\Cache;
use think\Config;
use think\Response;

class Captcha
{
    /**
     * @var Config|null
     */
    private $config = null;

    /**
     * @var Cache|null
     */
    private $cache = null;

    // 验证码实例
    private $im = null;
    // 验证码渲染实例
    private $draw = null;

    // 验证码颜色
    protected $color = '';
    // 验证码字符集合
    protected $codeSet = '2345678abcdefhjkmnpqrtuvwxyABCDEFHJKMNPQRTUVWXY';
    // 验证码过期时间（s）
    protected $expire = 1800;
    // 使用中文验证码
    protected $useZh = false;
    // 中文验证码字符串
    protected $zhSet = '天地玄黄宇宙洪荒日月列张寒来暑往秋收冬闰余成岁律吕调阳云腾致雨结为金生丽水玉出昆冈剑号巨珠称夜光果珍李重姜海咸河淡羽翔龙师火帝鸟官人皇始制文字乃服衣裳推位让国有唐吊民伐罪周发汤坐朝问道垂拱平章爱育';
    // 算术验证码
    protected $math = false;
    // 算术验证码字符集合
    protected $mathSet = '0123456789+-*/=';
    // 随机运算符号，支持加法(+)、减法(-)、乘法(*)、除法(/)四则运算，默认执行加法运算
    protected $operators = ['+', '-', '*', '/'];
    // 使用背景图片
    protected $useImgBg = false;
    // 验证码字体大小(px)
    protected $fontSize = 25;
    // 是否画混淆曲线
    protected $useCurve = false;
    // 是否添加杂点
    protected $useNoise = false;
    // 杂点大小
    protected $fontSizeNoise = 20;
    // 验证码图片高度
    protected $imageH = 0;
    // 验证码图片宽度
    protected $imageW = 0;
    // 验证码位数
    protected $length = 5;
    // 验证码字体，不设置随机获取
    protected $fontFamily = '';
    // 背景颜色
    protected $bg = '';

    /**
     * 架构方法 设置参数
     * @access public
     * @param Config $config
     * @param Cache $cache
     */
    public function __construct(Config $config, Cache $cache)
    {
        $this->config = $config;
        $this->cache = $cache;
    }

    /**
     * 配置验证码
     * @param string|null $config
     */
    protected function configure(string $config = null): void
    {
        if (is_null($config)) {
            $config = $this->config->get('captcha', []);
        } else {
            $config = $this->config->get('captcha.' . $config, []);
        }

        foreach ($config as $key => $val) {
            if (property_exists($this, $key)) {
                $this->{$key} = $val;
            }
        }
    }

    /**
     * 创建验证码
     * @return array
     * @throws Exception
     */
    protected function generate(): array
    {
        $bag = '';

        if ($this->math) {
            $y = random_int(1, 9);

            switch ($this->operators ? $this->operators[array_rand($this->operators)] : '+') {
                case '-':
                    $x = random_int(10, 30);
                    $bag = "{$x} - {$y} =";
                    $key = $x - $y;
                    break;
                case '*':
                    $x = random_int(1, 10);
                    $bag = " {$x} * {$y} =";
                    $key = $x * $y;
                    break;
                case '/':
                    $x = mt_rand(1, 10) * $y;
                    $bag = "{$x} / {$y} =";
                    $key = $x / $y;
                    break;
                default:
                    $x = random_int(10, 30);
                    $bag = "{$x} + {$y} =";
                    $key = $x + $y;
                    break;
            }

            $key .= '';
        } else {
            if ($this->useZh) {
                $characters = preg_split('/(?<!^)(?!$)/u', $this->zhSet);
            } else {
                $characters = str_split($this->codeSet);
            }

            for ($i = 0; $i < $this->length; $i++) {
                $bag .= $characters[rand(0, count($characters) - 1)];
            }

            $key = mb_strtolower($bag, 'UTF-8');
        }

        $hash = password_hash($key, PASSWORD_BCRYPT, ['cost' => 10]);

        return [
            'value' => $bag,
            'key'   => $hash,
        ];
    }

    /**
     * 验证验证码是否正确
     * @access public
     * @param string $codeId 用户验证码 ID
     * @param string $code 用户验证码
     * @return bool 用户验证码是否正确
     */
    public function check(string $codeId, string $code): bool
    {
        if (!$this->cache->has('captcha:' . $codeId)) {
            return false;
        }

        $captcha = $this->cache->get('captcha:' . $codeId);
        $key = $captcha['key'];

        $code = mb_strtolower($code, 'UTF-8');

        $res = password_verify($code, $key);

        $this->cache->delete('captcha:' . $codeId);

        return $res;
    }

    /**
     * 输出验证码并把验证码的值保存的session中
     * @access public
     * @param string $codeId
     * @param null|string $config
     * @return Response
     */
    public function create(string $codeId, string $config = null): Response
    {
        if (empty($codeId)) {
            throw new \InvalidArgumentException("Error codeId.");
        }
        $this->configure($config);

        if ($this->math) {
            $this->length = 5;
        }

        // 图片宽(px)
        $this->imageW || $this->imageW = $this->length * $this->fontSize * 1.5 + $this->length * $this->fontSize / 2;
        // 图片高(px)
        $this->imageH || $this->imageH = $this->fontSize * 2.5;

        if ($this->useImgBg) {
            $path = __DIR__ . '/../assets/bgs/';
            $dir = dir($path);
            $bgs = [];
            while (false !== ($file = $dir->read())) {
                if ('.' != $file[0] && substr($file, -4) == '.png') {
                    $bgs[] = $path . $file;
                }
            }
            $dir->close();
            $gb = $bgs[array_rand($bgs)];
            $this->im = new \Imagick($gb);
            $this->im->resizeImage($this->imageW, $this->imageH, \Imagick::FILTER_POINT, 1);
        } else {
            $this->im = new \Imagick();
            // 建立一幅大小为 $this->imageW * $this->imageH 的画布，背景色为 $this->bg
            $this->im->newImage($this->imageW, $this->imageH, $this->bg ? $this->bg : 'rgba(243, 251, 254, 1)');
        }

        $this->draw = new \ImagickDraw();

        // 验证码使用随机字体
        $fontPath = __DIR__ . '/../assets/' . ($this->math ? 'fonts@math' : ($this->useZh ? 'fonts@zh' : 'fonts@default')) . '/';

        if (empty($this->fontFamily)) {
            $dir = dir($fontPath);
            $fontArray = [];
            while (false !== ($file = $dir->read())) {
                if ('.' != $file[0] && preg_match("/^\.(ttf|ttc|otc)$/i", substr($file, -4))) {
                    $fontArray[] = $file;
                }
            }
            $dir->close();
            $this->fontFamily = $fontArray[array_rand($fontArray)];
        }

        // 字体全路径
        $fontttf = $fontPath . $this->fontFamily;
        // 设置验证码字体
        $this->draw->setFont($fontttf);
        // 设置验证码字体大小
        $this->draw->setFontSize($this->fontSize);

        // 获取创建好的验证码
        $generator = $this->generate();

        // 缓存 key
        $this->cache->set('captcha:' . $codeId, [
            'key' => $generator['key'],
        ], $this->expire);

        // 将验证码从字符串转成数组
        $text = $this->useZh ? preg_split('/(?<!^)(?!$)/u', $generator['value']) : str_split($generator['value']);

        // 将验证码字符，挨个画出来
        foreach ($text as $index => $char) {
            $x = $this->fontSize * ($index + 1) * mt_rand(1.2, 1.6) * ($this->math ? 1 : 1.5);
            $y = $this->fontSize + mt_rand(10, 20);
            $angle = $this->math ? 0 : mt_rand(-20, 20);

            // 验证码文字以及坐标
            $this->draw->annotation($x, $y, $char);
            // 验证码文字在x轴上的倾斜角度
            $this->draw->skewX($angle);
            // 验证码文字颜色
            if (!$this->color) {
                $this->draw->setFillColor(
                    'rgb(' .
                    mt_rand(1, 150)
                    . ',' .
                    mt_rand(1, 150)
                    . ',' .
                    mt_rand(1, 150)
                    . ')'
                );
            } else {
                $this->draw->setFillColor($this->color);
            }
        }

        if ($this->useCurve) {
            // 绘干扰线
            $this->writeCurve();
        }

        if ($this->useNoise) {
            // 绘杂点
            $this->writeNoise();
        }

        // 验证码输出格式
        $this->im->setImageFormat("png");
        // 验证码最终输出的样式
        $this->im->drawImage($this->draw);
        ob_start();
        // 输出图像
        echo $this->im;
        $content = ob_get_clean();
        // 销毁imagick对象
        $this->im->destroy();
        return response($content, 200, ['Content-Length' => strlen($content)])->contentType('image/png');
    }

    /**
     * 画一条由两条连在一起构成的随机正弦函数曲线作干扰线(你可以改成更帅的曲线函数)
     *
     *      高中的数学公式咋都忘了涅，写出来
     *        正弦型函数解析式：y=Asin(ωx+φ)+b
     *      各常数值对函数图像的影响：
     *        A：决定峰值（即纵向拉伸压缩的倍数）
     *        b：表示波形在Y轴的位置关系或纵向移动距离（上加下减）
     *        φ：决定波形与X轴位置关系或横向移动距离（左加右减）
     *        ω：决定周期（最小正周期T=2π/∣ω∣）
     *
     */
    protected function writeCurve(): void
    {
        // imagick 画贝塞尔曲线
        $px = $py = 0;
        // 文本随机颜色（验证码颜色）
        $this->draw->setFillColor(
            'rgb(' .
            mt_rand(150, 225)
            . ',' .
            mt_rand(150, 225)
            . ',' .
            mt_rand(150, 225)
            . ')'
        );

        // 曲线前部分
        $A = mt_rand(1, $this->imageH / 2); // 振幅
        $b = mt_rand(-$this->imageH / 4, $this->imageH / 4); // Y轴方向偏移量
        $f = mt_rand(-$this->imageH / 4, $this->imageH / 4); // X轴方向偏移量
        $T = mt_rand($this->imageH, $this->imageW * 2); // 周期
        $w = (2 * M_PI) / $T;

        $px1 = 0; // 曲线横坐标起始位置
        $px2 = mt_rand($this->imageW / 2, $this->imageW * 0.8); // 曲线横坐标结束位置

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if (0 != $w) {
                $py = $A * sin($w * $px + $f) + $b + $this->imageH / 2; // y = Asin(ωx+φ) + b
                $i = (int)($this->fontSize / 5);
                $coord = [];    // 坐标
                while ($i > 0) {
                    $coord[] = ['x' => $px + $i, 'y' => $py + $i];
                    $i--;
                }
                $this->draw->bezier($coord);
            }
        }

        // 曲线后部分
        $A = mt_rand(1, $this->imageH / 2); // 振幅
        $f = mt_rand(-$this->imageH / 4, $this->imageH / 4); // X轴方向偏移量
        $T = mt_rand($this->imageH, $this->imageW * 2); // 周期
        $w = (2 * M_PI) / $T;
        $b = $py - $A * sin($w * $px + $f) - $this->imageH / 2;
        $px1 = $px2;
        $px2 = $this->imageW;

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if (0 != $w) {
                $py = $A * sin($w * $px + $f) + $b + $this->imageH / 2; // y = Asin(ωx+φ) + b
                $i = (int)($this->fontSize / 5);
                $coord = [];    // 坐标
                while ($i > 0) {
                    $coord[] = ['x' => $px + $i, 'y' => $py + $i];
                    $i--;
                }
                $this->draw->bezier($coord);
            }
        }
    }

    /**
     * 画杂点
     * 往图片上写不同颜色的文字
     */
    protected function writeNoise(): void
    {
        $bag = '';
        if ($this->math) {
            $characters = str_split($this->mathSet);
        } elseif ($this->useZh) {
            $characters = preg_split('/(?<!^)(?!$)/u', $this->zhSet);
        } else {
            $characters = str_split($this->codeSet);
        }

        for ($i = 0; $i < 10; $i++) {
            $bag .= $characters[rand(0, count($characters) - 1)];
        }

        $key = mb_strtolower($bag, 'UTF-8');

        $text = $this->useZh ? preg_split('/(?<!^)(?!$)/u', $key) : str_split($key);

        foreach ($text as $index => $char) {
            // 文本字体大小
            $this->draw->setFontSize($this->fontSizeNoise);
            // 文本随机颜色（验证码颜色）
            $this->draw->setFillColor(
                'rgba(' .
                mt_rand(150, 225)
                . ',' .
                mt_rand(150, 225)
                . ',' .
                mt_rand(150, 225)
                . ',' .
                mt_rand(3, 5) / 10
                . ')'
            );
            // 图片上插入随机文本（验证码）
            $this->draw->annotation(mt_rand(-10, $this->imageW), mt_rand(-10, $this->imageH), $char);
        }
    }
}
