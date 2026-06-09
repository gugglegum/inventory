<?php

declare(strict_types=1);

namespace backend\models;

use common\models\Post;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\base\Model;

/**
 * Форма создания и редактирования заметки к предмету.
 *
 * Хранит пользовательский ввод как строки, валидирует дату/текстовые поля и записывает
 * в Post уже нормализованные значения, включая unix timestamp для datetime.
 *
 * @property-read bool $isNewRecord Признак того, что связанная заметка еще не сохранена.
 */
final class PostForm extends Model
{
    /**
     * Дата и время заметки в пользовательском формате ДД.ММ.ГГГГ ЧЧ:ММ.
     */
    public string $datetimeText = '';

    /**
     * Заголовок заметки.
     */
    public string $title = '';

    /**
     * Текст заметки.
     */
    public string $text = '';

    /**
     * AR-модель, в которую форма сохраняет провалидированные значения.
     */
    private Post $post;

    /**
     * Unix timestamp, полученный из datetimeText после валидации.
     */
    private ?int $parsedDatetime = null;

    /**
     * Создает форму вокруг подготовленной AR-модели Post.
     */
    public function __construct(Post $post, ?string $defaultDatetimeText = null, array $config = [])
    {
        $this->post = $post;
        parent::__construct($config);
        $this->scenario = $post->scenario;
        $this->fillFromPost($defaultDatetimeText);
    }

    /**
     * Сохраняет прежнее имя HTML-формы после отделения form-модели от Post.
     */
    public function formName(): string
    {
        return 'Post';
    }

    /**
     * Наборы полей для создания и редактирования заметки.
     */
    public function scenarios(): array
    {
        $scenarios = parent::scenarios();
        $scenarios[Post::SCENARIO_CREATE] = ['datetimeText', 'title', 'text'];
        $scenarios[Post::SCENARIO_UPDATE] = ['datetimeText', 'title', 'text'];

        return $scenarios;
    }

    /**
     * Правила валидации сырых строковых значений формы.
     */
    public function rules(): array
    {
        return [
            [['datetimeText', 'title', 'text'], 'filter', 'filter' => 'trim'],
            [['datetimeText', 'title'], 'required'],
            [['title', 'text'], 'string'],
            [['title'], 'string', 'max' => 200],
            [['datetimeText'], 'validateDatetimeText'],
        ];
    }

    /**
     * Подписи полей формы заметки.
     */
    public function attributeLabels(): array
    {
        return array_merge($this->post->attributeLabels(), [
            'datetimeText' => 'Дата и время, к которому относится пост',
        ]);
    }

    /**
     * Возвращает связанную AR-модель заметки.
     */
    public function getPost(): Post
    {
        return $this->post;
    }

    /**
     * Признак создания новой заметки для шаблонов формы.
     */
    public function getIsNewRecord(): bool
    {
        return $this->post->isNewRecord;
    }

    /**
     * Проверяет пользовательскую дату и запоминает timestamp для save().
     */
    public function validateDatetimeText(string $attribute): void
    {
        if ($this->datetimeText === '' || $this->hasErrors($attribute)) {
            return;
        }

        $timezone = new DateTimeZone(Yii::$app->timeZone ?: 'UTC');
        $dateTime = DateTimeImmutable::createFromFormat('d.m.Y H:i', $this->datetimeText, $timezone);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $dateTime === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            $this->addError($attribute, 'Неверный формат даты/времени.');
            return;
        }

        $this->parsedDatetime = $dateTime->getTimestamp();
    }

    /**
     * Валидирует форму, переносит значения в Post и сохраняет AR-модель.
     */
    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $this->post->datetime = $this->parsedDatetime ?? 0;
        $this->post->title = $this->title;
        $this->post->text = $this->text;

        if (!$this->post->save()) {
            $this->addErrors($this->post->errors);
            return false;
        }

        return true;
    }

    /**
     * Заполняет строковые поля формы текущим состоянием Post.
     */
    private function fillFromPost(?string $defaultDatetimeText): void
    {
        $datetime = $this->post->getAttribute('datetime');
        if ($datetime !== null) {
            $this->datetimeText = Yii::$app->formatter->asDatetime($datetime, 'php:d.m.Y H:i');
        } elseif ($defaultDatetimeText !== null) {
            $this->datetimeText = $defaultDatetimeText;
        }

        $this->title = $this->stringify($this->post->getAttribute('title'));
        $this->text = $this->stringify($this->post->getAttribute('text'));
    }

    /**
     * Приводит значение AR-атрибута к строке формы.
     */
    private function stringify(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }
}
