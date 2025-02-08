<?php

namespace Esoftdream;

use Exception;

use function extension_loaded;
use function file_exists;
use function function_exists;
use function imagecolorallocate;
use function imagecreatetruecolor;
use function imagedestroy;
use function imagefilledellipse;
use function imagefilledrectangle;
use function imageftbbox;
use function imagefttext;
use function imageline;
use function imagepng;
use function mt_rand;
use function random_bytes;
use function session;

/**
 * Image-based CAPTCHA class
 */
class Image
{
    protected string $imgDir = WRITEPATH . 'cache/';
    protected string $font = './font/mangalb.ttf';
    protected int $fontSize = 24;
    protected int $width = 200;
    protected int $height = 50;
    protected string $suffix = ".png";
    protected int $dotNoiseLevel = 100;
    protected int $lineNoiseLevel = 5;
    protected int $expiration = 600;
    protected ?string $word = null;
    protected int $wordLength = 8;
    protected ?string $id = null;

    /**
     * Constructor
     *
     * @throws Exception If required GD functions are missing.
     */
    public function __construct()
    {
        if (!extension_loaded("gd")) {
            throw new Exception("GD extension is required for CAPTCHA generation.");
        }
        if (!function_exists("imagepng")) {
            throw new Exception("PNG support in GD is required.");
        }
        if (!function_exists("imageftbbox")) {
            throw new Exception("FreeType support in GD is required.");
        }

        helper('text');
    }

    /**
     * Set the word length for the CAPTCHA
     *
     * @param int $wordLength The length of the generated word.
     * @return $this
     */
    public function setWordLength(int $wordLength)
    {
        $this->wordLength = $wordLength;
        return $this;
    }

    /**
     * Set the width of the CAPTCHA image
     *
     * @param int $width The image width in pixels.
     * @return $this
     */
    public function setWidth(int $width)
    {
        $this->width = $width;
        return $this;
    }

    /**
     * Set the height of the CAPTCHA image
     *
     * @param int $height The image height in pixels.
     * @return $this
     */
    public function setHeight(int $height)
    {
        $this->height = $height;
        return $this;
    }

    /**
     * Get the image directory path
     *
     * @return string The directory where CAPTCHA images are stored.
     */
    public function getImgDir(): string
    {
        return $this->imgDir;
    }

    /**
     * Get the file suffix for CAPTCHA images
     *
     * @return string The file extension used for CAPTCHA images.
     */
    public function getSuffix(): string
    {
        return $this->suffix;
    }

    /**
     * Generate a CAPTCHA image
     *
     * @return string The unique ID of the generated CAPTCHA.
     */
    public function generate(): string
    {
        $id    = $this->generateId();
        $tries = 5;

        while ($tries-- && file_exists($this->imgDir . $id . $this->suffix)) {
            $id = $this->generateRandomId();
            $this->setId($id);
        }

        $this->generateImage($id, $this->getWord());

        return $id;
    }

    /**
     * Generate a unique ID and set the CAPTCHA word
     *
     * @return string The generated unique ID.
     */
    private function generateId(): string
    {
        $id = $this->generateRandomId();
        $this->setId($id);

        $word = random_string('alnum', $this->wordLength);
        $this->setWord($word);

        return $id;
    }

    /**
     * Set the CAPTCHA ID
     *
     * @param string $id The unique ID for the CAPTCHA.
     */
    protected function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * Get the stored CAPTCHA word
     *
     * @return string|null The stored CAPTCHA word.
     */
    public function getWord(): ?string
    {
        return $this->word;
    }

    /**
     * Set the CAPTCHA word and store it in session
     *
     * @param string $word The word to store.
     */
    protected function setWord(string $word): void
    {
        session()->set('word', $word);
        $this->word = $word;
    }

    /**
     * Generate a random unique ID
     *
     * @return string The generated unique ID.
     */
    protected function generateRandomId(): string
    {
        return md5(random_bytes(32));
    }

    /**
     * Generate the CAPTCHA image
     *
     * @param string $id The CAPTCHA ID.
     * @param string $word The CAPTCHA text.
     * @throws Exception If no font is specified.
     */
    protected function generateImage(string $id, string $word): void
    {
        $font = $this->font;

        if (empty($font)) {
            throw new Exception('Image CAPTCHA requires font');
        }

        $w     = $this->width;
        $h     = $this->height;
        $fsize = $this->fontSize;

        $imgFile = $this->getImgDir() . $id . $this->getSuffix();
        $img = imagecreatetruecolor($w, $h);


        $textColor = imagecolorallocate($img, 0, 0, 0);
        $bgColor   = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, $w - 1, $h - 1, $bgColor);
        $textbox = imageftbbox($fsize, 0, $font, $word);
        $x       = ($w - ($textbox[2] - $textbox[0])) / 2;
        $y       = ($h - ($textbox[7] - $textbox[1])) / 2;
        $x       = (int) $x;
        $y       = (int) $y;
        imagefttext($img, $fsize, 0, $x, $y, $textColor, $font, $word);

        // generate noise
        for ($i = 0; $i < $this->dotNoiseLevel; $i++) {
            imagefilledellipse($img, mt_rand(0, $w), mt_rand(0, $h), 2, 2, $textColor);
        }
        for ($i = 0; $i < $this->lineNoiseLevel; $i++) {
            imageline($img, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), $textColor);
        }

        // transformed image
        $img2    = imagecreatetruecolor($w, $h);
        $bgColor = imagecolorallocate($img2, 255, 255, 255);
        imagefilledrectangle($img2, 0, 0, $w - 1, $h - 1, $bgColor);

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
                $sx = $x + (sin($x * $freq1 + $ph1) + sin($y * $freq3 + $ph3)) * $szx;
                $sy = $y + (sin($x * $freq2 + $ph2) + sin($y * $freq4 + $ph4)) * $szy;
                $sx = (int) $sx;
                $sy = (int) $sy;

                if ($sx < 0 || $sy < 0 || $sx >= $w - 1 || $sy >= $h - 1) {
                    continue;
                } else {
                    $color   = (imagecolorat($img, $sx, $sy) >> 16) & 0xFF;
                    $colorX  = (imagecolorat($img, $sx + 1, $sy) >> 16) & 0xFF;
                    $colorY  = (imagecolorat($img, $sx, $sy + 1) >> 16) & 0xFF;
                    $colorXY = (imagecolorat($img, $sx + 1, $sy + 1) >> 16) & 0xFF;
                }

                if ($color === 255 && $colorX === 255 && $colorY === 255 && $colorXY === 255) {
                    // ignore background
                    continue;
                } elseif ($color === 0 && $colorX === 0 && $colorY === 0 && $colorXY === 0) {
                    // transfer inside of the image as-is
                    $newcolor = 0;
                } else {
                    // do antialiasing for border items
                    $fracX  = $sx - floor($sx);
                    $fracY  = $sy - floor($sy);
                    $fracX1 = 1 - $fracX;
                    $fracY1 = 1 - $fracY;

                    $newcolor = $color * $fracX1 * $fracY1
                              + $colorX * $fracX * $fracY1
                              + $colorY * $fracX1 * $fracY
                              + $colorXY * $fracX * $fracY;
                }

                imagesetpixel($img2, $x, $y, imagecolorallocate(
                    $img2,
                    (int) $newcolor,
                    (int) $newcolor,
                    (int) $newcolor
                ));
            }
        }

        // generate noise
        for ($i = 0; $i < $this->dotNoiseLevel; $i++) {
            imagefilledellipse($img2, mt_rand(0, $w), mt_rand(0, $h), 2, 2, $textColor);
        }

        for ($i = 0; $i < $this->lineNoiseLevel; $i++) {
            imageline($img2, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), $textColor);
        }

        imagepng($img2, $imgFile);
        imagedestroy($img);
        imagedestroy($img2);
    }

    /**
     * Generate random frequency
     *
     * @return float
     */
    protected function randomFreq()
    {
        return mt_rand(700000, 1000000) / 15000000;
    }

    /**
     * Generate random phase for distortion
     *
     * @return float Random phase value.
     */
    protected function randomPhase()
    {
        return mt_rand(0, 3141592) / 1000000;
    }

    /**
     * Generate random character size for distortion
     *
     * @return float|int Random size value.
     */
    protected function randomSize()
    {
        return mt_rand(300, 700) / 100;
    }
}
