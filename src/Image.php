<?php

namespace Esoftdream;

use Exception;

class Image
{
    protected string $imgDir;
    protected string $font;
    protected int $fontSize = 24;
    protected int $width = 200;
    protected int $height = 50;
    protected string $suffix = ".png";
    protected int $dotNoiseLevel = 100;
    protected int $lineNoiseLevel = 5;
    protected int $expiration = 600;
    protected ?string $word = null;
    protected int $wordLength = 6;
    protected ?string $id = null;

    public function __construct(?string $imgDir = null, ?string $fontPath = null)
    {
        if (!extension_loaded("gd")) {
            throw new Exception("GD extension is required for CAPTCHA generation.");
        }
        if (!function_exists("imagepng") || !function_exists("imageftbbox")) {
            throw new Exception("PNG & FreeType support in GD are required.");
        }

        $this->imgDir = $imgDir ?? WRITEPATH . 'cache/';
        $this->font   = $fontPath ?? __DIR__ . '/font/mangalb.ttf';

        if (!is_dir($this->imgDir) && !mkdir($this->imgDir, 0777, true)) {
            throw new Exception("Failed to create CAPTCHA directory: {$this->imgDir}");
        }
        if (!file_exists($this->font)) {
            throw new Exception("Font file not found: {$this->font}");
        }

        helper('text');
    }

    public function setWordLength(int $length): self
    {
        $this->wordLength = max(4, $length);
        return $this;
    }

    public function setWidth(int $width): self
    {
        $this->width = max(100, $width);
        return $this;
    }

    public function setHeight(int $height): self
    {
        $this->height = max(30, $height);
        return $this;
    }

    public function setFontSize(int $size): self
    {
        $this->fontSize = $size;
        return $this;
    }

    public function getImgDir(): string
    {
        return $this->imgDir;
    }

    public function getSuffix(): string
    {
        return $this->suffix;
    }

    /**
     * Generate a CAPTCHA and return both ID and word
     */
    public function generate(): array
    {
        $this->cleanupOldCaptchas();

        $id = 'captcha_' . $this->generateId();
        $tries = 5;

        while ($tries-- && file_exists($this->imgDir . $id . $this->suffix)) {
            $id = $this->generateRandomId();
            $this->id = $id;
        }

        $this->generateImage($id, $this->word);

        return [
            'id'   => $id,
            'word' => $this->word, // diserahkan ke controller untuk disimpan
        ];
    }

    private function generateId(): string
    {
        $id = $this->generateRandomId();
        $this->id = $id;

        // Campur huruf & angka
        $word = random_string('numeric', $this->wordLength);
        $this->word = strtolower($word);

        return $id;
    }

    protected function generateRandomId(): string
    {
        return bin2hex(random_bytes(16));
    }

    protected function generateImage(string $id, string $word): void
    {
        if (empty($this->font) || !file_exists($this->font)) {
            throw new Exception('Valid font file is required for CAPTCHA');
        }

        $w     = $this->width;
        $h     = $this->height;
        $fsize = mt_rand($this->fontSize - 2, $this->fontSize + 2);

        $imgFile = $this->imgDir . $id . $this->suffix;
        $img = imagecreatetruecolor($w, $h);


        $textColor = imagecolorallocate($img, 0, 0, 0);
        $bgColor   = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, $w - 1, $h - 1, $bgColor);
        $textbox = imageftbbox($fsize, 0, $this->font, $word);
        $x       = ($w - ($textbox[2] - $textbox[0])) / 2;
        $y       = ($h - ($textbox[7] - $textbox[1])) / 2;
        $x       = (int) $x;
        $y       = (int) $y;
        imagefttext($img, $fsize, 0, $x, $y, $textColor, $this->font, $word);

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

    protected function cleanupOldCaptchas(): void
    {
        foreach (glob($this->imgDir . '*' . $this->suffix) as $fileWithExt) {
            $baseFile = substr($fileWithExt, 0, -strlen($this->suffix)); // Hapus ekstensi

            // log_message('notice', $baseFile);

            // Cek waktu kedaluwarsa berdasarkan file gambar
            if (filemtime($fileWithExt) + $this->expiration < time()) {
                // Hapus file gambar
                @unlink($fileWithExt);

                // Hapus file tanpa ekstensi jika ada
                $fileWithoutExt = $baseFile;
                if (file_exists($fileWithoutExt)) {
                    @unlink($fileWithoutExt);
                }
            }
        }
    }
}
