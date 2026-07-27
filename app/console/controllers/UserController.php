<?php

namespace console\controllers;

use common\helpers\ValidateErrorsFormatter;
use common\models\User;
use common\services\SsoUserLinkException;
use common\services\SsoUserLinker;
use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\console\Controller;
use yii\console\ExitCode;

class UserController extends Controller
{
    public const int EXIT_CODE_USER_EXISTS = 2;
    public const int EXIT_CODE_EMAIL_EXISTS = 3;
    public const int EXIT_CODE_PASSWORD_TOO_SHORT = 4;
    public const int EXIT_CODE_DB_ERROR = 5;
    public const int EXIT_CODE_USER_NOT_FOUND = 6;

    /**
     * Create new user
     *
     * @param string $username
     * @param string $email
     * @return int
     * @throws Exception on failure
     * @throws InvalidConfigException
     */
    public function actionCreate(string $username, string $email): int
    {
        if (User::find()->where(['username' => $username])->exists()) {
            echo "User with name '{$username}' already exists\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
        if (User::find()->where(['email' => $email])->exists()) {
            echo "User with e-mail '{$email}' already exists\n";
            return ExitCode::UNSPECIFIED_ERROR;
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
                return ExitCode::UNSPECIFIED_ERROR;
            }
        } catch (\yii\db\Exception $e) {
            echo "DB error occurred while creating new user '{$username}':\n" . $e->getMessage() . "\n";
            return self::EXIT_CODE_DB_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * Change password for specified user
     *
     * @param string $username
     * @return int
     * @throws Exception on failure
     * @throws InvalidConfigException
     */
    public function actionChangePassword(string $username): int
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
                return ExitCode::UNSPECIFIED_ERROR;
            }
        } catch (\yii\db\Exception $e) {
            echo "DB error occurred while updating user '{$username}'':\n" . $e->getMessage() . "\n";
            return self::EXIT_CODE_DB_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * Явно связывает существующего активного пользователя с доверенным OIDC subject.
     */
    public function actionLinkSso(string $identifier, string $subject): int
    {
        $issuer = Yii::$app->params['oidc']['issuer'] ?? null;
        if (!is_string($issuer) || $issuer === '') {
            echo "Не удалось привязать пользователя к SSO: OIDC issuer не настроен.\n";

            return ExitCode::UNSPECIFIED_ERROR;
        }

        try {
            $user = (new SsoUserLinker())->prelink($identifier, $issuer, $subject);
        } catch (SsoUserLinkException $exception) {
            echo 'Не удалось привязать пользователя к SSO: ' . $exception->getMessage() . "\n";

            return ExitCode::UNSPECIFIED_ERROR;
        }

        echo "Пользователь '{$user->username}' успешно привязан к Pyrda SSO ({$issuer}).\n";

        return ExitCode::OK;
    }

    /**
     * Delete user
     *
     * @param string $username
     * @return int
     * @throws \Exception in case delete failed.
     */
    public function actionDelete(string $username): int
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
                    return ExitCode::UNSPECIFIED_ERROR;
                } else {
                    echo "User '{$username} successfully deleted\n";
                    return ExitCode::OK;
                }
            } catch (\yii\db\Exception $e) {
                echo "DB error occurred while deleting user '{$username}'':\n" . $e->getMessage() . "\n";
                return self::EXIT_CODE_DB_ERROR;
            }
        }

        return ExitCode::OK;
    }

    /**
     * Выполняет запрос пароля от пользователя
     *
     * @param string $prompt
     * @return string
     * @psalm-suppress ForbiddenCode Shell commands are used intentionally to request a hidden console password.
     */
    private function _promptPassword(string $prompt = 'Enter new password: '): string
    {
        if (stripos(PHP_OS, 'win') === 0) {
            $vbScript = sys_get_temp_dir() . 'prompt_password.vbs';
            file_put_contents(
                $vbScript, 'wscript.echo(InputBox("'
                . addslashes($prompt)
                . '", "", ""))');
            $command = 'cscript //nologo ' . escapeshellarg($vbScript);
            $output = shell_exec($command);
            $password = is_string($output) ? rtrim($output) : '';
            unlink($vbScript);
            return $password;
        } else {
            $command = "/usr/bin/env bash -c 'echo OK'";
            $output = shell_exec($command);
            if (!is_string($output) || rtrim($output) !== 'OK') {
                trigger_error("Can't invoke bash");
                return '';
            }
            $command = "/usr/bin/env bash -c 'read -s -p \""
                . addslashes($prompt)
                . "\" mypassword && echo \$mypassword'";
            $output = shell_exec($command);
            $password = is_string($output) ? rtrim($output) : '';
            echo "\n";
            return $password;
        }
    }

    /**
     * Запрашивает от пользователя подтверждение какого-либо действия (y/n)
     *
     * @param string $prompt Строка вопроса.
     * @param bool|null $default OPTIONAL Выбор по умолчанию; срабатывает при вводе пустой строки.
     * @return bool
     */
    private function _confirm(string $prompt, ?bool $default = null): bool
    {
        do {
            echo "{$prompt} (yes|no)";
            if ($default !== null) {
                echo ' [' . ($default ? 'yes' : 'no') . ']';
            }
            echo ': ';
            $line = fgets(STDIN);
            $input = $line !== false ? strtolower(trim($line)) : '';

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
    }
}
