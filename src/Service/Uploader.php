<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class Uploader
{

    public function __construct(private $profileFolder, private $profileFolderPath, private FileSystem $fs)
    {
    }
    public function uploadProfileImage(UploadedFile $picture, string $oldPicturePath = null): string
    {
        $extension = $picture->guessExtension() ?? 'bin';
        $filename = bin2hex(random_bytes(10)) . '.' . $extension;
        $folder = $this->profileFolder;
        $picture->move($folder, $filename);

        if ($oldPicturePath) {
            $this->fs->remove($folder . '/' . pathinfo($oldPicturePath, PATHINFO_BASENAME));
        }
        return $this->profileFolderPath . '/' . $filename;
    }
}
