<?php

namespace Esoftdream;

class Captcha
{
    private Image $captcha;

    public function __construct(int $width = 300, int $height = 50)
    {
        // Inisialisasi konfigurasi CAPTCHA
        $this->captcha = new Image();
        $this->captcha->setWidth($width);
        $this->captcha->setHeight($height);
        $this->captcha->setWordLength(6);
    }

    /**
     * Membuat gambar CAPTCHA
     *
     * @return void|false
     */
    public function generate(string $baseUrl  = 'captcha/image/')
    {
        $captcha = new \Esoftdream\Image();
        $result = $captcha->generate();

        // Simpan ke cache selama 10 menit
        cache()->save($result['id'], $result['word'], 600);

        return [
            'captcha_id' => $result['id'],
            'image_url'  => base_url($baseUrl . $result['id'])
        ];
    }

    /**
     * Verifikasi input user dengan CAPTCHA yang di generate sebelumnya
     *
     * @param string $string input user yang di verifikasi
     *
     * @return bool true jika input user sama dengan CAPTCHA yang di generate
     */
    public function verify(string $id, string $input): bool
    {
        $word = cache('captcha_' . $id);

        if ($word && $word === $input) {
            cache()->delete($id);
            return true;
        }
        return false;
    }
}
