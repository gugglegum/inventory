<?php

declare(strict_types=1);

namespace backend\controllers;

use common\services\SsoAccessWebhookHandler;
use common\services\SsoProfileWebhookHandler;
use common\services\SsoWebhookVerifier;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * Публичные HMAC-authenticated endpoints для событий Pyrda SSO.
 */
final class SsoWebhookController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'profile' => ['post'],
                    'access' => ['post'],
                ],
            ],
        ];
    }

    public function actionProfile(): Response
    {
        $request = (new SsoWebhookVerifier())->verify(
            Yii::$app->request,
            'profile',
            ['user.profile.updated'],
        );
        (new SsoProfileWebhookHandler())->handle($request);

        return $this->noContent();
    }

    public function actionAccess(): Response
    {
        $request = (new SsoWebhookVerifier())->verify(
            Yii::$app->request,
            'access',
            SsoAccessWebhookHandler::SUPPORTED_EVENTS,
        );
        (new SsoAccessWebhookHandler())->handle($request);

        return $this->noContent();
    }

    private function noContent(): Response
    {
        Yii::$app->response->statusCode = 204;
        Yii::$app->response->content = '';

        return Yii::$app->response;
    }
}
