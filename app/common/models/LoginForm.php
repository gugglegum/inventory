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
     * @var string|null
     */
    public $username;

    /**
     * @var string|null
     */
    public $password;

    /**
     * @var bool
     */
    public $rememberMe = true;

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
            if ($user === null || $this->password === null || !$user->validatePassword($this->password)) {
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

            return $user !== null && Yii::$app->user->login($user, $this->rememberMe ? 86400 * 30 : 0);
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
        if ($this->_user === null && $this->username !== null) {
            $this->_user = User::findByUsername($this->username);
        }

        return $this->_user;
    }
}
