<?php

declare(strict_types=1);

namespace backend\models;

use common\models\Repo;
use common\models\RepoUser;
use yii\base\Model;

class RepoForm extends Model
{
    public const string SCENARIO_CREATE = 'create';
    public const string SCENARIO_UPDATE = 'update';

    public $name;
    public $description;
    public $lastItemId;

    public $priority = '0';

    /**
     * @var Repo
     */
    private Repo $repo;

    /**
     * @var RepoUser
     */
    private RepoUser $repoUser;

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->repo = new Repo();
    }

    public function scenarios(): array
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_CREATE] = ['name', 'description', 'priority'];
        $scenarios[self::SCENARIO_UPDATE] = ['name', 'description', 'lastItemId', 'priority'];
        return $scenarios;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return array_merge($this->repo->attributeLabels(), [
            'priority' => 'Приоритет сортировки',
        ]);
    }

    public function getRepo(): Repo
    {
        return $this->repo;
    }

    public function setRepo(Repo $repo): void
    {
        $this->repo = $repo;
    }

    public function setRepoUser(RepoUser $repoUser): void
    {
        $this->repoUser = $repoUser;
    }

    public function load($data, $formName = null): bool
    {
        if ($this->scenario === self::SCENARIO_CREATE) {
            $this->lastItemId = '0';
        }
        return parent::load($data, $formName);
    }

    /**
     * @return bool
     * @throws \yii\db\Exception
     */
    public function save(): bool
    {
        $this->repo->load($this->attributes, '');
        if ($this->scenario === self::SCENARIO_CREATE) {
            $this->repo->createdBy = \Yii::$app->getUser()->getId();
        } elseif ($this->scenario === self::SCENARIO_UPDATE) {
            $this->repo->updatedBy = \Yii::$app->getUser()->getId();
        }

        $this->repoUser->load($this->attributes, '');
        $this->repoUser->userId = $this->repo->createdBy;
        $this->repoUser->access = RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS | RepoUser::ACCESS_DELETE_ITEMS | RepoUser::ACCESS_EDIT_REPO | RepoUser::ACCESS_DELETE_REPO;

        $transaction = \Yii::$app->db->beginTransaction();

        try {
            if (!$this->repo->save()) {
                $this->addErrors($this->repo->errors);
                $transaction->rollBack();
                return false;
            }

            $this->repoUser->repoId = $this->repo->id;

            if (!$this->repoUser->save()) {
                $this->addErrors($this->repoUser->errors);
                $transaction->rollBack();
                return false;
            }
            $transaction->commit();
            return true;
        } catch (\yii\db\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }
}
