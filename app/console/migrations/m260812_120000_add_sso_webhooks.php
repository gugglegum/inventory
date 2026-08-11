<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Добавляет версии SSO-состояния и журнал принятых profile/access webhooks.
 */
final class m260812_120000_add_sso_webhooks extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%user}}',
            'ssoProfileVersion',
            (string) $this->bigInteger()->unsigned()->null()->after('ssoClaims')
        );
        $this->addColumn(
            '{{%user}}',
            'ssoDisabledAt',
            (string) $this->integer()->unsigned()->null()->after('ssoProfileVersion')
        );
        $this->addColumn(
            '{{%user}}',
            'ssoAccessVersion',
            (string) $this->bigInteger()->unsigned()->null()->after('ssoDisabledAt')
        );
        $this->addColumn(
            '{{%user}}',
            'ssoSessionVersion',
            (string) $this->bigInteger()->unsigned()->null()->after('ssoAccessVersion')
        );

        $this->createIndex('idx_user_ssoProfileVersion', '{{%user}}', 'ssoProfileVersion');
        $this->createIndex('idx_user_ssoDisabledAt', '{{%user}}', 'ssoDisabledAt');
        $this->createIndex('idx_user_ssoAccessVersion', '{{%user}}', 'ssoAccessVersion');
        $this->createIndex('idx_user_ssoSessionVersion', '{{%user}}', 'ssoSessionVersion');

        $this->createTable('{{%sso_profile_webhook_delivery}}', [
            'id' => $this->primaryKey()->unsigned(),
            'eventId' => $this->char(36)->notNull(),
            'eventType' => $this->string(64)->notNull(),
            'ssoSubject' => 'VARBINARY(255) NOT NULL',
            'payload' => 'MEDIUMTEXT NOT NULL',
            'processedAt' => $this->integer()->unsigned()->notNull(),
            'created' => $this->integer()->unsigned()->notNull(),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT="Принятые profile webhooks Pyrda SSO"');
        $this->execute(
            'ALTER TABLE {{%sso_profile_webhook_delivery}} '
            . 'MODIFY [[eventId]] CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, '
            . 'MODIFY [[eventType]] VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL'
        );
        $this->createIndex(
            'uq_sso_profile_webhook_delivery_eventId',
            '{{%sso_profile_webhook_delivery}}',
            'eventId',
            true
        );
        $this->createIndex(
            'idx_sso_profile_webhook_delivery_subject',
            '{{%sso_profile_webhook_delivery}}',
            'ssoSubject'
        );

        $this->createTable('{{%sso_access_webhook_delivery}}', [
            'id' => $this->primaryKey()->unsigned(),
            'eventId' => $this->char(36)->notNull(),
            'eventType' => $this->string(64)->notNull(),
            'ssoSubject' => 'VARBINARY(255) NOT NULL',
            'accessVersion' => $this->bigInteger()->unsigned()->null(),
            'sessionVersion' => $this->bigInteger()->unsigned()->null(),
            'reason' => $this->string(255)->notNull(),
            'payload' => 'MEDIUMTEXT NOT NULL',
            'processedAt' => $this->integer()->unsigned()->notNull(),
            'created' => $this->integer()->unsigned()->notNull(),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT="Принятые access webhooks Pyrda SSO"');
        $this->execute(
            'ALTER TABLE {{%sso_access_webhook_delivery}} '
            . 'MODIFY [[eventId]] CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, '
            . 'MODIFY [[eventType]] VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL'
        );
        $this->createIndex(
            'uq_sso_access_webhook_delivery_eventId',
            '{{%sso_access_webhook_delivery}}',
            'eventId',
            true
        );
        $this->createIndex(
            'idx_sso_access_webhook_delivery_subject',
            '{{%sso_access_webhook_delivery}}',
            'ssoSubject'
        );
        $this->createIndex(
            'idx_sso_access_webhook_delivery_accessVersion',
            '{{%sso_access_webhook_delivery}}',
            'accessVersion'
        );
        $this->createIndex(
            'idx_sso_access_webhook_delivery_sessionVersion',
            '{{%sso_access_webhook_delivery}}',
            'sessionVersion'
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%sso_access_webhook_delivery}}');
        $this->dropTable('{{%sso_profile_webhook_delivery}}');

        $this->dropIndex('idx_user_ssoSessionVersion', '{{%user}}');
        $this->dropIndex('idx_user_ssoAccessVersion', '{{%user}}');
        $this->dropIndex('idx_user_ssoDisabledAt', '{{%user}}');
        $this->dropIndex('idx_user_ssoProfileVersion', '{{%user}}');
        $this->dropColumn('{{%user}}', 'ssoSessionVersion');
        $this->dropColumn('{{%user}}', 'ssoAccessVersion');
        $this->dropColumn('{{%user}}', 'ssoDisabledAt');
        $this->dropColumn('{{%user}}', 'ssoProfileVersion');
    }
}
