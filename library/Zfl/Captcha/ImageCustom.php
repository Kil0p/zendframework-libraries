<?php
Namespace Zfl\Captcha;

use Zend\Captcha\Image;
use Zend\Captcha\Exception;
use Zend\Stdlib\ErrorHandler;

class ImageCustom extends Image {

    private $colorRGB = array(
        'r' => 255,
        'g' => 255,
        'b' => 255,
    );

    public function getColorRGB(){
        return $this->colorRGB;
    }

    public function setColorRGB(array $color){
        $this->colorRGB = $color;

        return $this;
    }

    public function generateImage($id, $word){
        $font = $this->getFont();

        if (empty($font)) {
            throw new Exception\NoFontProvidedException('Image CAPTCHA requires font');
        }

        $w     = $this->getWidth();
        $h     = $this->getHeight();
        $fsize = $this->getFontSize();

        $imgFile   = $this->getImgDir() . $id . $this->getSuffix();

        if (empty($this->startImage)) {
            $img = imagecreatetruecolor($w, $h);
        } else {
            // Potential error is change to exception
            ErrorHandler::start();
            $img   = imagecreatefrompng($this->startImage);
            $error = ErrorHandler::stop();
            if (!$img || $error) {
                throw new Exception\ImageNotLoadableException(
                    "Can not load start image '{$this->startImage}'", 0, $error
                );
            }
            $w = imagesx($img);
            $h = imagesy($img);
        }
        $colorRGB = $this->getColorRGB();
        $textColor = imagecolorallocate($img, 0, 0, 0);
        $bgColor   = imagecolorallocate($img, $colorRGB['r'], $colorRGB['g'], $colorRGB['b']);
        imagefilledrectangle($img, 0, 0, $w-1, $h-1, $bgColor);
        $textbox = imageftbbox($fsize, 0, $font, $word);
        $x = ($w - ($textbox[2] - $textbox[0])) / 2;
        $y = ($h - ($textbox[7] - $textbox[1])) / 2;
        imagefttext($img, $fsize, 0, $x, $y, $textColor, $font, $word);

        // generate noise
        for ($i=0; $i < $this->dotNoiseLevel; $i++) {
            imagefilledellipse($img, mt_rand(0, $w), mt_rand(0, $h), 2, 2, $textColor);
        }
        for ($i=0; $i < $this->lineNoiseLevel; $i++) {
            imageline($img, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), $textColor);
        }

        // transformed image
        $img2     = imagecreatetruecolor($w, $h);
        $bgColor = imagecolorallocate($img2, $colorRGB['r'], $colorRGB['g'], $colorRGB['b']);
        imagefilledrectangle($img2, 0, 0, $w-1, $h-1, $bgColor);

        // apply wave transforms
        $freq1 = $this->randomFreq();
        $freq2 = $this->randomFreq();
        $freq3 = $this->randomFreq();
        $freq4 = $this->randomFreq();

        $ph1 = $this->randomPhase();
        $ph2 = $this->randomPhase();
        $ph3 = $this->randomPhase();
        $ph4 = $this->randomPhase();

        $szx = $this->randomSize();
        $szy = $this->randomSize();

        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $sx = $x + (sin($x*$freq1 + $ph1) + sin($y*$freq3 + $ph3)) * $szx;
                $sy = $y + (sin($x*$freq2 + $ph2) + sin($y*$freq4 + $ph4)) * $szy;

                if ($sx < 0 || $sy < 0 || $sx >= $w - 1 || $sy >= $h - 1) {
                    continue;
                } else {
                    $color   = (imagecolorat($img, $sx, $sy) >> 16)         & 0xFF;
                    $colorX  = (imagecolorat($img, $sx + 1, $sy) >> 16)     & 0xFF;
                    $colorY  = (imagecolorat($img, $sx, $sy + 1) >> 16)     & 0xFF;
                    $colorXY = (imagecolorat($img, $sx + 1, $sy + 1) >> 16) & 0xFF;
                }

                if ($color == 255 && $colorX == 255 && $colorY == 255 && $colorXY == 255) {
                    // ignore background
                    continue;
                } elseif ($color == 0 && $colorX == 0 && $colorY == 0 && $colorXY == 0) {
                    // transfer inside of the image as-is
                    $newcolor = 0;
                } else {
                    // do antialiasing for border items
                    $fracX  = $sx - floor($sx);
                    $fracY  = $sy - floor($sy);
                    $fracX1 = 1 - $fracX;
                    $fracY1 = 1 - $fracY;

                    $newcolor = $color   * $fracX1 * $fracY1
                    + $colorX  * $fracX  * $fracY1
                    + $colorY  * $fracX1 * $fracY
                    + $colorXY * $fracX  * $fracY;
                }

                imagesetpixel($img2, $x, $y, imagecolorallocate($img2, $newcolor, $newcolor, $newcolor));
            }
        }

        // generate noise
        for ($i=0; $i<$this->dotNoiseLevel; $i++) {
            imagefilledellipse($img2, mt_rand(0, $w), mt_rand(0, $h), 2, 2, $textColor);
        }

        for ($i=0; $i<$this->lineNoiseLevel; $i++) {
            imageline($img2, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), $textColor);
        }

        imagepng($img2, $imgFile);
        imagedestroy($img);
        imagedestroy($img2);
    }
}