<?php

declare(strict_types=1);

namespace console\controllers;

use backend\services\PhotoUploadService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Обслуживание незавершенных асинхронных загрузок фотографий.
 */
final class PhotoUploadsController extends Controller
{
    /** Выполнить подсчет без удаления файлов и строк БД. */
    public bool $dryRun = false;

    /**
     * @param string $actionID
     * @return array<array-key, string>
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        if ($actionID === 'prune') {
            $options[] = 'dryRun';
        }

        return $options;
    }

    /**
     * Удаляет явные temporary markers и связанные с ними Photo после TTL 24 часа.
     *
     * Пример cron:
     *   17 * * * * cd /path/to/stockhub/app && ./yii photo-uploads/prune
     */
    public function actionPrune(): int
    {
        $result = (new PhotoUploadService())->pruneExpired($this->dryRun);
        $prefix = $this->dryRun ? 'Будет удалено' : 'Удалено';
        $this->stdout(
            sprintf(
                "%s сессий: %d, временных файлов: %d, Photo из очереди: %d, байт: %d.\n",
                $prefix,
                $result['sessions'],
                $result['files'],
                $result['queuedPhotos'],
                $result['bytes']
            ),
            Console::FG_GREEN
        );

        return ExitCode::OK;
    }
}
