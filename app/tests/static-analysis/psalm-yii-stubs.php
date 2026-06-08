<?php

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace -- Yii helper class is intentionally global.

/**
 * Psalm-only Yii helper type binding.
 *
 * Runtime still loads `vendor/yiisoft/yii2/Yii.php`; this stub only tells Psalm
 * that the application user identity in this project is `common\models\User`.
 *
 * @extends \yii\BaseYii<\common\models\User>
 */
class Yii extends \yii\BaseYii
{
    /**
     * @var \yii\web\Application<\common\models\User>|null
     */
    public static $app;
}
