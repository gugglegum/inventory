<?php

declare(strict_types=1);

namespace backend\services;

use common\components\ItemAccessValidator;
use common\helpers\ValidateErrorsFormatter;
use common\models\Item;
use common\models\Repo;
use Throwable;
use yii\base\Exception;
use yii\web\User;

/**
 * Разбирает текстовый импорт предметов и создает дочерние предметы после подтверждения пользователем.
 *
 * Поддерживает строки названий, короткие маркеры описания/тегов и свойства в формате `* ключ: значение`.
 */
final class ItemImportService
{
    /**
     * Алиасы пользовательских названий свойств к внутренним ключам импорта.
     *
     * @var array<string, string>
     */
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

    /**
     * Набор свойств, которые разрешено задавать в строках `* ключ: значение`.
     *
     * @var list<string>
     */
    private const array SUPPORTED_PROPERTIES = [
        'description',
        'tags',
        'container',
    ];

    /**
     * Разбирает текст импорта и, если пользователь подтвердил операцию, создает предметы внутри родительского контейнера.
     *
     * @param Repo $repo Репозиторий, в котором создаются предметы.
     * @param Item $parentItem Родительский контейнер для импортируемых предметов.
     * @param ?string $text Пользовательский текст импорта.
     * @param bool $confirm Нужно ли сохранять распознанные предметы в базе.
     * @param User $user Текущий пользователь, который будет записан как автор созданных предметов.
     * @param ItemAccessValidator $itemAccessValidator Валидатор прав для создаваемых предметов.
     *
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
            $firstItemAnchor = $this->createItems(
                $repo,
                $parentItem,
                $parseResult->items,
                $user,
                $itemAccessValidator,
            );
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

    /**
     * Разбирает текст импорта в список предметов без сохранения в базе.
     *
     * Поддерживаемый формат:
     * - строка без префикса начинает новый предмет и задает его название;
     * - `!текст` добавляет строку описания;
     * - `#теги` добавляет теги;
     * - `* свойство: значение` задает описание, теги или флаг контейнера.
     */
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
                        if (!preg_match('/^\*\s*(\w+)\s*:\s*(.*)$/ui', $str, $m)) {
                            throw new Exception('Invalid property line format');
                        }
                        $key = mb_strtolower(trim($m[1]));
                        $key = self::PROPERTY_ALIASES[$key] ?? $key;
                        $value = trim($m[2]);
                        $addProperty($key, $value);
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

    /**
     * Создает распознанные предметы одной атомарной пачкой.
     *
     * Если сохранение любого предмета или его тегов завершается ошибкой, откатываются все уже созданные предметы,
     * теги и обновление счетчика `repo.lastItemId`.
     *
     * @param Repo $repo Репозиторий, в котором создаются предметы.
     * @param Item $parentItem Родительский контейнер для импортируемых предметов.
     * @param list<array{name:string, description?:string, tags?:string, container?:string}> $items Распознанные предметы.
     * @param User $user Текущий пользователь, который будет записан как автор созданных предметов.
     * @param ItemAccessValidator $itemAccessValidator Валидатор прав для создаваемых предметов.
     *
     * @return ?string HTML-якорь первого созданного предмета.
     * @throws Exception
     * @throws \yii\db\Exception
     */
    private function createItems(
        Repo $repo,
        Item $parentItem,
        array $items,
        User $user,
        ItemAccessValidator $itemAccessValidator,
    ): ?string {
        $transaction = Item::getDb()->beginTransaction();
        $firstItemAnchor = null;

        try {
            foreach ($items as $item) {
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

            $transaction->commit();

            return $firstItemAnchor;
        } catch (Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }

            throw $e;
        }
    }
}
