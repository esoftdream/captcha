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
    protected int $wordLength = 8;
    protected ?string $id = null;

    public function __construct(string $imgDir = null, string $fontPath = null)
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

        $id = $this->generateId();
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
        $word = random_string('alnum', $this->wordLength);
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

        $w = $this->width;
        $h = $this->height;
        $fontSize = mt_rand($this->fontSize - 2, $this->fontSize + 2);

        $imgFile = $this->imgDir . $id . $this->suffix;
        $img = imagecreatetruecolor($w, $h);

        $bgColor = imagecolorallocate($img, mt_rand(230, 255), mt_rand(230, 255), mt_rand(230, 255));
        $textColor = imagecolorallocate($img, mt_rand(0, 50), mt_rand(0, 50), mt_rand(0, 50));

        imagefilledrectangle($img, 0, 0, $w, $h, $bgColor);

        $textbox = imageftbbox($fontSize, 0, $this->font, $word);
        $x = (int)(($w - ($textbox[2] - $textbox[0])) / 2);
        $y = (int)(($h - ($textbox[7] - $textbox[1])) / 2);

        imagefttext($img, $fontSize, mt_rand(-10, 10), $x, $y, $textColor, $this->font, $word);

        // Noise titik
        for ($i = 0; $i < $this->dotNoiseLevel; $i++) {
            imagefilledellipse($img, mt_rand(0, $w), mt_rand(0, $h), 2, 2, $textColor);
        }

        // Noise garis
        for ($i = 0; $i < $this->lineNoiseLevel; $i++) {
            imageline($img, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), $textColor);
        }

        imagepng($img, $imgFile);
        imagedestroy($img);
    }

    protected function cleanupOldCaptchas(): void
    {
        foreach (glob($this->imgDir . '*' . $this->suffix) as $file) {
            if (filemtime($file) + $this->expiration < time()) {
                @unlink($file);
            }
        }
    }
}
