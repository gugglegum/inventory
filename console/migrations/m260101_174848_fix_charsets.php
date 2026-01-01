<?php

use yii\db\Migration;

class m260101_174848_fix_charsets extends Migration
{
    private const CHARSET = 'utf8mb4';
    private const COLLATE = 'utf8mb4_unicode_ci';

    /**
     * {@inheritdoc}
     */
    public function safeUp():void
    {
        // 1) Ensure current connection talks utf8mb4
        $this->execute('SET NAMES ' . self::CHARSET);

        // 2) Convert current database defaults
        // (Yii dbname is already selected; DATABASE() returns it)
        $dbName = (string)$this->db->createCommand('SELECT DATABASE()')->queryScalar();
        if ($dbName === '') {
            throw new \RuntimeException('No database selected (DATABASE() returned empty).');
        }

        // Quote with backticks to be safe
        $quotedDb = str_replace('`', '``', $dbName);
        $this->execute("ALTER DATABASE `{$quotedDb}` CHARACTER SET " . self::CHARSET . " COLLATE " . self::COLLATE);

        // 3) Convert every base table
        $tables = $this->db->createCommand("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_type = 'BASE TABLE'
        ")->queryColumn();

        $this->execute('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $table) {
            // Information_schema returns raw table_name without prefix markers.
            // We'll quote it ourselves (no Yii {{%...}} here).
            $quotedTable = str_replace('`', '``', (string)$table);
            $this->execute(
                "ALTER TABLE `{$quotedTable}` CONVERT TO CHARACTER SET " . self::CHARSET . " COLLATE " . self::COLLATE
            );
        }

        $this->execute('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): bool
    {
        // Обычно откатывать обратно кодировку бессмысленно/опасно:
        // данные могут содержать 4-байтовые символы (emoji), которые utf8mb3 потеряет.
        return false;
    }
}
