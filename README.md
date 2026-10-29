# Archi TN

Socle de gestion pour cabinets d'architecture en Tunisie : Laravel 12 / PHP 8.2+, MySQL 8, Blade, Tailwind, Alpine, FR/AR et RTL. Cette version comprend l’authentification, les rôles, les clients et les projets. Les documents, la planification et la facturation restent hors périmètre.

> **Support et déploiement :** Laravel 12 reçoit des correctifs de sécurité jusqu’au 24 février 2027 ; ses correctifs fonctionnels se sont arrêtés le 13 août 2026. Prévoir la mise à niveau suivante avant la fin de support. Exécuter `composer audit` et traiter ses alertes ; Docker Compose est réservé au développement local.

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

### Téléchargements Composer lents pendant le build Docker

Si `composer install` échoue avec `curl error 28` sur `codeload.github.com`, il s’agit d’un délai réseau dépassé : ce n’est pas une erreur de migration. Le build utilise BuildKit pour conserver les archives Composer déjà téléchargées et accorde par défaut 1 200 secondes par transfert, avec quatre téléchargements simultanés. Relancer simplement :

```sh
docker compose build app web
docker compose up -d
```

Sur une connexion particulièrement lente, augmenter la limite pour cette construction :

```sh
docker compose build --build-arg COMPOSER_HTTP_TIMEOUT=2400 --build-arg COMPOSER_MAX_PARALLEL_HTTP=2 app web
docker compose up -d
```

Le délai concerne les transferts HTTP de Composer (`default_socket_timeout`), distinct de `COMPOSER_PROCESS_TIMEOUT`, qui concerne les processus externes. Le cache BuildKit accélère les nouvelles tentatives, sans les rendre possibles hors ligne si une archive manque. Vérifier aussi l’accès à `github.com` et `codeload.github.com`, ainsi que le proxy ou VPN éventuel. Les outils de développement restent installés. Ne pas supprimer les volumes ni lancer `docker compose down -v` pour résoudre ce problème.

### Mise à niveau depuis Laravel 11

Avant toute mise à niveau, sauvegarder et tester la restauration de MySQL, du volume `app_runtime` (notamment la clé d’application) et des fichiers persistants. Mettre le service en maintenance pendant la mise à niveau. Après récupération de la version Laravel 12, reconstruire l’image puis appliquer les migrations et vider les caches :

```sh
docker compose up -d --build
docker compose exec -u www-data app php artisan migrate --force
docker compose exec -u www-data app php artisan optimize:clear
```

Ne pas utiliser `docker compose down -v` lors d’une mise à niveau : cela détruit les volumes et la clé. Les rôles et comptes existants ne nécessitent pas de réexécution des seeders. Le disque local conserve explicitement `storage/app/private` et la durée des jetons de réinitialisation reste configurée en minutes dans `config/auth.php`.

### Premier administrateur

Dans `.env` **local et non versionné**, définir `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` (au moins 10 caractères). La vérification des mots de passe compromis est activée par défaut et nécessite un accès HTTPS à `api.pwnedpasswords.com`. Puis lancer :

```sh
docker compose up -d
docker compose exec -u www-data app php artisan db:seed --class=RolePermissionSeeder --force
docker compose exec -u www-data app php artisan db:seed --class=InitialAdminSeeder --force
```

Le seeder d'initialisation ne modifie pas un administrateur actif existant, refuse un email déjà utilisé et vérifie la politique de mot de passe. Les valeurs sont lues via les variables d'environnement du conteneur ; recréer le service avec `docker compose up -d` après modification du `.env`. Ne pas publier de secrets dans Git, des captures ou des journaux. Supprimer `ADMIN_PASSWORD` de l'environnement après initialisation et recréer le service ; conserver la clé d'application et les sauvegardes.

## Rôles et autorisations

La configuration `config/access.php` sert de source à `RolePermissionSeeder`, qui synchronise les permissions des rôles à chaque exécution : modifier la configuration avant de relancer le seeder. Après une mise à niveau, relancer `RolePermissionSeeder` afin de synchroniser les permissions de la phase 3 ; cette commande remplace les permissions de chaque rôle par celles de `config/access.php`.

| Rôle | Clients | Projets | Notes et équipe |
|---|---|---|---|
| `admin` | Tous : créer, modifier, archiver, restaurer | Tous : créer, modifier, changer de statut, archiver, restaurer | Voir/ajouter les notes, supprimer toute note ; gérer l’équipe |
| `architect` | Tous : créer, modifier, archiver, restaurer | Tous : créer, modifier, changer de statut, archiver, restaurer | Voir/ajouter les notes, supprimer ses notes ; gérer l’équipe |
| `collaborator` | Pas d’accès au module Clients | Uniquement projets affectés ; modification et changement de statut sauf affectation `viewer` | Voir les notes internes des projets affectés ; ajouter/supprimer ses notes si autorisé, pas de gestion d’équipe |
| `client` | Pas d’accès au module Clients | Lecture seule des projets liés à ses fiches client | Pas de notes internes ni d’historique détaillé |
| `contractor` | Aucun accès | Aucun accès | Aucun accès |

Permissions granulaires : `clients.view`, `clients.create`, `clients.update`, `clients.archive`, `clients.restore`, `projects.view`, `projects.create`, `projects.update`, `projects.archive`, `projects.restore`, `projects.change_status`, `projects.manage_team`, `projects.notes`. Les policies et les requêtes filtrées complètent ces permissions. La navigation et le tableau de bord respectent la même portée d’accès.

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

La CI vérifie les dépendances Composer et leur audit, puis exécute les migrations MySQL, PHPUnit, Pint, Larastan niveau 5, la compilation Vite et des vérifications Docker/Mailpit à chaque push et pull request. Un test part d’une installation Laravel 11 avec données existantes, puis vérifie après mise à niveau la clé, la session, les identifiants et le profil. Le contrôle des mots de passe compromis est désactivé dans les tests automatisés, avec un test du vérificateur utilisant des réponses HTTP simulées. `composer.lock` et `package-lock.json` sont versionnés. Les traductions Laravel FR/AR utilisent les catalogues [Laravel-Lang](https://github.com/Laravel-Lang/lang).

Les données MySQL sont dans `mysql_data`, la clé générée au premier démarrage dans `app_runtime`, les journaux applicatifs dans `app_storage`. `docker compose down` les conserve ; `docker compose down -v` les détruit, y compris la clé. Laravel journalise dans `storage/logs` avec rotation quotidienne. Attention : modifier les identifiants MySQL dans `.env` ne reconfigure pas une base déjà initialisée.

## Structure et conventions

- `app/Http/Controllers`, `app/Http/Middleware`, `app/Policies`, `app/Services`, `app/Models` : contrôle d'accès, flux HTTP et logique métier.
- `config/access.php`, `config/security.php` : rôles et paramètres de sécurité.
- `database/migrations`, `database/seeders` : schéma versionné et initialisation.
- `lang/fr`, `lang/ar`, `resources/views` : traductions, vues et composants RTL.
- `tests/Feature`, `tests/Unit` : tests automatisés ; `docker/` : configuration locale.

Une fonctionnalité par branche, PR revue, commits Conventional Commits (`feat:`, `fix:`, `test:`, `docs:`). Ne jamais committer `.env`, les secrets, `vendor` ou `node_modules`.


## Phase 3 : clients, projets et données géographiques

Appliquer les migrations et synchroniser les rôles sur une installation existante :

```sh
docker compose exec -u www-data app php artisan migrate --force
docker compose exec -u www-data app php artisan db:seed --class=RolePermissionSeeder --force
docker compose exec -u www-data app php artisan db:seed --class=GeographySeeder --force
```

Sauvegarder la base avant la migration. Les 24 gouvernorats proviennent de `database/data/governorates.php`. **La liste des délégations est volontairement vide et doit être complétée et vérifiée** dans `database/data/delegations.php` : chaque ligne est `[clé_interne_unique, clé_gouvernorat, libellé_fr, libellé_ar]`. Les clés sont internes, pas des codes administratifs officiels. Ne pas changer une clé existante ; relancer le seeder après ajout ou correction des libellés. Les formulaires acceptent un gouvernorat sans délégation tant que ces données manquent. Les relations et la cohérence délégation/gouvernorat sont vérifiées côté serveur.

Le seeder de démonstration est **facultatif**, interdit en production et exige un compte administrateur ou architecte actif. Il crée des noms fictifs et des adresses `example.test` :

```sh
docker compose exec -u www-data app php artisan db:seed --class=PhaseThreeDemoSeeder
```

### Schéma simplifié

```text
users (client, optionnel) <- clients -> governorates <- delegations
                               |
                               +------ client_contacts (archivables)
                               +------ projects -> users (responsable)
                                           |  +-- project_user -> users (équipe)
                                           |  +-- project_status_history -> users (auteur)
                                           +----- project_notes -> users (auteur, archivables)
project_reference_counters (une ligne par année)
```

Les clients, contacts, projets et notes sont supprimés logiquement. Archiver un client archive aussi ses projets actifs ; restaurer le client ne restaure pas ses projets. Pour restaurer un projet, il faut d’abord restaurer son client. L’historique et les événements du journal ne sont pas supprimables depuis l’interface. Le contact principal actif est unique par client. L’architecte responsable doit être un administrateur ou architecte actif ; les membres d’équipe sont des utilisateurs internes actifs. L’attribution `viewer` est en lecture seule.

### Conventions et limites de validation

Les références ont par défaut la forme `2026-001` : l’année est calculée en fuseau `Africa/Tunis`, le compteur est verrouillé en transaction et la référence reste unique même après archivage. `PROJECT_REFERENCE_FORMAT` doit contenir une fois chacun `{year}` et `{sequence}` ; `PROJECT_REFERENCE_PADDING` définit le nombre **minimum** de chiffres (1 à 12), sans limite à 999. `PROJECT_REFERENCE_TIMEZONE` permet de modifier le fuseau. Les modifications de format ne doivent pas produire de référence existante.

La CIN est une chaîne de huit chiffres. Les téléphones tunisiens sont contrôlés sur leur structure à huit chiffres puis stockés sous `+216XXXXXXXX`, avec ou sans indicatif en entrée ; l’attribution effective du numéro n’est pas vérifiée. Le matricule fiscal est contrôlé **syntaxiquement seulement** : par défaut sept chiffres, trois lettres et trois chiffres, par exemple `1234567/A/A/M/000`. La règle est configurable dans `config/projects.php` (`CLIENT_TAX_ID_PATTERN`). **Ce format et les catégories acceptées doivent être confirmés auprès de l’administration compétente** ; aucun contrôle de validité officiel ou d’existence n’est effectué. Les champs financiers sont indicatifs, en TND avec trois décimales ; aucune logique de facturation n’est incluse.

Le tableau Kanban est en lecture seule et porte sur la page de résultats courante. Le statut initial (`PROJECT_INITIAL_STATUS`, `sketch` par défaut) et les états actifs du tableau de bord sont configurés dans `config/projects.php`. Pour protéger les données internes, les clients ne reçoivent ni notes ni historique détaillé, et les listes sont filtrées en base avant pagination.
