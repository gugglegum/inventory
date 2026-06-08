<?php

declare(strict_types=1);

namespace backend\services;

use common\components\ItemAccessValidator;
use common\helpers\ValidateErrorsFormatter;
use common\models\Item;
use common\models\Repo;
use yii\base\Exception;
use yii\web\User;

final class ItemImportService
{
    private const array PROPERTY_ALIASES = [
        'метки' => 'tags',
        'теги' => 'tags',
        'тэги' => 'tags',
        'desc' => 'description',
        'описание' => 'description',
        'cont' => 'container',
        'контейнер' => 'container',
        'конт' => 'container',
    ];

    private const array SUPPORTED_PROPERTIES = [
        'description',
        'tags',
        'container',
    ];

    /**
     * @throws Exception
     * @throws \yii\db\Exception
     */
    public function import(
        Repo $repo,
        Item $parentItem,
        ?string $text,
        bool $confirm,
        User $user,
        ItemAccessValidator $itemAccessValidator,
    ): ItemImportResult {
        $parseResult = $this->parse((string) $text);
        $firstItemAnchor = null;

        if ($confirm && !$parseResult->hasError()) {
            foreach ($parseResult->items as $item) {
                $itemModel = new Item();
                $itemModel->scenario = Item::SCENARIO_CREATE;
                $itemModel->setItemAccessValidator($itemAccessValidator);
                $itemModel->repoId = $repo->id;
                $itemModel->name = $item['name'];
                $itemModel->parentItemId = $parentItem->itemId;
                $itemModel->isContainer = !empty($item['container']) ? '1' : '0';
                $itemModel->description = $item['description'] ?? '';
                $itemModel->createdBy = $user->id;

                if (!$itemModel->save()) {
                    throw new Exception(ValidateErrorsFormatter::getMessage($itemModel));
                }

                if (isset($item['tags'])) {
                    $itemModel->saveTagsFromString($item['tags']);
                }

                if ($firstItemAnchor === null) {
                    $firstItemAnchor = 'item' . $itemModel->repoId . '-' . $itemModel->itemId;
                }
            }
        }

        return new ItemImportResult(
            $parseResult->text,
            $parseResult->items,
            $parseResult->errorLine,
            $parseResult->errorStr,
            $parseResult->errorMsg,
            $firstItemAnchor,
        );
    }

    public function parse(string $text): ItemImportResult
    {
        /** @var list<array{name:string, description?:string, tags?:string, container?:string}> $items */
        $items = [];
        $errorLine = null;
        $errorStr = null;
        $errorMsg = null;
        $line = 1;
        /** @var array{name?:string, description?:string, tags?:string, container?:string} $item */
        $item = [];

        $addProperty = static function (string $key, string $value) use (&$item): void {
            if (!in_array($key, self::SUPPORTED_PROPERTIES, true)) {
                throw new Exception('Unknown property "' . $key . '"');
            }
            if ($key === 'container') {
                $value = $value ? '1' : '0';
            }
            if (array_key_exists($key, $item)) {
                $item[$key] = match ($key) {
                    'description' => $item[$key] . "\n" . $value,
                    'tags' => $item[$key] . ', ' . $value,
                    default => $value,
                };
            } else {
                $item[$key] = $value;
            }
        };

        $str = '';
        try {
            foreach (explode("\n", $text) as $str) {
                $str = trim($str);

                if ($str === '') {
                    continue;
                }

                switch ($str[0]) {
                    case '*':
                        if (preg_match('/^\*\s*(\w+)\s*:\s*(.*)$/ui', $str, $m)) {
                            $key = mb_strtolower(trim($m[1]));
                            $key = self::PROPERTY_ALIASES[$key] ?? $key;
                            $value = trim($m[2]);
                            $addProperty($key, $value);
                        } else {
                            throw new Exception('Invalid property line format');
                        }
                        break;
                    case '!':
                        $addProperty('description', trim(mb_substr($str, 1)));
                        break;
                    case '#':
                        $addProperty('tags', trim(mb_substr($str, 1)));
                        break;
                    default:
                        if (isset($item['name'])) {
                            $items[] = $item;
                            $item = [];
                        }
                        $item['name'] = $str;
                }
                $line++;
            }
            if (isset($item['name'])) {
                $items[] = $item;
            }
        } catch (Exception $e) {
            $errorLine = $line;
            $errorStr = $str;
            $errorMsg = $e->getMessage();
        }

        return new ItemImportResult($text, $items, $errorLine, $errorStr, $errorMsg);
    }
}
