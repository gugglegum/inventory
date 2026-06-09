<?php

declare(strict_types=1);

namespace backend\models;

use common\models\Item;
use yii\base\Model;

/**
 * Форма создания и редактирования предмета.
 *
 * Принимает сырые POST-значения как строки, выполняет валидацию пользовательского ввода
 * и только после этого записывает типизированные значения в связанную AR-модель Item.
 *
 * @property-read bool $isNewRecord Признак того, что связанный предмет еще не сохранен.
 */
final class ItemForm extends Model
{
    /**
     * ID предмета внутри репозитория для update-сценария.
     */
    public string $itemId = '';

    /**
     * ID родительского контейнера внутри репозитория.
     */
    public string $parentItemId = '';

    /**
     * Пользовательское название предмета.
     */
    public string $name = '';

    /**
     * Пользовательское описание предмета.
     */
    public string $description = '';

    /**
     * Флаг контейнера из checkbox-поля формы.
     */
    public string $isContainer = '0';

    /**
     * Приоритет ручной сортировки.
     */
    public string $priority = '0';

    /**
     * AR-модель, в которую форма сохраняет провалидированные значения.
     */
    private Item $item;

    /**
     * Создает форму вокруг подготовленной AR-модели Item.
     */
    public function __construct(Item $item, array $config = [])
    {
        $this->item = $item;
        parent::__construct($config);
        $this->scenario = $item->scenario;
        $this->fillFromItem();
    }

    /**
     * Сохраняет прежнее имя HTML-формы после отделения form-модели от Item.
     */
    public function formName(): string
    {
        return 'Item';
    }

    /**
     * Наборы полей для создания и редактирования предмета.
     */
    public function scenarios(): array
    {
        $scenarios = parent::scenarios();
        $scenarios[Item::SCENARIO_CREATE] = ['parentItemId', 'name', 'description', 'isContainer', 'priority'];
        $scenarios[Item::SCENARIO_UPDATE] = ['itemId', 'parentItemId', 'name', 'description', 'isContainer', 'priority'];

        return $scenarios;
    }

    /**
     * Правила валидации сырых строковых значений формы.
     */
    public function rules(): array
    {
        return [
            [['itemId', 'parentItemId', 'name', 'description', 'priority'], 'filter', 'filter' => 'trim'],
            [['itemId', 'name', 'isContainer'], 'required'],
            [['itemId', 'parentItemId', 'isContainer', 'priority'], 'integer'],
            [['parentItemId'], 'validateParentExists'],
            [['parentItemId'], 'validateParentIsNotLooped'],
            [['description'], 'string'],
            [['name'], 'string', 'max' => 200],
        ];
    }

    /**
     * Подписи полей совпадают с Item, чтобы UI не менялся.
     */
    public function attributeLabels(): array
    {
        return $this->item->attributeLabels();
    }

    /**
     * Возвращает связанную AR-модель предмета.
     */
    public function getItem(): Item
    {
        return $this->item;
    }

    /**
     * Признак создания нового предмета для шаблонов формы.
     */
    public function getIsNewRecord(): bool
    {
        return $this->item->isNewRecord;
    }

    /**
     * Проверяет существование родительского контейнера в текущем репозитории.
     */
    public function validateParentExists(string $attribute): void
    {
        if ($this->parentItemId === '' || $this->hasErrors($attribute)) {
            return;
        }

        if ($this->findParentItem() === null) {
            $this->addError($attribute, 'Родительский предмет не существует');
        }
    }

    /**
     * Проверяет, что новый родитель не является самим предметом или его потомком.
     */
    public function validateParentIsNotLooped(string $attribute): void
    {
        if ($this->parentItemId === '' || $this->item->isNewRecord || $this->hasErrors($attribute)) {
            return;
        }

        $parentItem = $this->findParentItem();
        while ($parentItem !== null) {
            if ((int) $parentItem->id === (int) $this->item->id) {
                $this->addError($attribute, 'Родительский предмет является одновременно дочерним (что образует бесконечную цепочку вложенности предметов)');
                return;
            }
            $parentItem = $parentItem->parentItem;
        }
    }

    /**
     * Валидирует форму, переносит значения в Item и сохраняет AR-модель.
     *
     * @throws \yii\db\Exception
     */
    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        if ($this->scenario === Item::SCENARIO_UPDATE) {
            $this->item->itemId = (int) $this->itemId;
        }
        $this->item->parentItemId = $this->parentItemId !== '' ? (int) $this->parentItemId : null;
        $this->item->name = $this->name;
        $this->item->description = $this->description;
        $this->item->isContainer = (int) $this->isContainer;
        $this->item->priority = $this->priority !== '' ? (int) $this->priority : 0;

        if (!$this->item->save()) {
            $this->addErrors($this->item->errors);
            return false;
        }

        return true;
    }

    /**
     * Заполняет строковые поля формы текущим состоянием Item.
     */
    private function fillFromItem(): void
    {
        $this->itemId = $this->stringify($this->item->getAttribute('itemId'));
        $this->parentItemId = $this->stringify($this->item->getAttribute('parentItemId'));
        $this->name = $this->stringify($this->item->getAttribute('name'));
        $this->description = $this->stringify($this->item->getAttribute('description'));
        $this->isContainer = $this->stringify($this->item->getAttribute('isContainer'));
        $this->priority = $this->stringify($this->item->getAttribute('priority'));
    }

    /**
     * Возвращает родителя по пользовательскому itemId в рамках текущего репозитория.
     */
    private function findParentItem(): ?Item
    {
        return Item::findOne([
            'repoId' => $this->item->repoId,
            'itemId' => (int) $this->parentItemId,
        ]);
    }

    /**
     * Приводит значение AR-атрибута к строке формы.
     */
    private function stringify(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }
}
