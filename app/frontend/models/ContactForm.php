<?php

namespace frontend\models;

use Yii;
use yii\base\Model;

/**
 * ContactForm is the model behind the contact form.
 */
class ContactForm extends Model
{
    /**
     * @var string|null
     */
    public $name;

    /**
     * @var string|null
     */
    public $email;

    /**
     * @var string|null
     */
    public $subject;

    /**
     * @var string|null
     */
    public $body;

    /**
     * @var string|null
     */
    public $verifyCode;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            // name, email, subject and body are required
            [['name', 'email', 'subject', 'body'], 'required'],
            // email has to be a valid email address
            ['email', 'email'],
            // verifyCode needs to be entered correctly
            ['verifyCode', 'captcha'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'verifyCode' => 'Verification Code',
        ];
    }

    /**
     * Sends an email to the specified email address using the information collected by this model.
     *
     * @param  string  $email the target email address
     * @return boolean whether the email was sent
     */
    public function sendEmail(string $email): bool
    {
        return Yii::$app->mailer->compose()
            ->setTo($email)
            ->setFrom([(string) $this->email => (string) $this->name])
            ->setSubject((string) $this->subject)
            ->setTextBody((string) $this->body)
            ->send();
    }
}
