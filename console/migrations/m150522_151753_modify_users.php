<?php

use yii\db\Migration;

class m150522_151753_modify_users extends Migration
{
    public function up()
    {
        $this->renameTable('user', 'users');
        $this->alterColumn('users', 'id', 'INT UNSIGNED NOT NULL AUTO_INCREMENT');
        $this->renameColumn('users', 'auth_key', 'authKey');
        $this->renameColumn('users', 'password_hash', 'passwordHash');
        $this->renameColumn('users', 'password_reset_token', 'passwordResetToken');
        $this->renameColumn('users', 'created_at', 'created');
        $this->renameColumn('users', 'updated_at', 'updated');
    }

    public function down()
    {
        $this->alterColumn('users', 'id', 'INT NOT NULL AUTO_INCREMENT');
        $this->renameColumn('users', 'authKey', 'auth_key');
        $this->renameColumn('users', 'passwordHash', 'password_hash');
        $this->renameColumn('users', 'passwordResetToken', 'password_reset_token');
        $this->renameColumn('users', 'created', 'created_at');
        $this->renameColumn('users', 'updated', 'updated_at');
        $this->renameTable('users', 'user');
    }
}
