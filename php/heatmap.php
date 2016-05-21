<?php
/**
 * Created by PhpStorm.
 * User: mini
 * Date: 2016/05/21
 * Time: 18:24
 */

require realpath(__DIR__ . DIRECTORY_SEPARATOR . 'class.php');

date_default_timezone_set('Asia/Tokyo');

// GDライブラリのチェック
if (!function_exists('imagecreate')) {
    throw new \Exception('GD library is required run this script.');
}

// 準備を行う
$opt = getopt('', [
    'in::', // ファイル入力。未指定の場合には標準入力を使用する
    'out::', // ファイル出力。未指定の場合にはエラー終了する
    'start:', // 出力開始日時
    'width:', // 出力画像幅
    'height:', // 出力画像高さ
]);

if (empty($opt['out'])) {
    throw new \Exception('Output image path parameter(--out) is required.');
}

if (empty($opt['width'])) {
    throw new \Exception('Output image width parameter(--width) is required.');
}

if (empty($opt['height'])) {
    throw new \Exception('Output image height parameter(--height) is required.');
}

if (empty($opt['start'])) {
    throw new \Exception('Output image height parameter(--start) is required.');
}

// JSONファイルの読込を行い、画像を出力する
$input = isset($opt['in']) ? $opt['in'] : 'php://stdin';

$json = json_decode(file_get_contents($input));

$writer = new HeatmapWriter($opt['out'], $opt['start'], $opt['width'], $opt['height']);
foreach ($json->entries as $e) {
    $writer->put($e);
}

$writer->writeFile();


class HeatmapWriter
{
    private $path;

    private $image;
    private $width;
    private $height;

    private $firstTime = false;
    private $scaleArray = [];
    private $maxValue = 0;

    public function __construct($path, $start, $width, $height)
    {
        if (empty($path)) {
            throw new \Exception('Invalid path exception.');
        }

        $this->path = $path;
        $this->width = $width;
        $this->height = $height;
        $this->firstTime = $start;

        $this->scaleArray = array_fill(0, $width, 0);

        $this->initializeImage();
    }

    private function initializeImage()
    {
        $this->image = $image = imagecreatetruecolor($this->width, $this->height);
        // imagecolorallocate() の最初のコールで パレットをもとにした画像 (imagecreate() を使用して作成した画像) で背景色がセットされます。
        imagecolorallocate($image, 0, 0, 0);
    }

    /**
     * @param Entry $e
     */
    public function put($e)
    {
        $this->addPoint($e->start, $e->end);
    }

    /**
     *
     */
    public function writeFile()
    {
        foreach (range(0, $this->width) as $x) {
            $color = $this->int2color($this->scaleArray[$x]);

            imagerectangle($this->image, $x, 0, $x + 1, $this->height, $color);
            imagecolordeallocate($this->image, $color);
        }

        imagepng($this->image, $this->path);
        imagedestroy($this->image);
    }

    /**
     * @param $int
     * @return int
     */
    private function int2color($int)
    {
        $scaled = ceil(255 - ($int * 255 / $this->maxValue));
        return imagecolorallocate($this->image, $scaled, $scaled, $scaled);
    }

    /**
     * @param $second
     */
    private function addPoint($second)
    {
        $xFrom = $this->calcLeftPoint($second);
        $xTo = $this->calcLeftPoint($second);

        $x = $xFrom;

        while ($x <= $xTo and $x < $this->width) {
            $this->scaleArray[$x]++;

            $x++;
        }

        $this->scaleArray[$x]++;
        $this->maxValue = max($this->maxValue,$this->scaleArray[$x]);
    }

    private function calcLeftPoint($second)
    {
        return floor(($second - $this->firstTime) *1024 /86400);
    }
}
