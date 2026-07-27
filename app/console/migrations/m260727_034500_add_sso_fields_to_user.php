<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Добавляет данные привязки локального пользователя к Pyrda SSO.
 */
final class m260727_034500_add_sso_fields_to_user extends Migration
{
    private const string IDENTITY_INDEX = 'ux_user_ssoIdentity';

    private const string IDENTITY_CHECK = 'ck_user_ssoIdentity_complete';

    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%user}}',
            'ssoIssuer',
            'VARBINARY(255) NULL'
        );
        $this->addColumn(
            '{{%user}}',
            'ssoSubject',
            'VARBINARY(255) NULL'
        );
        $this->addColumn(
            '{{%user}}',
            'ssoClaims',
            (string) $this->json()->null()
        );
        $this->addColumn(
            '{{%user}}',
            'lastSsoLoginAt',
            (string) $this->integer()->null()
        );

        $this->createIndex(
            self::IDENTITY_INDEX,
            '{{%user}}',
            ['ssoIssuer', 'ssoSubject'],
            true
        );
        $this->addCheck(
            self::IDENTITY_CHECK,
            '{{%user}}',
            '([[ssoIssuer]] IS NULL AND [[ssoSubject]] IS NULL)'
            . ' OR ([[ssoIssuer]] IS NOT NULL AND [[ssoSubject]] IS NOT NULL)'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): void
    {
        $this->dropCheck(self::IDENTITY_CHECK, '{{%user}}');
        $this->dropIndex(self::IDENTITY_INDEX, '{{%user}}');
        $this->dropColumn('{{%user}}', 'lastSsoLoginAt');
        $this->dropColumn('{{%user}}', 'ssoClaims');
        $this->dropColumn('{{%user}}', 'ssoSubject');
        $this->dropColumn('{{%user}}', 'ssoIssuer');
    }
}
