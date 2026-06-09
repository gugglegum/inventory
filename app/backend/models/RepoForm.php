<?php

declare(strict_types=1);

namespace backend\models;

use common\models\Repo;
use common\models\RepoUser;
use yii\base\Model;

/**
 * Форма создания и редактирования репозитория.
 *
 * Хранит пользовательский ввод как строки и переносит его в Repo/RepoUser только после
 * валидации, чтобы AR-модели не загружали сырые POST-данные.
 */
class RepoForm extends Model
{
    public const string SCENARIO_CREATE = 'create';
    public const string SCENARIO_UPDATE = 'update';

    /**
     * Название репозитория.
     */
    public string $name = '';

    /**
     * Описание репозитория.
     */
    public string $description = '';

    /**
     * Последний занятый itemId внутри репозитория.
     */
    public string $lastItemId = '0';

    /**
     * Пользовательский приоритет репозитория в списке.
     */
    public string $priority = '0';

    /**
     * Связанная AR-модель репозитория.
     */
    private Repo $repo;

    /**
     * Связанная AR-модель персональных настроек доступа.
     */
    private RepoUser $repoUser;

    public function __construct(array $config = [])
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
     * Правила валидации строковых значений формы.
     */
    public function rules(): array
    {
        return [
            [['name', 'description', 'lastItemId', 'priority'], 'filter', 'filter' => 'trim'],
            [['name', 'priority'], 'required'],
            [['lastItemId', 'priority'], 'integer'],
            [['name'], 'string', 'max' => 64],
            [['description'], 'string'],
        ];
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

    /**
     * Заполняет форму текущими значениями Repo и RepoUser без Model::load().
     */
    public function fillFromModels(): void
    {
        $this->name = $this->stringify($this->repo->getAttribute('name'));
        $this->description = $this->stringify($this->repo->getAttribute('description'));
        $this->lastItemId = $this->stringify($this->repo->getAttribute('lastItemId'));
        $this->priority = $this->stringify($this->repoUser->getAttribute('priority'));
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
        if (!$this->validate()) {
            return false;
        }

        $this->repo->name = $this->name;
        $this->repo->description = $this->description;
        $this->repo->lastItemId = $this->lastItemId !== '' ? (int) $this->lastItemId : 0;

        $userId = (int) \Yii::$app->getUser()->getId();
        if ($this->scenario === self::SCENARIO_CREATE) {
            $this->repo->createdBy = $userId;
        } elseif ($this->scenario === self::SCENARIO_UPDATE) {
            $this->repo->updatedBy = $userId;
        }

        $this->repoUser->priority = $this->priority !== '' ? (int) $this->priority : 0;
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

    /**
     * Приводит значение AR-атрибута к строке формы.
     */
    private function stringify(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }
}
