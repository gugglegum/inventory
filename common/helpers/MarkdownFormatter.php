<?php

declare(strict_types=1);

namespace common\helpers;

use common\models\Repo;
use s9e\TextFormatter\Bundles\Fatdown;
use yii\helpers\Html;
use yii\helpers\Url;

final class MarkdownFormatter
{
    public static function format(string $markdownText, Repo $repo): string
    {
        $formattedText = Fatdown::parse($markdownText);
        $formattedText = Fatdown::render($formattedText);

        // Выделяем ссылками упоминания ID предметов вида "#1234"
        return preg_replace_callback(
            '/(?<=[\s.,;()<>{}\[\]]|^)(#(\d+))(?=[\s.,;()<>{}\[\]]|$)/',
            function(array $matches) use ($repo) {
                return '<a href="' . Html::encode(Url::to(['items/view', 'repoId' => $repo->id, 'id' => $matches[2]])) . '">' . $matches[1] . '</a>';
            },
            $formattedText);
    }
}
