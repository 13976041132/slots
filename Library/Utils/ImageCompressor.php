<?php
/**
 * 图片压缩类：通过缩放来压缩
 * options:
 *   percent - 缩放比例
 *   width - 缩放后宽度
 *   height - 缩放后高度
 * 如果要保持源图比例，把percent保持为1即可
 * 即使原比例压缩，也可大幅度缩小
 * 结果：可保存、可直接显示
 */

namespace FF\Library\Utils;

class ImageCompressor
{
    private $src;
    private $image;
    private $imageInfo;
    private $options;

    public function __construct($src, $options = array())
    {
        $this->src = $src;
        $this->options = $options;
        $this->compressImage();
    }

    private function compressImage()
    {
        list($width, $height, $type, $attr) = getimagesize($this->src);
        $this->imageInfo = array(
            'width' => $width,
            'height' => $height,
            'type' => image_type_to_extension($type, false),
            'attr' => $attr
        );
        $fun = "imagecreatefrom" . $this->imageInfo['type'];
        $image = $fun($this->src);
        if (!empty($this->options['width'])) {
            $percent = $this->options['width'] / $width;
        } elseif (!empty($this->options['height'])) {
            $percent = $this->options['height'] / $height;
        } elseif (!empty($this->options['height'])) {
            $percent = $this->options['percent'];
        } else {
            $percent = 1;
        }
        $newWidth = $this->options['width'] ?: round($width * $percent);
        $newHeight = $this->options['height'] ?: round($height * $percent);
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        $this->image = $newImage;
    }

    public function showImage()
    {
        header('Content-Type: image/' . $this->imageInfo['type']);
        $func = "image" . $this->imageInfo['type'];
        $func($this->image);
    }

    public function saveImage($dstImgName)
    {
        if (empty($dstImgName)) return;

        //如果目标图片名有后缀就用目标图片扩展名，如果没有，则用源图的扩展名
        $allowExts = ['.jpg', '.jpeg', '.png', '.bmp', '.wbmp', '.gif'];
        $dstExt = strrchr($dstImgName, ".");
        $sourceExt = strrchr($this->src, ".");
        if (!empty($dstExt)) $dstExt = strtolower($dstExt);
        if (!empty($sourceExt)) $sourceExt = strtolower($sourceExt);
        //有指定目标名扩展名
        if (!empty($dstExt) && in_array($dstExt, $allowExts)) {
            $dstName = $dstImgName;
        } elseif (!empty($sourceExt) && in_array($sourceExt, $allowExts)) {
            $dstName = $dstImgName . $sourceExt;
        } else {
            $dstName = $dstImgName . $this->imageInfo['type'];
        }
        $func = "image" . $this->imageInfo['type'];
        $func($this->image, $dstName);
    }

    public function __destruct()
    {
        imagedestroy($this->image);
    }
}