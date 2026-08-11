<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\controllers\PhotoUploadController;
use backend\services\PhotoUploadService;
use common\models\Photo;
use common\models\PhotoUploadSession;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;
use yii\web\UploadedFile;

/**
 * Integration-тесты HTTP-границы временных upload-сессий.
 */
final class PhotoUploadControllerTest extends DbTestCase
{
    /**
     * Временные preview также не перенаправляют гостя на HTML-страницу входа.
     */
    public function testTemporaryPreviewRejectsGuestWithUnauthorizedResponse(): void
    {
        [$controller, $repo, $owner] = $this->prepareFixture();
        [$session, $file] = $this->uploadTemporary($repo, $owner);
        Yii::$app->user->logout(false);
        $this->setGetRequest([], '/photo-upload/view');

        $this->expectException(UnauthorizedHttpException::class);

        $controller->runAction('view', [
            'token' => (string) $session->token,
            'repoId' => (int) $repo->id,
            'context' => PhotoUploadSession::CONTEXT_ITEM_CREATE,
            'id' => (int) $file->id,
        ]);
    }

    public function testCreateReturnsOpaqueSessionAndScopedUrls(): void
    {
        [$controller, $repo] = $this->prepareFixture();
        $this->setPostRequest([
            'repoId' => (string) $repo->id,
            'context' => PhotoUploadSession::CONTEXT_ITEM_CREATE,
        ]);

        $result = $controller->actionCreate();

        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', (string) $result['token']);
        self::assertSame($result['token'], $result['session_id']);
        self::assertStringContainsString('/photo-upload/upload', (string) $result['upload_url']);
        self::assertStringContainsString('repoId=' . $repo->id, (string) $result['upload_url']);
        self::assertSame([], $result['files']);
    }

    public function testCreateRequiresContextSpecificRepoPermission(): void
    {
        [$controller, $repo] = $this->prepareFixture();
        $readonly = $this->createUser();
        $this->grantRepoAccess($repo, $readonly, RepoUser::ACCESS_READONLY);
        $this->login($readonly);
        $this->setPostRequest([
            'repoId' => (string) $repo->id,
            'context' => PhotoUploadSession::CONTEXT_ITEM_CREATE,
        ]);

        $this->expectException(ForbiddenHttpException::class);

        $controller->actionCreate();
    }

    public function testTemporaryPreviewRequiresOwnerAndUsesInternalDelivery(): void
    {
        [$controller, $repo, $owner] = $this->prepareFixture();
        [$session, $file] = $this->uploadTemporary($repo, $owner);

        $response = $controller->actionView(
            (string) $session->token,
            (int) $repo->id,
            PhotoUploadSession::CONTEXT_ITEM_CREATE,
            (int) $file->id,
        );

        self::assertSame(200, $response->statusCode);
        self::assertSame(
            '/_protected-photos/' . Photo::getFileRelativePath((int) $file->photoId),
            $response->headers->get('X-Accel-Redirect')
        );

        $otherUser = $this->createUser();
        $this->grantRepoAccess($repo, $otherUser, RepoUser::ACCESS_CREATE_ITEMS);
        $this->login($otherUser);

        $this->expectException(NotFoundHttpException::class);
        $controller->actionView(
            (string) $session->token,
            (int) $repo->id,
            PhotoUploadSession::CONTEXT_ITEM_CREATE,
            (int) $file->id,
        );
    }

    /**
     * @return array{PhotoUploadController, Repo, User}
     */
    private function prepareFixture(): array
    {
        $owner = $this->createUser(['access' => User::ACCESS_CREATE_REPO]);
        $repo = $this->createRepo($owner);
        $this->grantRepoAccess(
            $repo,
            $owner,
            RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS
        );

        $controller = new PhotoUploadController('photo-upload', Yii::$app);
        Yii::$app->controller = $controller;

        return [$controller, $repo, $owner];
    }

    /**
     * @return array{PhotoUploadSession, \common\models\PhotoUploadFile}
     */
    private function uploadTemporary(Repo $repo, User $user): array
    {
        $service = new PhotoUploadService();
        $session = $service->createSession($repo, $user, PhotoUploadSession::CONTEXT_ITEM_CREATE);
        $source = $this->createUploadedJpegFixture();
        $size = filesize($source);
        self::assertIsInt($size);

        try {
            $file = $service->storeUploadedFile(
                $session,
                new UploadedFile([
                    'name' => 'preview.jpg',
                    'tempName' => $source,
                    'type' => 'image/jpeg',
                    'size' => $size,
                    'error' => UPLOAD_ERR_OK,
                ]),
                (int) $user->id,
            );
        } finally {
            @unlink($source);
        }

        return [$session, $file];
    }
}
