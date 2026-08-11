<?php
namespace common\models;

use Yii;
use yii\base\Model;

/**
 * Login form
 */
class LoginForm extends Model
{
    /**
     * Имя пользователя из формы входа.
     */
    public string $username = '';

    /**
     * Пароль из формы входа.
     */
    public string $password = '';

    /**
     * Строковый флаг запоминания сессии из checkbox.
     */
    public string $rememberMe = '1';

    private ?User $_user = null;


    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            // username and password are both required
            [['username', 'password'], 'required'],
            // rememberMe must be a boolean value
            ['rememberMe', 'boolean'],
            // password is validated by validatePassword()
            ['password', 'validatePassword'],
        ];
    }

    /**
     * Validates the password.
     * This method serves as the inline validation for password.
     *
     * @param string $attribute the attribute currently being validated
     * @param array|null $params the additional name-value pairs given in the rule
     */
    public function validatePassword(string $attribute, /** @noinspection PhpUnusedParameterInspection */ ?array $params): void
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();
            if ($user === null || !$user->validatePassword($this->password)) {
                $this->addError($attribute, 'Incorrect username or password.');
            }
        }
    }

    /**
     * Logs in a user using the provided username and password.
     *
     * @return boolean whether the user is logged in successfully
     */
    public function login(): bool
    {
        if ($this->validate()) {
            $user = $this->getUser();
            $loginDuration = (int) (Yii::$app->params['auth']['sessionDurationSeconds'] ?? 86400 * 180);

            return $user !== null && Yii::$app->user->login(
                $user,
                $this->shouldRememberUser() ? $loginDuration : 0
            );
        } else {
            return false;
        }
    }

    /**
     * Finds user by [[username]]
     *
     * @return User|null
     */
    protected function getUser(): ?User
    {
        if ($this->_user === null && $this->username !== '') {
            $this->_user = User::findByUsername($this->username);
        }

        return $this->_user;
    }

    /**
     * Возвращает true, если пользователь выбрал долгую сессию.
     */
    private function shouldRememberUser(): bool
    {
        return filter_var($this->rememberMe, FILTER_VALIDATE_BOOLEAN);
    }
}
