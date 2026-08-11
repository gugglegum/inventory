<?php

use backend\models\PhotoEditorForm;
use yii\helpers\Html;

/**
 * Общий редактор фотографий для форм предметов и заметок.
 *
 * @var yii\web\View $this
 * @var PhotoEditorForm $photoEditorForm состояние hidden-полей родительской формы
 * @var string $createSessionUrl URL ленивого создания временной upload-сессии
 * @var int $repoId репозиторий, к которому относится upload-сессия
 * @var string $uploadContext серверный context upload-сессии
 * @var array<int, array{
 *     type: 'existing'|'temporary',
 *     id: int,
 *     thumbnailUrl: string,
 *     previewUrl: string,
 *     name?: string,
 *     deleteUrl?: string
 * }> $photoEntries фотографии в текущем порядке
 * @var string|null $editorId уникальный id корневого элемента
 * @var string|null $uploadUrl URL загрузки в уже созданную сессию
 * @var string|null $accept значение accept для file input
 * @var int|null $maxUploadBytes клиентский лимит размера одного файла, 0 — без лимита
 * @var int|null $maxConcurrentUploads число одновременно загружаемых файлов
 */

$editorId = $editorId ?? 'photo-editor-' . bin2hex(random_bytes(6));
$uploadUrl = $uploadUrl ?? '';
$accept = $accept ?? '.jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp';
$maxUploadBytes = max(0, (int) ($maxUploadBytes ?? 0));
$maxConcurrentUploads = max(1, (int) ($maxConcurrentUploads ?? 3));
$editorErrors = array_values($photoEditorForm->getFirstErrors());

$this->render('//_fancybox');
$this->registerCssFile(
    '@web/css/photo-editor.css',
    ['appendTimestamp' => true],
    'photo-editor'
);
$this->registerJsFile(
    '@web/js/photo-editor.js',
    ['appendTimestamp' => true, 'depends' => [yii\web\JqueryAsset::class]],
    'photo-editor'
);
?>

<div
    id="<?= Html::encode($editorId) ?>"
    class="photo-editor"
    data-photo-editor
    data-create-session-url="<?= Html::encode($createSessionUrl) ?>"
    data-repo-id="<?= $repoId ?>"
    data-upload-context="<?= Html::encode($uploadContext) ?>"
    data-upload-url="<?= Html::encode($uploadUrl) ?>"
    data-max-upload-bytes="<?= $maxUploadBytes ?>"
    data-max-concurrent-uploads="<?= $maxConcurrentUploads ?>"
>
    <?= Html::activeHiddenInput(
        $photoEditorForm,
        'manifest',
        ['data-photo-editor-manifest' => true]
    ) ?>
    <?= Html::activeHiddenInput(
        $photoEditorForm,
        'sessionToken',
        ['data-photo-editor-token' => true]
    ) ?>
    <?= Html::activeHiddenInput(
        $photoEditorForm,
        'revision',
        ['data-photo-editor-revision' => true]
    ) ?>

    <div
        class="photo-editor__droparea"
        data-photo-editor-droparea
        role="button"
        tabindex="0"
        aria-controls="<?= Html::encode($editorId) ?>-input"
    >
        <span class="photo-editor__droparea-title">Добавить фотографии</span>
        <span class="photo-editor__droparea-hint">
            Нажмите для выбора файлов, перетащите их сюда или вставьте изображение из буфера обмена
        </span>
        <input
            id="<?= Html::encode($editorId) ?>-input"
            class="photo-editor__file-input"
            type="file"
            accept="<?= Html::encode($accept) ?>"
            multiple
            data-photo-editor-input
            tabindex="-1"
        >
    </div>

    <div
        class="photo-editor__message alert alert-danger"
        data-photo-editor-message
        <?php if ($editorErrors === []) { ?>hidden<?php } else { ?>data-message-kind="server"<?php } ?>
    ><?= Html::encode(implode(' ', $editorErrors)) ?></div>

    <div class="photo-editor__cards" data-photo-editor-list role="list" aria-live="polite">
        <?php foreach ($photoEntries as $entry) { ?>
            <?php
            $name = $entry['name'] ?? 'Фотография';
            $isTemporary = $entry['type'] === 'temporary';
            ?>
            <article
                class="photo-editor__card photo-editor__card--ready"
                data-photo-editor-card
                data-entry-type="<?= Html::encode($entry['type']) ?>"
                data-entry-id="<?= Html::encode((string) $entry['id']) ?>"
                data-status="ready"
                <?php if ($isTemporary && isset($entry['deleteUrl'])) { ?>
                    data-delete-url="<?= Html::encode($entry['deleteUrl']) ?>"
                <?php } ?>
                role="listitem"
            >
                <a
                    class="photo-editor__preview"
                    href="<?= Html::encode($entry['previewUrl']) ?>"
                    data-photo-editor-preview
                    data-caption="<?= Html::encode($name) ?>"
                    aria-label="Просмотреть: <?= Html::encode($name) ?>"
                >
                    <?= Html::img($entry['thumbnailUrl'], [
                        'class' => 'photo-editor__thumbnail',
                        'alt' => $name,
                        'loading' => 'lazy',
                        'decoding' => 'async',
                        'draggable' => 'false',
                    ]) ?>
                </a>
                <button
                    class="photo-editor__drag-handle"
                    type="button"
                    data-photo-editor-drag-handle
                    title="Изменить порядок"
                    aria-label="Изменить порядок фотографии"
                ><i class="glyphicon glyphicon-move" aria-hidden="true"></i></button>
                <button
                    class="photo-editor__remove"
                    type="button"
                    data-photo-editor-remove
                    title="Убрать"
                    aria-label="Убрать фотографию"
                ><i class="glyphicon glyphicon-trash" aria-hidden="true"></i></button>
                <div class="photo-editor__meta">
                    <span class="photo-editor__name" title="<?= Html::encode($name) ?>"><?= Html::encode($name) ?></span>
                    <span class="photo-editor__status" data-photo-editor-status>Готово</span>
                </div>
                <div class="photo-editor__progress" data-photo-editor-progress hidden>
                    <span style="width: 100%"></span>
                </div>
            </article>
        <?php } ?>
    </div>

    <p class="photo-editor__empty" data-photo-editor-empty<?= $photoEntries !== [] ? ' hidden' : '' ?>>
        Фотографии пока не добавлены.
    </p>
</div>
