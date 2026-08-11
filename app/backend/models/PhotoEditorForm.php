<?php

declare(strict_types=1);

namespace backend\models;

use JsonException;
use yii\base\Model;

/**
 * Состояние редактора фотографий, отправляемое вместе с формой предмета или заметки.
 *
 * Manifest хранит единый упорядоченный список существующих связей и временных
 * загрузок. Revision остается неизменной на клиенте и защищает от потери
 * параллельных изменений списка фотографий.
 */
final class PhotoEditorForm extends Model
{
    public string $sessionToken = '';

    public string $manifest = '[]';

    public string $revision = '';

    /** @var list<array{type:'existing'|'temporary', id:int}> */
    private array $entries = [];

    public function formName(): string
    {
        return 'PhotoEditor';
    }

    /**
     * @param list<array{type:'existing', id:int}> $initialEntries
     */
    public function __construct(array $initialEntries = [], string $revision = '', array $config = [])
    {
        parent::__construct($config);

        $encodedManifest = json_encode($initialEntries, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->manifest = $encodedManifest;
        $this->revision = $revision;
        $this->entries = $initialEntries;
    }

    public function rules(): array
    {
        return [
            [['sessionToken', 'manifest', 'revision'], 'string'],
            ['sessionToken', 'match', 'pattern' => '/\A[a-f0-9]{64}\z/', 'skipOnEmpty' => true],
            ['revision', 'match', 'pattern' => '/\A[a-f0-9]{64}\z/', 'skipOnEmpty' => true],
            ['manifest', 'validateManifest'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'sessionToken' => 'Сессия загрузки фотографий',
            'manifest' => 'Список фотографий',
            'revision' => 'Версия списка фотографий',
        ];
    }

    /**
     * @return list<array{type:'existing'|'temporary', id:int}>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function hasTemporaryEntries(): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry['type'] === 'temporary') {
                return true;
            }
        }

        return false;
    }

    public function validateManifest(string $attribute): void
    {
        if ($this->hasErrors($attribute)) {
            return;
        }

        try {
            $decoded = json_decode($this->manifest, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->addError($attribute, 'Список фотографий поврежден. Обновите страницу и повторите изменения.');
            return;
        }

        if (!is_array($decoded) || !array_is_list($decoded) || count($decoded) > 500) {
            $this->addError($attribute, 'Список фотографий имеет недопустимый формат.');
            return;
        }

        $entries = [];
        $seenEntries = [];
        foreach ($decoded as $rawEntry) {
            if (!is_array($rawEntry)) {
                $this->addError($attribute, 'Список фотографий имеет недопустимый формат.');
                return;
            }

            $type = $rawEntry['type'] ?? null;
            $id = $rawEntry['id'] ?? null;
            if (
                ($type !== 'existing' && $type !== 'temporary')
                || !is_int($id)
                || $id <= 0
            ) {
                $this->addError($attribute, 'Список фотографий содержит недопустимую запись.');
                return;
            }

            $entryKey = $type . ':' . $id;
            if (isset($seenEntries[$entryKey])) {
                $this->addError($attribute, 'Одна фотография не может присутствовать в списке несколько раз.');
                return;
            }
            $seenEntries[$entryKey] = true;
            $entries[] = [
                'type' => $type,
                'id' => $id,
            ];
        }

        if ($this->containsTemporaryEntry($entries) && $this->sessionToken === '') {
            $this->addError($attribute, 'Для временных фотографий потеряна сессия загрузки.');
            return;
        }

        $this->entries = $entries;
    }

    /**
     * @param list<array{type:'existing'|'temporary', id:int}> $entries
     */
    private function containsTemporaryEntry(array $entries): bool
    {
        foreach ($entries as $entry) {
            if ($entry['type'] === 'temporary') {
                return true;
            }
        }

        return false;
    }
}
