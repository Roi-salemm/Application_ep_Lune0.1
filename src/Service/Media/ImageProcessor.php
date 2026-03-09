<?php

namespace App\Service\Media;

use App\Entity\AppMedia;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Service d image: genere trois variantes (mini/tel/tab) sans conserver l original.
 * Pourquoi: servir la bonne taille selon l appareil et reduire le stockage disque.
 * Info: accepte PNG/JPG/WebP, PNG conserve son format sinon sortie WebP.
 */
final class ImageProcessor
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const VARIANTS = [
        'mini' => 480,
        'tel' => 1080,
        'tab' => 1600,
    ];

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function processUpload(UploadedFile $file, int $quality = 82): AppMedia
    {
        // Commentaire: verification du fichier temporaire pour eviter les erreurs "file does not exist".
        $tmpPath = (string) $file->getPathname();
        if ($tmpPath === '' || !is_readable($tmpPath)) {
            throw new \RuntimeException('Fichier temporaire introuvable ou non lisible.');
        }

        $imageInfo = @getimagesize($tmpPath);
        if (!is_array($imageInfo) || !isset($imageInfo['mime'])) {
            throw new \RuntimeException('Image invalide.');
        }

        $sourceMime = (string) $imageInfo['mime'];
        if ($sourceMime === '' || !in_array($sourceMime, self::ALLOWED_MIME, true)) {
            throw new \RuntimeException('Format image non supporte (PNG/JPG/WebP).');
        }

        $outputFormat = $sourceMime === 'image/png' ? 'png' : 'webp';
        $outputMime = $outputFormat === 'png' ? 'image/png' : 'image/webp';
        if ($outputFormat === 'webp' && !function_exists('imagewebp')) {
            throw new \RuntimeException('GD imagewebp indisponible.');
        }

        $source = $this->createImageResource($tmpPath, $sourceMime);
        if (!$source) {
            throw new \RuntimeException('Format image non supporte.');
        }

        $sourceWidth = (int) $imageInfo[0];
        $sourceHeight = (int) $imageInfo[1];
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($source);
            throw new \RuntimeException('Dimensions image invalides.');
        }

        $projectDir = $this->kernel->getProjectDir();
        $publicDir = $projectDir . '/public';
        $baseDir = $publicDir . '/uploads/app_media';
        $variantDirs = [
            'mini' => $baseDir . '/mini',
            'tel' => $baseDir . '/tel',
            'tab' => $baseDir . '/tab',
        ];
        $this->filesystem->mkdir(array_values($variantDirs), 0775);

        $token = bin2hex(random_bytes(8));
        $createdFiles = [];
        $variants = [];

        try {
            foreach (self::VARIANTS as $key => $width) {
                $filename = sprintf('%s_%s.%s', $token, $key, $outputFormat);
                $fullPath = $variantDirs[$key] . '/' . $filename;
                $variant = $this->resizeAndSave(
                    $source,
                    $sourceWidth,
                    $sourceHeight,
                    $sourceMime,
                    $width,
                    $fullPath,
                    $outputFormat,
                    $quality
                );
                $variants[$key] = [
                    'path' => 'uploads/app_media/' . $key . '/' . $filename,
                    'mime' => $outputMime,
                    'size' => $variant['size'],
                    'width' => $variant['width'],
                    'height' => $variant['height'],
                ];
                $createdFiles[] = $fullPath;
            }
        } catch (\Throwable $exception) {
            foreach ($createdFiles as $path) {
                @unlink($path);
            }
            imagedestroy($source);
            throw $exception;
        }
        imagedestroy($source);

        $media = new AppMedia();
        $media->setType('image');
        $media->setOriginalPath(null);
        $media->setOriginalMime($sourceMime);
        $media->setOriginalSize((int) ($file->getSize() ?: filesize($tmpPath)));

        $mini = $variants['mini'];
        $tel = $variants['tel'];
        $tab = $variants['tab'];

        $media->setImgMiniPath($mini['path']);
        $media->setImgMiniMime($mini['mime']);
        $media->setImgMiniSize($mini['size']);
        $media->setImgMiniWidth($mini['width']);
        $media->setImgMiniHeight($mini['height']);

        $media->setImgTelPath($tel['path']);
        $media->setImgTelMime($tel['mime']);
        $media->setImgTelSize($tel['size']);
        $media->setImgTelWidth($tel['width']);
        $media->setImgTelHeight($tel['height']);

        $media->setImgTabPath($tab['path']);
        $media->setImgTabMime($tab['mime']);
        $media->setImgTabSize($tab['size']);
        $media->setImgTabWidth($tab['width']);
        $media->setImgTabHeight($tab['height']);

        $media->setAppPath($tel['path']);
        $media->setAppMime($tel['mime']);
        $media->setAppSize($tel['size']);
        $media->setAppWidth($tel['width']);
        $media->setAppHeight($tel['height']);
        $media->setIsPublic(true);

        return $media;
    }

    /**
     * @return array{width:int, height:int, size:int}
     */
    private function resizeAndSave(
        \GdImage $source,
        int $sourceWidth,
        int $sourceHeight,
        string $sourceMime,
        int $targetWidth,
        string $fullPath,
        string $format,
        int $quality
    ): array {
        $targetWidth = min($targetWidth, $sourceWidth);
        $targetHeight = (int) round($sourceHeight * ($targetWidth / $sourceWidth));
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$resized) {
            throw new \RuntimeException('Erreur creation image.');
        }

        if (in_array($sourceMime, ['image/png', 'image/webp'], true)) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        $copied = imagecopyresampled(
            $resized,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );
        if (!$copied) {
            throw new \RuntimeException('Erreur redimensionnement image.');
        }

        $saved = $format === 'png'
            ? imagepng($resized, $fullPath, 6)
            : imagewebp($resized, $fullPath, $quality);
        imagedestroy($resized);

        if (!$saved) {
            throw new \RuntimeException('Erreur conversion image.');
        }

        return [
            'width' => $targetWidth,
            'height' => $targetHeight,
            'size' => (int) filesize($fullPath),
        ];
    }

    private function createImageResource(string $path, string $mime): \GdImage|false
    {
        return match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? imagecreatefromjpeg($path) : false,
            'image/png' => function_exists('imagecreatefrompng') ? imagecreatefrompng($path) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false,
            default => false,
        };
    }
}
