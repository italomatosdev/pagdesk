<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordBase;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notificação de redefinição de senha em português.
 */
class ResetPasswordNotification extends ResetPasswordBase
{
    /**
     * Monta a mensagem de e-mail em português.
     *
     * @param  string  $url
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    protected function buildMailMessage($url)
    {
        $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Notificação de redefinição de senha')
            ->line('Você está recebendo este e-mail porque recebemos uma solicitação de redefinição de senha para sua conta.')
            ->action('Redefinir senha', $url)
            ->line('Este link expira em '.$expire.' minutos.')
            ->line('Se você não solicitou a redefinição de senha, nenhuma ação é necessária.')
            ->salutation('Atenciosamente,'."\n".config('app.name'));
    }
}
