<?php
/**
 * Yii bootstrap file.
 *
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

require(__DIR__ . '/../vendor/yiisoft/yii2/BaseYii.php');

/**
 * Этот класс нужен исключительно для корректной работы валидатора кода в IDE PhpStorm, чтобы
 * он видел кастомные компоненты, которые адресуются через \Yii::$app->myComponent. Этот класс
 * не используется на самом деле. Для корректной работы, чтобы избежать проблемы "Other
 * declaration of class Yii exists", нужно нажать правой кнопкой на файле
 * vendor/yiisoft/yii2/Yii.php и выбрать пункт "Mark as Plain Text". Тогда выполняться будет
 * оригинальный файл, а IDE будет парсить этот фэйковый.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Yii extends \yii\BaseYii
{
	/**
	 * @var BaseApplication|WebApplication|ConsoleApplication the application instance
	 */
	public static $app;
}

spl_autoload_register(['Yii', 'autoload'], true, true);
Yii::$classMap = include(__DIR__ . '/../vendor/yiisoft/yii2/classes.php');
Yii::$container = new yii\di\Container;

/**
 * Class BaseApplication
 * Used for properties that are identical for both WebApplication and ConsoleApplication
 *
 * @property gugglegum\Yii2\Extension\CookieLanguageSelector\Component $cookieLanguageSelector
 */
abstract class BaseApplication extends yii\base\Application
{
}

/**
 * Class WebApplication
 * Include only Web application related components here
 *
 */
class WebApplication extends yii\web\Application
{
}

/**
 * Class ConsoleApplication
 * Include only Console application related components here
 *
 */
class ConsoleApplication extends yii\console\Application
{
}
