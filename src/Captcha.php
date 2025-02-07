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
    public function generate()
    {
        $hash = $this->captcha->generate();
        $word = $this->captcha->getWord();

        // Simpan teks captcha ke session untuk validasi
        session()->set('captcha_word', $word);

        // Path lengkap gambar captcha
        $imagePath = $this->captcha->getImgDir() . $hash . $this->captcha->getSuffix();

        // Pastikan file captcha ada sebelum mengirimkan output
        if (file_exists($imagePath)) {
            // Set header untuk gambar PNG
            header('Content-Type: image/png');
            readfile($imagePath);

            // remove cache file
            unlink($imagePath);
            exit;
        } else {
            return 'Captcha image not found.';
        }
    }

    /**
     * Verifikasi input user dengan CAPTCHA yang di generate sebelumnya
     *
     * @param string $string input user yang di verifikasi
     *
     * @return bool true jika input user sama dengan CAPTCHA yang di generate
     */
    public function verify(string $string):bool
    {
        $word = session()->get('captcha_word');

        // Hapus teks CAPTCHA dari session
        session()->remove('captcha_word');

        return $word == $string;
    }
}
