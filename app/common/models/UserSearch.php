<?php

namespace common\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * UserSearch represents the model behind the search form about `common\models\User`.
 */
class UserSearch extends Model
{
    public string $id = '';
    public string $username = '';
    public string $authKey = '';
    public string $passwordHash = '';
    public string $passwordResetToken = '';
    public string $email = '';
    public string $status = '';
    public string $created = '';
    public string $updated = '';

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['id', 'status', 'created', 'updated'], 'integer'],
            [['username', 'authKey', 'passwordHash', 'passwordResetToken', 'email'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios(): array
    {
        return Model::scenarios();
    }

    /**
     * Подписи полей совпадают с User, чтобы search UI не менялся.
     */
    public function attributeLabels(): array
    {
        return (new User())->attributeLabels();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search(array $params): ActiveDataProvider
    {
        $query = User::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id' => $this->integerOrNull($this->id),
            'status' => $this->integerOrNull($this->status),
            'created' => $this->integerOrNull($this->created),
            'updated' => $this->integerOrNull($this->updated),
        ]);

        $query->andFilterWhere(['like', 'username', $this->username])
            ->andFilterWhere(['like', 'authKey', $this->authKey])
            ->andFilterWhere(['like', 'passwordHash', $this->passwordHash])
            ->andFilterWhere(['like', 'passwordResetToken', $this->passwordResetToken])
            ->andFilterWhere(['like', 'email', $this->email]);

        return $dataProvider;
    }

    /**
     * Возвращает int для заполненного числового поля или null для пустого фильтра.
     */
    private function integerOrNull(string $value): ?int
    {
        return $value !== '' ? (int) $value : null;
    }
}
