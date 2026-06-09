<?php
namespace frontend\models;

use common\models\User;
use yii\base\InvalidArgumentException;
use yii\base\Model;
use Yii;

/**
 * Password reset form
 */
class ResetPasswordForm extends Model
{
    /**
     * Новый пароль пользователя.
     */
    public string $password = '';

    /**
     * @var User
     */
    private User $_user;


    /**
     * Creates a form model given a token.
     *
     * @param mixed $token
     * @param array $config name-value pairs that will be used to initialize the object properties
     * @throws InvalidArgumentException if token is empty or not valid
     */
    public function __construct(mixed $token, array $config = [])
    {
        if (empty($token) || !is_string($token)) {
            throw new InvalidArgumentException('Password reset token cannot be blank.');
        }
        $user = User::findByPasswordResetToken($token);
        if (!$user) {
            throw new InvalidArgumentException('Wrong password reset token.');
        }
        $this->_user = $user;
        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            ['password', 'required'],
            ['password', 'string', 'min' => 6],
        ];
    }

    /**
     * Resets password.
     *
     * @return boolean if password was reset.
     */
    public function resetPassword(): bool
    {
        $user = $this->_user;
        $user->setPassword($this->password);
        $user->removePasswordResetToken();

        return $user->save(false);
    }
}
