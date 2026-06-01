<?php

declare(strict_types=1);

use common\models\Item;
use common\models\Post;
use common\models\Repo;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var Post[] $posts */
/** @var Item $item */
/** @var Repo $repo */

?>
<ul class="posts">
    <?php foreach ($item->posts as $post) { ?>
        <li>
            <div class="title"><?= Html::encode(date('d.m.Y', $post->datetime)) ?> <?= Html::a($post->title, ['posts/view', 'repoId' => $repo->id, 'itemId' => $item->itemId, 'postId' => $post->id]) ?><?=
                Html::a('', Url::to(['posts/update', 'repoId' => $repo->id, 'itemId' => $item->itemId, 'postId' => $post->id]), ['class' => 'glyphicon glyphicon-edit edit-link', 'style' => 'margin-left: 5px']) ?></div>
            <div class="text"><?php
                // Выводим укороченный текст, если он слишком длинное. Заменяем в нём все избыточные белые
                // пробелы на обычные пробелы.
                $maxDescriptionLength = 250;
                $threshold = 10;
                $text = preg_replace('/\s+/u', "\x20", $post->text);
                if (mb_strlen($text) > $maxDescriptionLength + $threshold) {
                    $text = rtrim(mb_substr($text, 0, $maxDescriptionLength)) . '...';
                }
                echo \common\helpers\MarkdownFormatter::format($text, $repo);
                ?></div>

            <?php $postPhotos = $post->postPhotos; if (count($postPhotos) != 0) { ?>
                <div class="photos">
                    <?php foreach ($postPhotos as $postPhoto) { ?>
                        <?= Html::beginTag('a', ['href' => $postPhoto->photo->getUrl(), 'rel' => 'post-photos#' . $post->id, 'class' => 'fancybox']) ?>
                        <?= Html::img($postPhoto->photo->getThumbnailUrl(48, 48, true, true, 90), ['alt' => 'Photo']) ?>
                        <?= Html::endTag('a') ?>
                    <?php } ?>
                </div>
            <?php } ?>
        </li>
    <?php } ?>
</ul>
