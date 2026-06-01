<?php

use common\models\Repo;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var \yii\web\View $this */
/** @var Repo $repo */
/** @var \common\models\User[] $affectedUsers */

$this->title = 'Удаление репозитория';
$this->render('/_breadcrumbs', ['item' => null, 'repo' => $repo, 'suffix' => [$this->title]]);

?>
<div class="item-delete">

    <h1><?= Html::encode($this->title) ?></h1>

<?php if (($itemsCount = $repo->getItems()->count()) > 0) { ?>
    <div class="alert alert-danger" role="alert" style="margin: 2em 0; max-width: 50em; font-size: 130%"><strong>ВНИМАНИЕ!</strong> Вы собираетесь полностью удалить репозиторий
        &laquo;<strong><?= Html::encode($repo->name) ?></strong>&raquo;, содержащий предметы&nbsp;(<strong><?= Html::encode($itemsCount) ?></strong>).
        Подумайте как следует перед тем как продолжить. Это действие нельзя будет отменить.
    </div>
<?php } else { ?>
    <div class="alert alert-success" role="alert" style="margin: 2em 0; max-width: 50em; font-size: 130%"><strong>ВНИМАНИЕ!</strong>
        Вы собираетесь удалить репозиторий &laquo;<strong><?= Html::encode($repo->name) ?></strong>&raquo;. Хотя
        репозиторий не содержит предметов, при его удалении ID репозитория будет утрачен навсегда. Если вы планируете создать
        новый репозиторий, возможно имеет смысл просто <a href="<?= Html::encode(Url::to(['repo/update', 'repoId' => $repo->id])) ?>">переименовать</a> этот?
    </div>
<?php } ?>
<?php if (count($affectedUsers) > 0) { ?>
    <div class="alert alert-danger" role="alert" style="margin: 2em 0; max-width: 50em; font-size: 130%"><strong>ВНИМАНИЕ!</strong> К этому репозиторию имеют
        доступ другие пользователи (<?= count($affectedUsers) ?>):
        <ul>
        <?php foreach ($affectedUsers as $affectedUser) {
            echo '<li>' . Html::encode($affectedUser->username) . "</li>\n";
         } ?>
        </ul>
    </div>
<?php } else { ?>
    <div class="alert alert-success" role="alert" style="margin: 2em 0; max-width: 50em; font-size: 130%">К этому репозиторию
        имеете доступ только Вы. Так что по крайней мере больше никто не пострадает.
    </div>

    <?php } ?>

    <?php $form = ActiveForm::begin([
        'action' => Url::to(['delete', 'repoId' => $repo->id]),
        'method' => 'post',
        'options' => [
            'style' => 'margin-top: 1em',
        ],
    ]); ?>

    <?= $form->errorSummary($repo) ?>

    <?= Html::submitButton('<i class="glyphicon glyphicon-trash" style="margin-right: 5px;"></i> Удалить', [
        'class' => 'btn btn-danger',
        'data' => [
            'confirm' => 'Вы действительно хотите удалить этот репозиторий?',
            'method' => 'post',
        ],
    ]) ?>
    <?= Html::a('<i class="glyphicon glyphicon-remove"></i> Отмена', Url::to(['repo/index']), ['style' => 'margin-left: 1em']) ?>
    <?php ActiveForm::end(); ?>

</div>
