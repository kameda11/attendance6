<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class CustomVerifyEmailNotification extends VerifyEmail
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail'];
    }


    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('【勤怠管理システム】メールアドレスの認証をお願いします')
            ->greeting($notifiable->name . ' 様')
            ->line('勤怠管理システムにご登録いただき、ありがとうございます。')
            ->line('メールアドレスの認証を完了するために、以下のボタンをクリックしてください。')
            ->action('メールアドレスを認証する', $verificationUrl)
            ->line('このメールに心当たりがない場合は、無視していただいて構いません。')
            ->salutation('よろしくお願いいたします。');
    }


    protected function verificationUrl($notifiable)
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable);
        }

        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
