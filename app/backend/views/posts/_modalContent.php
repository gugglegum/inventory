<?php

declare(strict_types=1);

use common\models\Item;
use common\models\Post;
use common\models\Repo;
use yii\helpers\Html;

/** @var Post $post */
/** @var Item $item */
/** @var Repo $repo */

$text = trim((string) $post->text);
?>
<div class="modal-header">
    <div>
        <h2 class="modal-title fs-5"><?= Html::encode($post->title) ?></h2>
        <div class="post-modal__date"><?= Html::encode(date('d.m.Y H:i T', $post->datetime)) ?></div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
</div>
<div class="modal-body post-modal">
    <?php if ($text !== '') { ?>
        <div class="post-modal__text"><?= \common\helpers\MarkdownFormatter::format($text, $repo) ?></div>
    <?php } ?>

    <?php if ($post->postPhotos !== []) { ?>
        <div class="post-modal__photos">
            <?php foreach ($post->postPhotos as $postPhoto) { ?>
                <?= Html::a(
                    Html::img($postPhoto->photo->getThumbnailUrl(240, 240, false, false, 90), ['alt' => 'Фотография заметки']),
                    $postPhoto->photo->getUrl(),
                    ['data-fancybox' => 'post-modal-photos-' . $post->id]
                ) ?>
            <?php } ?>
        </div>
    <?php } ?>

    <dl class="post-modal__meta">
        <dt>Создатель:</dt>
        <dd><?= $post->createdByUser ? Html::encode($post->createdByUser->username) : '<em>Неизвестно</em>' ?></dd>
        <dt>Создано:</dt>
        <dd><?= Html::encode(date('d.m.Y H:i T', $post->created)) ?></dd>
        <?php if ($post->updated !== null) { ?>
            <dt>Изменено:</dt>
            <dd><?= Html::encode(date('d.m.Y H:i T', $post->updated)) ?></dd>
        <?php } ?>
    </dl>
</div>
<div class="modal-footer">
    <?= Html::a('<i class="bi bi-pencil"></i> Изменить', ['posts/update', 'repoId' => $repo->id, 'itemId' => $item->itemId, 'postId' => $post->id], ['class' => 'btn btn-primary']) ?>
    <?= Html::a('<i class="bi bi-trash"></i> Удалить', ['posts/delete', 'repoId' => $repo->id, 'itemId' => $item->itemId, 'postId' => $post->id], ['class' => 'btn btn-outline-danger']) ?>
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
</div>
