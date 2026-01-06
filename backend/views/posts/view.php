<?php

use common\models\Item;
use common\models\Post;
use common\models\Repo;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var \yii\web\View $this */
/** @var Post $post */
/** @var Item $item */
/** @var Repo $repo */

$this->title = $post->title;

$this->render('/_breadcrumbs', ['item' => $item, 'repo' => $repo, 'suffix' => [$this->title]]);

$this->registerCssFile('@web/css/upload_photo.css', ['appendTimestamp' => true], 'upload_photo');
$this->registerCssFile('@web/css/post-view.css', ['appendTimestamp' => true], 'post-view');
//$this->registerJsFile('@web/js/item-view.js', ['appendTimestamp' => true, 'depends' => [\yii\web\JqueryAsset::class]], 'item-view');

$this->render('//_fancybox'); // Подключение jQuery-плагина Fancybox (*.js + *.css)

$text = trim((string) $post->text);
if ($text !== '') {
    $text = \common\helpers\MarkdownFormatter::format($text, $repo);
}

?>
<div id="post-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <dl id="post-text">
        <div id="lnkEdit">
            <?= Html::a('<i class="glyphicon glyphicon-edit" style="margin-right: 5px;"></i> Изменить', ['update', 'repoId' => $repo->id, 'itemId' => $item->itemId, 'postId' => $post->id]) ?>
        </div>
        <div><?= $text ?></div>
    </dl>

    <div id="post-photos">
        <h3>Фотографии</h3>
        <?php
        $photos = $post->postPhotos;
        if (count($photos) !== 0) {
            echo Html::beginTag('div', ['class' => 'uploaded-photos']);
            foreach ($photos as $postPhoto) {
                echo Html::beginTag('div', ['class' => 'photo-wrapper']);
                echo Html::beginTag('div', ['class' => 'photo-frame']);
                echo Html::beginTag('a', ['href' => $postPhoto->photo->getUrl(), 'rel' => 'post-photos', 'class' => 'fancybox']);
                echo Html::img($postPhoto->photo->getThumbnailUrl(240, 240, false, false, 90), ['alt' => 'Photo']);
                echo Html::endTag('a');
                echo '<div class="upload-date">' . Html::encode(date('d.m.Y H:i T', $postPhoto->photo->created)) . '</div>';
                echo Html::endTag('div');
                echo Html::endTag('div');
            }
            echo '<div class="clearfix"></div>';
            echo Html::endTag('div');
        } else {
            echo "<p class='hint-block'><em>Нет фотографий</em></p>\n";
        }
        ?>
    </div>
    <div id="post-properties">
        <dl>
            <dt>Создатель:</dt>
            <dd><?= $post->createdByUser ? Html::encode($post->createdByUser->username) : '<em>Неизвестно</em>' ?></dd>
        </dl>
        <dl>
            <dt>Дата создания:</dt>
            <dd><?= Html::encode(date('d.m.Y H:i T', $post->created)) ?></dd>
        </dl>
        <dl>
            <dt>Последним изменил(а):</dt>
            <dd><?= $post->updatedByUser ? Html::encode($post->updatedByUser->username) : ($post->updated !== null ? '<em>Неизвестно</em>' : '<em>Никто</em>') ?></dd>
        </dl>
        <dl>
            <dt>Дата изменения:</dt>
            <dd><?= $post->updated !== null ? Html::encode(date('d.m.Y H:i T', $post->updated)) : '<em>Не было изменений</em>' ?></dd>
        </dl>
    </div>

    <p style="margin-top: 3em">
        <?= Html::a('<i class="glyphicon glyphicon-trash" style="margin-right: 5px;"></i> Удалить', ['delete', 'repoId' => $repo->id, 'itemId' => $item->itemId, 'postId' => $post->id], []) ?>
    </p>
</div>
