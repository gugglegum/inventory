<?php

declare(strict_types=1);

use common\models\Item;
use common\models\Post;
use common\models\Repo;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var \yii\web\View $this */
/** @var Post[] $posts */
/** @var Item $item */
/** @var Repo $repo */

$this->registerCssFile('@web/css/post-list.css', ['appendTimestamp' => true], 'post-list');
$this->registerJsFile('@web/js/post-list.js', ['appendTimestamp' => true], 'post-list');
?>
<div class="post-list">
    <?php foreach ($posts as $post) {
        $text = trim((string) $post->text);
        $postPhotos = $post->postPhotos;
        $hasDetails = $text !== '' || $postPhotos !== [];
        $preview = preg_replace('/\s+/u', ' ', $text) ?? '';
        if (mb_strlen($preview) > 260) {
            $preview = rtrim(mb_substr($preview, 0, 250)) . '…';
        }
        $viewUrl = Url::to([
            'posts/view',
            'repoId' => $repo->id,
            'itemId' => $item->itemId,
            'postId' => $post->id,
        ]);
        $modalUrl = Url::to([
            'posts/view',
            'repoId' => $repo->id,
            'itemId' => $item->itemId,
            'postId' => $post->id,
            'modal' => '1',
        ]);
        ?>
        <article id="post-<?= $post->id ?>" class="post-card post-card--clickable">
            <header class="post-card__header">
                <time datetime="<?= Html::encode(date(DATE_ATOM, $post->datetime)) ?>" class="post-card__date">
                    <?= Html::encode(date('d.m.Y H:i', $post->datetime)) ?>
                </time>
                <h4 class="post-card__title" title="<?= Html::encode($post->title) ?>"><?= Html::encode($post->title) ?></h4>
                <?= Html::a('', ['posts/update', 'repoId' => $repo->id, 'itemId' => $item->itemId, 'postId' => $post->id], [
                    'class' => 'bi bi-pencil post-card__action',
                    'title' => 'Изменить заметку',
                    'aria-label' => 'Изменить заметку',
                ]) ?>
            </header>

            <?php if ($hasDetails) { ?>
                <div class="post-card__details">
                    <?php if ($preview !== '') { ?>
                        <div class="post-card__text" title="<?= Html::encode($preview) ?>"><?= Html::encode($preview) ?></div>
                    <?php } ?>

                    <?php if ($postPhotos !== []) { ?>
                        <div class="post-card__photos" aria-label="Фотографии заметки">
                            <?php foreach (array_slice($postPhotos, 0, 4) as $postPhoto) { ?>
                                <?= Html::a(
                                    Html::img($postPhoto->photo->getThumbnailUrl(32, 32, true, true, 90), ['alt' => 'Фотография заметки']),
                                    $postPhoto->photo->getUrl(),
                                    ['data-fancybox' => 'post-photos-' . $post->id]
                                ) ?>
                            <?php } ?>
                            <?php if (count($postPhotos) > 4) { ?>
                                <span class="post-card__photo-count">+<?= count($postPhotos) - 4 ?></span>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>

            <a
                href="<?= Html::encode($viewUrl) ?>"
                class="post-card__open"
                data-post-modal-url="<?= Html::encode($modalUrl) ?>"
                aria-label="Открыть заметку «<?= Html::encode($post->title) ?>»"
            ><span class="visually-hidden">Открыть заметку</span></a>
        </article>
    <?php } ?>
</div>

<div class="modal fade post-view-modal" id="postViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" data-post-modal-content>
            <div class="modal-body text-center text-muted">Загрузка…</div>
        </div>
    </div>
</div>
