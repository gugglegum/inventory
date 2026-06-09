<?php
namespace backend\models;

use common\models\User;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\Model;

/**
 * Create/Edit user form
 *
 * @property-read bool $isNewRecord Признак того, что связанный пользователь еще не сохранен.
 */
class UserForm extends Model
{
    public const string SCENARIO_CREATE = 'create';

    /**
     * @var User
     */
    private ?User $_user = null;

    /**
     * Имя пользователя из формы.
     */
    public string $username = '';

    /**
     * Email пользователя из формы.
     */
    public string $email = '';

    /**
     * Новый пароль пользователя; пустая строка означает не менять пароль.
     */
    public string $password = '';

    /**
     * Статус пользователя из формы.
     */
    public string $status = '';

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            ['username', 'filter', 'filter' => 'trim'],
            ['username', 'required'],
            ['username', 'unique', 'targetClass' => '\common\models\User', 'message' => 'This username has already been taken.', 'on' => self::SCENARIO_CREATE],
            ['username', 'string', 'min' => 2, 'max' => 255],

            ['email', 'filter', 'filter' => 'trim'],
            ['email', 'required'],
            ['email', 'email'],
            ['email', 'unique', 'targetClass' => '\common\models\User', 'message' => 'This email address has already been taken.', 'on' => self::SCENARIO_CREATE],

            ['password', 'required', 'on' => self::SCENARIO_CREATE],
            ['password', 'string', 'min' => 6],

            ['status', 'safe'],
        ];
    }

    /**
     * @return User
     * @throws Exception
     */
    public function getUser(): User
    {
        if (! $this->_user instanceof User) {
            throw new Exception('User property not initialized');
        }
        return $this->_user;
    }

    /**
     * @param User $user
     */
    public function setUser(User $user): void
    {
        $this->_user = $user;
        $this->username = $this->stringify($this->_user->username);
        $this->email = $this->stringify($this->_user->email);
        $status = $this->_user->getAttribute('status');
        $this->status = $status !== null ? (string) $status : (string) User::STATUS_ACTIVE;
    }

    /**
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function save(): bool
    {
        if ($this->validate()) {
            $user = $this->getUser();
            $user->username = $this->username;
            $user->email = $this->email;
            $user->status = (int) $this->status;
            if ($this->password !== '') {
                $user->setPassword($this->password);
                $user->generateAuthKey();
            }
            return $user->save();
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function getIsNewRecord(): bool
    {
        return $this->getUser()->isNewRecord;
    }

    /**
     * Приводит значение AR-атрибута к строке формы.
     */
    private function stringify(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }
}
