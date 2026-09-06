<?php

namespace App\Support;

use Throwable;

/**
 * LA SIGNATURE DES LICENCES — ce qui empêche un client de se réabonner tout seul au bloc-notes.
 *
 * LE PROBLÈME, EN UNE PHRASE
 * ----------------------------
 * L'état d'abonnement voyage jusqu'au serveur du client et s'y installe dans un fichier JSON. Tant
 * qu'il n'est pas signé, la « protection » consiste à espérer que personne n'ouvre ce fichier et ne
 * remplace 2026 par 2099. C'est une porte fermée avec du ruban adhésif.
 *
 * CE QUE LA SIGNATURE FAIT — ET CE QU'ELLE NE FAIT PAS
 * ------------------------------------------------------
 * Elle rend le fichier INFALSIFIABLE : la console seule détient la clé privée, et un état modifié
 * ne passe plus la vérification. Elle ne rend pas le logiciel inviolable pour autant — qui possède
 * le serveur possède aussi le code, et peut retirer l'appel à la vérification. Il faut le dire sans
 * détour : ceci élève le coût de la fraude, il ne l'annule pas. Une protection présentée comme
 * absolue serait un mensonge, et un mensonge qu'on finit par croire soi-même.
 *
 * Le vrai levier reste l'hébergement : sur un serveur que vous tenez, le sujet ne se pose pas.
 *
 * POURQUOI UNE SIGNATURE ASYMÉTRIQUE, ET PAS UN SIMPLE HMAC
 * -----------------------------------------------------------
 * Un HMAC se vérifie avec le même secret qui le fabrique. Il faudrait donc poser ce secret sur les
 * serveurs des clients — c'est-à-dire donner à chacun de quoi fabriquer ses propres licences. Avec
 * une signature asymétrique, le produit ne reçoit que la clé PUBLIQUE : elle permet de vérifier,
 * jamais de signer. Elle peut être lue par tout le monde sans conséquence.
 *
 * POURQUOI RSA/OPENSSL ET PAS ED25519/SODIUM
 * --------------------------------------------
 * Ed25519 était le premier choix : clés courtes, signatures courtes, algorithme moderne. Il a été
 * abandonné pour une raison purement pratique, mais décisive : **l'extension sodium n'est pas
 * garantie**. Elle est absente des installations PHP sous Windows tant qu'on ne l'active pas à la
 * main, et rien ne promet qu'un hébergement mutualisé à Kinshasa l'aura.
 *
 * `ext-openssl`, elle, figure dans les dépendances OBLIGATOIRES de laravel/framework. Tout serveur
 * capable de faire tourner ce logiciel la possède, sans exception et sans configuration.
 *
 * Le raisonnement qui tranche : si la vérification échoue faute d'extension, c'est le système d'un
 * client À JOUR qui se ferme. Une protection qui punit les clients honnêtes en cas de panne est
 * pire que pas de protection du tout. On choisit donc l'algorithme le plus SÛR D'ÊTRE LÀ, pas le
 * plus élégant.
 */
class SignatureLicence
{
    /** RSA 2048 : largement suffisant pour signer un état d'abonnement, et rapide à vérifier. */
    private const TAILLE = 2048;

    /**
     * Fabrique une paire de clés. Appelée une seule fois, par la commande oikos:cles-signature.
     *
     * Les PEM sont encodés en base64 pour tenir sur UNE ligne : un PEM brut contient des retours
     * chariot, et un secret multiligne dans un .env est une source d'ennuis silencieux — dotenv
     * s'arrête à la première ligne et la clé est tronquée sans que rien ne le dise.
     *
     * @return array{privee: string, publique: string} en base64
     *
     * @throws \RuntimeException si openssl ne parvient pas à générer la paire
     */
    public static function fabriquerLesCles(): array
    {
        // PREMIER ESSAI : la configuration openssl du système. C'est le chemin normal, et celui
        // qui marche partout sauf sur Windows.
        try {
            return self::engendrer(null);
        } catch (\RuntimeException $e) {
            $premierEchec = $e->getMessage();
        }

        // SECOND ESSAI, AVEC NOTRE PROPRE openssl.cnf.
        //
        // PHP pour Windows est livré sans fichier de configuration openssl utilisable. La
        // génération échoue alors sur « configuration file routines::no such file » — un message
        // qui ne dit à personne ce qu'il faut faire, et qui a déjà coûté une soirée à quelqu'un.
        //
        // Plutôt que de renvoyer l'utilisateur chercher ce fichier sur son disque, on en écrit un.
        // Il tient en six lignes, ne sert qu'à la génération d'une paire RSA, et disparaît juste
        // après. La commande devient ainsi autonome : elle marche sous Windows sans rien installer,
        // sans variable d'environnement, et sans que personne ait à comprendre openssl.
        $fichier = self::ecrireUneConfigurationMinimale();

        if ($fichier === null) {
            throw new \RuntimeException($premierEchec);
        }

        try {
            return self::engendrer($fichier);
        } catch (\RuntimeException $e) {
            // On rend les DEUX messages : celui du système et celui de notre tentative. Sinon on
            // diagnostique le second échec en ignorant le premier, qui est souvent le vrai.
            throw new \RuntimeException($premierEchec.' — puis, avec notre propre configuration : '.$e->getMessage());
        } finally {
            @unlink($fichier);
        }
    }

    /**
     * La génération proprement dite. `$configuration` à null = celle du système.
     *
     * @return array{privee: string, publique: string}
     */
    private static function engendrer(?string $configuration): array
    {
        $options = [
            'private_key_bits' => self::TAILLE,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        if ($configuration !== null) {
            $options['config'] = $configuration;
        }

        // On vide la pile d'erreurs avant : openssl la garde d'un appel à l'autre, et un message
        // resté du premier essai se lirait comme une erreur du second.
        while (openssl_error_string()) {
        }

        $ressource = @openssl_pkey_new($options);

        if ($ressource === false) {
            throw new \RuntimeException(self::erreurOpenssl());
        }

        $privee = '';

        // L'export a besoin des MÊMES options : sans elles, il retomberait sur la configuration du
        // système — celle qui vient précisément d'échouer.
        if (! @openssl_pkey_export($ressource, $privee, null, $options)) {
            throw new \RuntimeException(self::erreurOpenssl());
        }

        $details = openssl_pkey_get_details($ressource);

        if (! is_array($details) || ! isset($details['key'])) {
            throw new \RuntimeException('Impossible de lire la clé publique produite par openssl.');
        }

        return [
            'privee' => base64_encode($privee),
            'publique' => base64_encode($details['key']),
        ];
    }

    /**
     * Le strict minimum qu'openssl demande pour accepter de fabriquer une clé.
     *
     * Aucune de ces valeurs ne se retrouve dans la paire produite : une clé RSA ne porte ni nom, ni
     * pays, ni date — ce sont les CERTIFICATS qui en portent, et nous n'en faisons pas. Ce fichier
     * n'existe que pour satisfaire une exigence de format d'openssl.
     */
    private static function ecrireUneConfigurationMinimale(): ?string
    {
        $contenu = "[ req ]\n"
            ."default_bits = ".self::TAILLE."\n"
            ."default_md = sha256\n"
            ."distinguished_name = req_distinguished_name\n"
            ."prompt = no\n"
            ."\n"
            ."[ req_distinguished_name ]\n"
            ."CN = oikos\n";

        // Le dossier temporaire du système d'abord ; storage/ ensuite, au cas où il serait
        // interdit d'écriture — ce qui arrive sur certains hébergements verrouillés.
        foreach ([sys_get_temp_dir(), storage_path('framework')] as $dossier) {
            if (! is_dir($dossier) || ! is_writable($dossier)) {
                continue;
            }

            $chemin = rtrim($dossier, '/\\').DIRECTORY_SEPARATOR.'openssl-oikos-'.bin2hex(random_bytes(6)).'.cnf';

            if (@file_put_contents($chemin, $contenu) !== false) {
                return $chemin;
            }
        }

        return null;
    }

    /** Rassemble ce qu'openssl a à dire — sinon l'échec est muet et introuvable. */
    private static function erreurOpenssl(): string
    {
        $messages = [];

        while ($message = openssl_error_string()) {
            $messages[] = $message;
        }

        return $messages === []
            ? "openssl n'a pas pu générer la paire de clés, sans en dire la raison."
            : implode(' | ', $messages);
    }

    public static function estConfiguree(): bool
    {
        return self::clePrivee() !== null;
    }

    /**
     * Signe un état de licence. Rend null si aucune clé n'est configurée.
     *
     * SANS CLÉ, ON NE SIGNE PAS — ET C'EST VOLONTAIRE. Une console fraîchement installée n'a pas
     * encore de paire ; refuser de répondre plutôt que de répondre sans signature fermerait toutes
     * les installations d'un coup, pour une case de configuration oubliée. Le produit, de son côté,
     * ne vérifie que s'il détient une clé publique : les deux moitiés s'allument séparément, ce qui
     * permet de basculer un parc sans coupure.
     */
    public static function signer(array $etat): ?string
    {
        $privee = self::clePrivee();

        if ($privee === null) {
            return null;
        }

        try {
            $signature = '';

            if (! openssl_sign(self::message($etat), $signature, $privee, OPENSSL_ALGO_SHA256)) {
                return null;
            }

            return base64_encode($signature);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * LE MESSAGE SIGNÉ — et l'endroit où une erreur ne se verrait pas.
     *
     * Deux règles, toutes deux indispensables :
     *
     *   · La clé `signature` est retirée avant de signer. Évident une fois dit, invisible sinon :
     *     un état qui contiendrait sa propre signature ne pourrait jamais être revérifié.
     *
     *   · Les clés sont TRIÉES. `json_encode` conserve l'ordre d'insertion ; ajouter demain un
     *     champ au milieu de EtatLicence changerait l'ordre, donc le message, donc la signature —
     *     et invaliderait des licences que personne n'a touchées. Le tri rend le message
     *     indépendant de la façon dont le tableau a été construit.
     *
     * Cette fonction et sa jumelle côté produit doivent rester identiques au caractère près. C'est
     * la seule dépendance dure entre les deux dépôts : la changer d'un côté seulement invalide
     * toutes les licences du parc.
     */
    public static function message(array $etat): string
    {
        unset($etat['signature']);

        self::trier($etat);

        return json_encode($etat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function trier(array &$tableau): void
    {
        foreach ($tableau as &$valeur) {
            if (is_array($valeur)) {
                self::trier($valeur);
            }
        }

        unset($valeur);

        // Seuls les tableaux associatifs sont triés par clé : réordonner une liste changerait son
        // sens, et une liste de modules n'a pas le même contenu selon son ordre pour qui la lit.
        if (! array_is_list($tableau)) {
            ksort($tableau);
        }
    }

    /** La clé privée, en PEM, décodée depuis le .env. Null si absente ou illisible. */
    private static function clePrivee(): ?string
    {
        $brute = trim((string) config('oikos.licence_cle_privee'));

        if ($brute === '') {
            return null;
        }

        $pem = base64_decode($brute, true);

        // On vérifie que ça RESSEMBLE à un PEM plutôt que d'attendre l'échec au moment de signer :
        // une clé tronquée au copier-coller doit se dire ici, pas dans une réponse d'API.
        return ($pem !== false && str_contains($pem, 'PRIVATE KEY')) ? $pem : null;
    }
}
