<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * L'e-mail qui porte le lien de réinitialisation.
 *
 * POURQUOI NE PAS GARDER CELUI DE LARAVEL
 * ----------------------------------------
 * Le message livré d'origine est en anglais et signé « Laravel ». Il partirait tel quel vers la
 * seule boîte qui compte ici — la vôtre — et, le jour où un collaborateur aura un compte, vers la
 * sienne. Un message d'authentification qui ne ressemble pas au produit se lit comme un
 * hameçonnage : on apprend à s'en méfier, puis on ne clique plus sur le vrai.
 *
 * CE QUE LE TEXTE DOIT DIRE, ET QUI N'EST PAS DÉCORATIF
 * -----------------------------------------------------
 * Deux phrases font tout le travail de sécurité :
 *
 *   · « ce lien expire dans N minutes et ne fonctionne qu'une fois » — pour qu'on ne le range pas
 *     dans un carnet, et qu'on ne s'étonne pas qu'il échoue demain ;
 *   · « si vous n'êtes pas à l'origine de cette demande, ignorez ce message » — parce que recevoir
 *     ce mail SANS l'avoir demandé est le premier signe qu'on essaie d'entrer chez vous, et que le
 *     réflexe correct est alors de ne rien faire, pas de cliquer pour « vérifier ».
 */
class ReinitialiserMotDePasse extends Notification
{
    public function __construct(public string $jeton) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // La durée n'est pas écrite en dur : elle est lue là où elle est décidée
        // (config/auth.php → passwords.users.expire). Un chiffre recopié dans un texte finit
        // toujours par mentir le jour où on change le réglage.
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        $lien = route('mot-de-passe.reinitialiser', [
            'token' => $this->jeton,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe — '.config('app.name'))
            ->greeting('Bonjour,')
            ->line('Une réinitialisation du mot de passe de votre compte '.config('app.name').' a été demandée.')
            ->action('Choisir un nouveau mot de passe', $lien)
            ->line("Ce lien expire dans {$minutes} minutes et ne fonctionne qu'une seule fois.")
            ->line("Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe reste inchangé, et personne n'a eu accès à votre compte.")
            ->salutation('— '.config('app.name'));
    }
}
