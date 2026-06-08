<?php

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace -- Yii helper class is intentionally global.
// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses -- Yii/Psalm shims intentionally share one file.

namespace {
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
         * @var \yii\web\Application<\common\models\User>
         */
        public static $app;
    }
}

namespace yii\db {
    /**
     * Psalm-only ActiveRecord relation return type tightening.
     *
     * Yii creates concrete ActiveQuery objects for relations; the upstream
     * interface return type is wider than this application relies on.
     */
    abstract class ActiveRecord extends BaseActiveRecord
    {
        /**
         * @template TRelatedModel of ActiveRecordInterface
         * @param class-string<TRelatedModel> $class
         * @param array<string, string> $link
         * @return ActiveQuery<TRelatedModel>
         */
        public function hasOne($class, $link): ActiveQuery
        {
        }

        /**
         * @template TRelatedModel of ActiveRecordInterface
         * @param class-string<TRelatedModel> $class
         * @param array<string, string> $link
         * @return ActiveQuery<TRelatedModel>
         */
        public function hasMany($class, $link): ActiveQuery
        {
        }
    }
}
