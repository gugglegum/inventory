<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Photo;
use RuntimeException;
use Yii;
use yii\web\Response;

/**
 * Формирует защищенный ответ для отдачи фотографии внутренним location nginx.
 */
final class PhotoDeliveryService
{
    private const int CACHE_SECONDS = 1209600;

    public function original(Photo $photo): Response
    {
        return $this->deliver(
            Photo::getFileRelativePath((int) $photo->id),
            $this->xAccelPrefix('storageXAccelUrl'),
            (string) $photo->md5
        );
    }

    public function thumbnail(
        Photo $photo,
        int $width,
        int $height,
        bool $upscale,
        bool $crop,
        int $quality
    ): Response {
        $thumbnailFile = $photo->getThumbnailFile($width, $height, $upscale, $crop, $quality);
        $etag = md5_file($thumbnailFile);
        if ($etag === false) {
            throw new RuntimeException("Не удалось вычислить ETag миниатюры {$thumbnailFile}.");
        }

        return $this->deliver(
            Photo::getThumbnailFileRelativePath((int) $photo->id, $width, $height, $upscale, $crop, $quality),
            $this->xAccelPrefix('thumbnailXAccelUrl'),
            $etag
        );
    }

    private function deliver(string $relativePath, string $xAccelPrefix, string $etag): Response
    {
        $response = Yii::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->content = '';
        $response->getHeaders()
            ->set('Cache-Control', 'private, max-age=' . self::CACHE_SECONDS . ', immutable')
            ->set('Content-Type', 'image/jpeg')
            ->set('ETag', '"' . trim($etag, '"') . '"');

        if ($this->etagMatches($etag)) {
            $response->statusCode = 304;
            $response->getHeaders()->remove('X-Accel-Redirect');

            return $response;
        }

        $response->statusCode = 200;
        $response->getHeaders()->set(
            'X-Accel-Redirect',
            $xAccelPrefix . '/' . $this->encodeRelativePath($relativePath)
        );

        return $response;
    }

    private function etagMatches(string $etag): bool
    {
        $ifNoneMatch = Yii::$app->getRequest()->getHeaders()->get('If-None-Match');
        if (!is_string($ifNoneMatch) || $ifNoneMatch === '') {
            return false;
        }

        foreach (explode(',', $ifNoneMatch) as $candidate) {
            $candidate = preg_replace('/^W\//', '', trim($candidate));
            if ($candidate === '*') {
                return true;
            }
            if (hash_equals($etag, trim((string) $candidate, '"'))) {
                return true;
            }
        }

        return false;
    }

    private function xAccelPrefix(string $param): string
    {
        $prefix = rtrim((string) Yii::$app->params['photos'][$param], '/');
        if ($prefix === '' || !str_starts_with($prefix, '/')) {
            throw new RuntimeException("Некорректный внутренний URI фотографий: {$prefix}.");
        }

        return $prefix;
    }

    private function encodeRelativePath(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0")) {
            throw new RuntimeException("Некорректный относительный путь фотографии: {$path}.");
        }

        $segments = explode('/', $path);
        if (in_array('..', $segments, true)) {
            throw new RuntimeException("Некорректный относительный путь фотографии: {$path}.");
        }

        return implode('/', array_map('rawurlencode', $segments));
    }
}
