<?php

namespace App\Metier\Notifications;

use App\Models\AbonnementPush;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * LES NOTIFICATIONS PUSH — un message qui arrive même l'écran éteint, l'application fermée.
 *
 * ============================================================================================
 * UN COMPTE, PLUSIEURS APPAREILS
 * ============================================================================================
 * `envoyer()` pousse vers TOUS les abonnements du compte : le téléphone à l'accueil et
 * l'ordinateur du bureau reçoivent le même message. Un abonnement qui répond « 410 Gone » — le
 * navigateur l'a lui-même invalidé, cache vidé ou application désinstallée — est supprimé sur le
 * champ : le garder enverrait indéfiniment dans le vide.
 *
 * ============================================================================================
 * PAS DE FILE D'ATTENTE, PAS DE JOB
 * ============================================================================================
 * L'envoi se fait en ligne, au moment du geste (un message posté, une annonce publiée). Le volume
 * de ce produit — une église, quelques dizaines à quelques centaines de comptes — ne justifie pas
 * la queue asynchrone que exigerait un envoi de masse : ce serait résoudre un problème qu'on n'a
 * pas.
 */
class PushNotifications
{
    /**
     * Pousse un message à tous les appareils abonnés d'un compte.
     *
     * Silencieux en cas d'échec réseau : une notification qui rate ne doit jamais faire échouer
     * le geste métier qui l'a déclenchée (envoyer un message, publier une annonce).
     */
    /**
     * MODE SIMULÉ, pour les tests : les envois sont notés au lieu d'être faits. L'envoi réel
     * parlerait à un vrai service de push par HTTP ; ce qu'on veut vérifier, c'est QUI est prévenu.
     *
     * @var list<array{destinataire: int, titre: string, corps: string, url: ?string}>|null
     */
    private static ?array $simules = null;

    public static function simuler(): void
    {
        self::$simules = [];
    }

    /** @return list<array{destinataire: int, titre: string, corps: string, url: ?string}> */
    public static function envoisSimules(): array
    {
        return self::$simules ?? [];
    }

    public static function arreterDeSimuler(): void
    {
        self::$simules = null;
    }

    public static function envoyer(User $destinataire, string $titre, string $corps, ?string $url = null): void
    {
        if (self::$simules !== null) {
            self::$simules[] = ['destinataire' => $destinataire->id, 'titre' => $titre, 'corps' => $corps, 'url' => $url];

            return;
        }

        $clePublique = config('webpush.cle_publique');
        $clePrivee = config('webpush.cle_privee');

        if (! $clePublique || ! $clePrivee) {
            return;
        }

        $abonnements = AbonnementPush::query()->where('user_id', $destinataire->id)->get();

        if ($abonnements->isEmpty()) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('webpush.sujet'),
                'publicKey' => $clePublique,
                'privateKey' => $clePrivee,
            ],
        ]);

        $charge = json_encode([
            'titre' => $titre,
            'corps' => $corps,
            'url' => $url,
        ]);

        foreach ($abonnements as $abonnement) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $abonnement->endpoint,
                    'publicKey' => $abonnement->cle_p256dh,
                    'authToken' => $abonnement->cle_auth,
                    'contentEncoding' => 'aes128gcm',
                ]),
                $charge,
            );
        }

        foreach ($webPush->flush() as $rapport) {
            if ($rapport->isSuccess()) {
                continue;
            }

            // 410 Gone (ou 404) : le navigateur a lui-même coupé cet abonnement. Le garder
            // enverrait indéfiniment dans le vide, à chaque message futur.
            if ($rapport->isSubscriptionExpired()) {
                AbonnementPush::query()
                    ->where('endpoint_hache', hash('sha256', $rapport->getEndpoint()))
                    ->delete();

                continue;
            }

            Log::warning('Notification push non délivrée.', [
                'destinataire_id' => $destinataire->id,
                'raison' => $rapport->getReason(),
            ]);
        }
    }
}
