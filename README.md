# Archi TN

Socle de gestion pour cabinets d'architecture en Tunisie : Laravel 11 / PHP 8.2, MySQL 8, Blade, Tailwind, Alpine, FR/AR et RTL. Cette phase couvre l'authentification, les rôles, les utilisateurs, le profil et les premiers événements du journal ; elle ne comprend aucun module métier.

> **Avertissement sécurité : Laravel 11 est hors support depuis mars 2026.** Ce projet ne doit pas être déployé en production en l'état. `composer.json` autorise explicitement la résolution de paquets signalés vulnérables (`audit.block-insecure=false`) pour pouvoir conserver cette version. Planifier une mise à niveau vers une version maintenue, exécuter `composer audit` et traiter les alertes avant tout déploiement. Docker Compose est réservé au développement local.

## Prérequis et installation

Git, Docker Engine et Docker Compose v2, accès Internet pour construire les images. Ports 8080, 8025 et éventuellement 8081 libres.

```sh
git clone https://github.com/HamdiSouissi/prime-architecture.git
cd prime-architecture
cp .env.example .env
docker compose up -d --build
docker compose exec -u www-data app php artisan migrate --force
```

Les sessions utilisent MySQL : la migration est indispensable avant la première requête. Exécuter les commandes Artisan et Composer avec `-u www-data` pour éviter de créer des fichiers de cache appartenant à root, que PHP-FPM ne pourrait pas modifier. Accéder à <http://localhost:8080>. Le code est intégré aux images : après une modification, reconstruire avec `docker compose up -d --build`.

### Premier administrateur

Dans `.env` **local et non versionné**, définir `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` (au moins 10 caractères). La vérification des mots de passe compromis est activée par défaut et nécessite un accès HTTPS à `api.pwnedpasswords.com`. Puis lancer :

```sh
docker compose up -d
docker compose exec -u www-data app php artisan db:seed --class=RolePermissionSeeder --force
docker compose exec -u www-data app php artisan db:seed --class=InitialAdminSeeder --force
```

Le seeder d'initialisation ne modifie pas un administrateur actif existant, refuse un email déjà utilisé et vérifie la politique de mot de passe. Les valeurs sont lues via les variables d'environnement du conteneur ; recréer le service avec `docker compose up -d` après modification du `.env`. Ne pas publier de secrets dans Git, des captures ou des journaux. Supprimer `ADMIN_PASSWORD` de l'environnement après initialisation et recréer le service ; conserver la clé d'application et les sauvegardes.

## Rôles et autorisations

La configuration `config/access.php` sert de source à `RolePermissionSeeder`, qui synchronise les permissions des rôles à chaque exécution : modifier la configuration avant de relancer le seeder. Aucune permission métier n'est présumée avant les phases correspondantes.

| Rôle | Permissions | Accès actuel |
|---|---|---|
| `admin` | `dashboard.internal.view`, `users.manage`, `activity.view` | Tableau interne, utilisateurs, journal |
| `architect` | `dashboard.internal.view` | Tableau interne et profil |
| `collaborator` | `dashboard.internal.view` | Tableau interne et profil |
| `client` | `dashboard.client.view` | Accueil externe et profil |
| `contractor` | `dashboard.client.view` | Accueil externe et profil |

Les routes d'administration passent par la policy utilisateur ou par les permissions ; `role`, `permission` et `role_or_permission` sont aussi disponibles en middleware. Les pages protégées requièrent une connexion et un email vérifié. L'interface d'administration permet de rechercher, filtrer, inviter, modifier, désactiver et supprimer logiquement. Un changement d’email révoque les sessions, invalide les anciens jetons et demande une nouvelle vérification (ou une nouvelle invitation pour un compte en attente). Un administrateur ne peut pas se désactiver, se supprimer ou se retirer son rôle ; les modifications d'administrateur sont sérialisées et le dernier administrateur actif est protégé.

## Invitation et emails

Aucune inscription publique. Dans **Utilisateurs → Inviter**, l'administrateur indique nom, email et rôle. L'invité reçoit un lien signé à usage unique ; le jeton n'est stocké qu'après hachage. Le lien expire après `INVITATION_EXPIRY_MINUTES` minutes (1 440 par défaut). L'utilisateur définit son mot de passe ; l'adresse est alors vérifiée. Un lien expiré, altéré ou déjà utilisé ne peut pas être réutilisé. Depuis la fiche d’un compte actif non vérifié, l’administrateur peut renvoyer une invitation ; tous les liens précédents sont invalidés. En cas d’échec SMTP, le compte reste créé et le renvoi permet de réessayer.

Mailpit intercepte les emails de développement : <http://localhost:8025>, accessible uniquement depuis l'hôte. Les messages ne sont pas réellement délivrés. Ils peuvent contenir des liens sensibles ; ne pas exposer Mailpit sur Internet. phpMyAdmin est optionnel : `docker compose --profile dev up -d`, puis <http://localhost:8081>. MySQL n'est pas publié directement sur l'hôte.

## Sécurité et profil

Le mot de passe doit comporter au moins `PASSWORD_MIN_LENGTH` caractères, avec un minimum absolu de 10. `PASSWORD_CHECK_COMPROMISED=true` utilise l'API Pwned Passwords par préfixe SHA-1 (k-anonymat), sans transmettre le mot de passe. La vérification échoue de manière fermée si l'API est inaccessible ou renvoie une réponse invalide : la création ou le changement de mot de passe sera alors refusé. Pour les tests ou un environnement isolé uniquement, `PASSWORD_CHECK_COMPROMISED=false` désactive ce contrôle ; ne pas le désactiver en production.

La connexion est limitée par email et IP, régénère la session et charge la langue préférée ; la déconnexion invalide la session. Le profil permet de modifier nom, téléphone et langue, de changer son mot de passe avec l'ancien, de consulter ses sessions actives et de déconnecter les autres appareils. Une désactivation, une suppression ou une réinitialisation de mot de passe révoque les sessions stockées en base et renouvelle le jeton de connexion persistante. Un middleware refuse également les comptes inactifs à chaque requête.

Les cookies de session sont `HttpOnly` et `SameSite=Lax` par défaut ; `Secure` est activé par défaut pour `APP_ENV=production`. HTTPS et une configuration de déploiement adaptée sont indispensables. Hors développement, renseigner `APP_URL` avec le nom d’hôte public exact : les autres hôtes sont refusés. L'application ajoute `X-Frame-Options`, `X-Content-Type-Options` et `Referrer-Policy`. Les demandes de réinitialisation affichent une réponse identique pour les adresses connues ou inconnues.

La double authentification TOTP est **reportée** : elle nécessite notamment récupération sécurisée, codes de secours et tests dédiés. Il n'y a pas encore de prise en charge multi-cabinet ; ne pas utiliser cette version pour isoler plusieurs organisations.

Le journal conserve connexions réussies et échouées, déconnexions, création/modification/désactivation/suppression de compte, changement de rôle et de mot de passe. Les échecs de connexion sont anonymisés ; les mots de passe ne sont jamais enregistrés. La page Journal est limitée à la permission `activity.view`, avec filtres et pagination. Définir une politique de conservation et une protection des données personnelles avant déploiement.

## Tests et qualité

```sh
docker compose exec -u www-data app php artisan test
docker compose exec -u www-data app vendor/bin/pint --test
docker compose exec -u www-data app vendor/bin/phpstan analyse --no-progress
docker compose exec -u www-data app composer audit
```

La CI exécute les migrations MySQL, PHPUnit, Pint, Larastan niveau 5, la compilation Vite et des vérifications Docker/Mailpit à chaque push et pull request. Le contrôle des mots de passe compromis est désactivé dans les tests automatisés, avec un test du vérificateur utilisant des réponses HTTP simulées. `composer.lock` et `package-lock.json` sont versionnés. Les traductions Laravel FR/AR utilisent les catalogues [Laravel-Lang](https://github.com/Laravel-Lang/lang).

Les données MySQL sont dans `mysql_data`, la clé générée au premier démarrage dans `app_runtime`, les journaux applicatifs dans `app_storage`. `docker compose down` les conserve ; `docker compose down -v` les détruit, y compris la clé. Laravel journalise dans `storage/logs` avec rotation quotidienne. Attention : modifier les identifiants MySQL dans `.env` ne reconfigure pas une base déjà initialisée.

## Structure et conventions

- `app/Http/Controllers`, `app/Http/Middleware`, `app/Policies`, `app/Services`, `app/Models` : contrôle d'accès, flux HTTP et logique métier.
- `config/access.php`, `config/security.php` : rôles et paramètres de sécurité.
- `database/migrations`, `database/seeders` : schéma versionné et initialisation.
- `lang/fr`, `lang/ar`, `resources/views` : traductions, vues et composants RTL.
- `tests/Feature`, `tests/Unit` : tests automatisés ; `docker/` : configuration locale.

Une fonctionnalité par branche, PR revue, commits Conventional Commits (`feat:`, `fix:`, `test:`, `docs:`). Ne jamais committer `.env`, les secrets, `vendor` ou `node_modules`.
