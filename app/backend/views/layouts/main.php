<?php

/** @var \yii\web\View $this */
/** @var string $content */

use backend\assets\AppAsset;
use common\models\User;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use yii\bootstrap5\Breadcrumbs;
use common\widgets\Alert;

AppAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <link rel="icon" type="image/vnd.microsoft.icon" href="<?= Url::to('@web/images/favicon.svg') ?>" />
    <?php $this->head() ?>
</head>
<body>
<?php $this->beginBody() ?>

<div class="wrap">
    <?php
    NavBar::begin([
        'brandLabel' => 'StockHub',
        'brandImage' => Url::to('@web/images/logo.svg'),
        'brandUrl' => Yii::$app->homeUrl,
        'options' => [
            'class' => 'navbar-expand-md navbar-dark bg-dark',
        ],
    ]);
    $menuItems = [];
    if (!Yii::$app->user->isGuest) {
        $menuItems[] = ['label' => 'Репозитории', 'url' => ['repo/index']];
        if (\common\components\UserAccess::canManageUsers()) {
            $menuItems[] = [
                'label' => 'Admin', 'items' => [
                    ['label' => 'Users', 'url' => ['users/index']],
                ],
            ];
        }

        /** @var User $identity */
        $identity = Yii::$app->user->identity;
        $menuItems[] = [
            'label' => 'Logout (' . $identity->username . ')',
            'url' => ['/site/logout'],
            'linkOptions' => ['data-method' => 'post']
        ];
    }
    echo Nav::widget([
        'options' => ['class' => 'navbar-nav ms-auto'],
        'items' => $menuItems,
    ]);
    NavBar::end();
    ?>

    <div class="container">
        <?= Breadcrumbs::widget([
            'homeLink' => false,
            'links' => $this->params['breadcrumbs'] ?? [],
        ]) ?>
        <?= Alert::widget() ?>
        <?= $content ?>
    </div>
</div>

<footer class="footer">
    <div class="container footer-content">
        <p>&copy; Павел Мелехов 2015&mdash;<?= date('Y') ?></p>

        <p><?= Yii::powered() ?></p>
    </div>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
