<?php

namespace console\controllers;

use common\helpers\ValidateErrorsFormatter;
use common\models\User;
use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\console\Controller;

class UserController extends Controller
{
    const EXIT_CODE_USER_EXISTS = 2;
    const EXIT_CODE_EMAIL_EXISTS = 3;
    const EXIT_CODE_PASSWORD_TOO_SHORT = 4;
    const EXIT_CODE_DB_ERROR = 5;
    const EXIT_CODE_USER_NOT_FOUND = 6;

    /**
     * Create new user
     *
     * @param string $username
     * @param string $email
     * @return int
     * @throws Exception on failure
     * @throws InvalidConfigException
     */
    public function actionCreate($username, $email)
    {
        if (User::find()->where(['username' => $username])->exists()) {
            echo "User with name '{$username}' already exists\n";
            return self::EXIT_CODE_ERROR;
        }
        if (User::find()->where(['email' => $email])->exists()) {
            echo "User with e-mail '{$email}' already exists\n";
            return self::EXIT_CODE_ERROR;
        }

        $user = new User();

        $user->username = $username;
        $user->email = $email;
        $user->status = User::STATUS_ACTIVE;
        $password = $this->_promptPassword();

        if (strlen($password) < 3) {
            echo "Entered password is too short (min 3 chars)\n";
            return self::EXIT_CODE_PASSWORD_TOO_SHORT;
        }

        $user->setPassword($password);
        $user->generateAuthKey();

        try {
            if ($user->save()) {
                echo "New user '{$username}' successfully created\n";
            } else {
                echo 'Validation error: ' . ValidateErrorsFormatter::firstError($user, '%ERROR%') . "\n";
                return self::EXIT_CODE_ERROR;
            }
        } catch (\yii\db\Exception $e) {
            echo "DB error occurred while creating new user '{$username}':\n" . $e->getMessage() . "\n";
            return self::EXIT_CODE_DB_ERROR;
        }

        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Change password for specified user
     *
     * @param string $username
     * @return int
     * @throws Exception on failure
     * @throws InvalidConfigException
     */
    public function actionChangePassword($username)
    {
        $user = User::find()->where(['username' => $username])->one();

        if (! $user) {
            echo "User '{$username}' not found\n";
            return self::EXIT_CODE_USER_NOT_FOUND;
        }

        $password = $this->_promptPassword();

        if (strlen($password) < 3) {
            echo "Entered password is too short (min 3 chars)\n";
            return self::EXIT_CODE_PASSWORD_TOO_SHORT;
        }

        $user->setPassword($password);
        $user->generateAuthKey();

        try {
            if ($user->save()) {
                echo "Password for user '{$username}' successfully changed\n";
            } else {
                echo 'Validation error: ' . ValidateErrorsFormatter::firstError($user, '%ERROR%') . "\n";
                return self::EXIT_CODE_ERROR;
            }
        } catch (\yii\db\Exception $e) {
            echo "DB error occurred while updating user '{$username}'':\n" . $e->getMessage() . "\n";
            return self::EXIT_CODE_DB_ERROR;
        }

        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Delete user
     *
     * @param $username
     * @return int
     * @throws \Exception in case delete failed.
     */
    public function actionDelete($username)
    {
        $user = User::find()->where(['username' => $username])->one();

        if (! $user) {
            echo "User '{$username}' not found\n";
            return self::EXIT_CODE_USER_NOT_FOUND;
        }

        if ($this->_confirm("Confirm delete user '{$username}'?", false)) {
            try {
                if ($user->delete() === false) {
                    echo "Some error occurred while deleting user '{$username}'\n";
                    return self::EXIT_CODE_ERROR;
                } else {
                    echo "User '{$username} successfully deleted\n";
                    return self::EXIT_CODE_NORMAL;
                }
            } catch (\yii\db\Exception $e) {
                echo "DB error occurred while deleting user '{$username}'':\n" . $e->getMessage() . "\n";
                return self::EXIT_CODE_DB_ERROR;
            }
        }

        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Выполняет запрос пароля от пользователя
     *
     * @param string $prompt
     * @return string
     */
    private function _promptPassword($prompt = 'Enter new password: ')
    {
        if (stripos(PHP_OS, 'win') === 0) {
            $vbScript = sys_get_temp_dir() . 'prompt_password.vbs';
            file_put_contents(
                $vbScript, 'wscript.echo(InputBox("'
                . addslashes($prompt)
                . '", "", ""))');
            $command = 'cscript //nologo ' . escapeshellarg($vbScript);
            $password = rtrim(shell_exec($command));
            unlink($vbScript);
            return $password;
        } else {
            $command = "/usr/bin/env bash -c 'echo OK'";
            if (rtrim(shell_exec($command)) !== 'OK') {
                trigger_error("Can't invoke bash");
                return '';
            }
            $command = "/usr/bin/env bash -c 'read -s -p \""
                . addslashes($prompt)
                . "\" mypassword && echo \$mypassword'";
            $password = rtrim(shell_exec($command));
            echo "\n";
            return $password;
        }
    }

    /**
     * Запрашивает от пользователя подтверждение какого-либо действия (y/n)
     *
     * @param string $prompt            Строка вопроса
     * @param null $default             OPTIONAL Выбор по умолчанию; срабатывает при вводе пустой строки
     * @return bool
     */
    private function _confirm($prompt, $default = null)
    {
        do {
            echo "{$prompt} (yes|no)";
            if ($default !== null) {
                echo ' [' . ($default ? 'yes' : 'no') . ']';
            }
            echo ': ';
            $input = strtolower(trim(fgets(STDIN)));

            if ($input === '' && $default !== null) {
                return $default;
            }

            if (preg_match('/^y(?:es)?$/i', $input)) {
                return true;
            }
            if (preg_match('/^no?$/i', $input)) {
                return false;
            }
        } while (true);
        return false;
    }
}
