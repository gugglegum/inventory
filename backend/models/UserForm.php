<?php
namespace backend\models;

use common\models\User;
use yii\base\Exception;
use yii\base\Model;
use Yii;

/**
 * Create/Edit user form
 */
class UserForm extends Model
{
    /**
     * @var User
     */
    private $_user;

    public $username;
    public $email;
    public $password;
    public $status;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['username', 'filter', 'filter' => 'trim'],
            ['username', 'required'],
            ['username', 'unique', 'targetClass' => '\common\models\User', 'message' => 'This username has already been taken.', 'on' => 'create'],
            ['username', 'string', 'min' => 2, 'max' => 255],

            ['email', 'filter', 'filter' => 'trim'],
            ['email', 'required'],
            ['email', 'email'],
            ['email', 'unique', 'targetClass' => '\common\models\User', 'message' => 'This email address has already been taken.', 'on' => 'create'],

            ['password', 'required', 'on' => 'create'],
            ['password', 'string', 'min' => 6],

            ['status', 'safe'],
        ];
    }

    /**
     * @return User
     * @throws Exception
     */
    public function getUser()
    {
        if (! $this->_user instanceof User) {
            throw new Exception('User property not initialized');
        }
        return $this->_user;
    }

    /**
     * @param User $user
     */
    public function setUser(User $user)
    {
        $this->_user = $user;
        $this->username = $this->_user->username;
        $this->email = $this->_user->email;
        $this->status = $this->_user->status;
    }

    public function save()
    {
        if ($this->validate()) {
            $user = $this->getUser();
            $user->username = $this->username;
            $user->email = $this->email;
            $user->status = $this->status;
            if ($this->password != '') {
                $user->setPassword($this->password);
                $user->generateAuthKey();
            }
            return $user->save();
        }

        return false;
    }

    public function getIsNewRecord()
    {
        return $this->getUser()->isNewRecord;
    }

}
