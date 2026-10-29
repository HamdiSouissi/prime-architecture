# Archi TN

Socle de gestion pour cabinets d'architecture en Tunisie : Laravel 12 / PHP 8.2+, MySQL 8, Blade, Tailwind, Alpine, FR/AR et RTL. Cette version comprend l’authentification, les rôles, les clients, les projets et les documents versionnés. La planification et la facturation restent hors périmètre.

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

Sauvegarder la base avant la migration ou l'actualisation du référentiel. Les 24 gouvernorats proviennent de `database/data/governorates.php` ; `database/data/delegations.php` contient **279 délégations FR/AR**, adaptées du jeu communautaire `tunisia-kit`, qui indique avoir utilisé le recensement INS 2024. La provenance, la licence MIT et les limites sont documentées dans `database/data/DELEGATIONS_SOURCES.md` et `database/data/TUNISIA_KIT_LICENSE.txt`. Ce n'est pas un registre officiel en temps réel : confirmer les libellés et évolutions auprès des autorités ; certaines sources diffèrent notamment pour Hamma Ouest.

Chaque ligne est `[clé_interne_unique, clé_gouvernorat, libellé_fr, libellé_ar]`. Les clés ne sont pas des codes officiels : ne pas les modifier après utilisation. Relancer `GeographySeeder` après ajout ou correction des libellés : il met à jour les noms des clés connues, conserve les identifiants en base et ne supprime aucune délégation existante. Il refuse de déplacer une clé déjà associée à un autre gouvernorat ; en cas de conflit, vérifier et résoudre manuellement avant de relancer. Des délégations ajoutées auparavant sous d'autres clés restent présentes et peuvent nécessiter une déduplication manuelle après vérification, sans casser les liens existants. La cohérence délégation/gouvernorat est vérifiée côté serveur.

Dans les formulaires Clients et Projets, choisissez d'abord un gouvernorat : la liste des délégations est filtrée automatiquement et le changement de gouvernorat efface la sélection précédente. En modification ou après une erreur de validation, la sélection est conservée. Si JavaScript est désactivé, toutes les délégations restent accessibles et le serveur refuse une combinaison incohérente. Si les listes sont vides, exécuter `GeographySeeder` comme indiqué ci-dessus, puis vérifier la base utilisée par le conteneur.

La CI teste ces formulaires dans Chrome avec `playwright-core` (dépendance **de développement uniquement**, sans navigateur téléchargé avec le paquet). Le test `npm run test:location-ui` nécessite une instance Compose locale préalablement migrée et initialisée avec un administrateur et le référentiel géographique ; il crée des données de test et ne doit pas être lancé sur une base réelle.

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

## Phase 4 : bibliothèque documentaire privée

Les documents appartiennent à un projet ; chaque fichier est une version immuable, numérotée sous verrou MySQL et identifiée par SHA-256. La version courante est une référence, et recopier une ancienne version crée une nouvelle version. L'archivage ne supprime pas les fichiers ; l'administrateur seul restaure les éléments archivés. Un projet archivé conserve ses fichiers mais les documents ne sont accessibles qu'une fois le projet restauré. Une version courante archivée est remplacée par la dernière version active disponible, ou aucune si toutes sont archivées. Restaurer une version archivée ne la rend pas automatiquement courante.

| Rôle | Consultation / téléchargement | Dépôt / modification | Archivage | Restauration / catégories |
|---|---|---|---|---|
| admin | Tous les projets actifs | Oui | Oui | Oui |
| architect | Tous les projets actifs | Oui | Oui | Non |
| collaborator | Projets où il est membre | Membres non `viewer` | Seulement si permission accordée | Non |
| client | Documents visibles de ses projets uniquement | Non ; commentaires publics uniquement | Non | Non |
| contractor | Aucun | Non | Non | Non |

Permissions : `documents.view`, `documents.upload`, `documents.update`, `documents.delete`, `documents.restore`, `documents.download`, `documents.manage_categories`. Les catégories initiales sont créées sans écraser les modifications de l'administrateur. Après déploiement, sauvegarder MySQL et le stockage, appliquer les migrations et synchroniser les permissions et catégories :

```sh
docker compose up -d --build
docker compose exec -u www-data app php artisan migrate --force
docker compose exec -u www-data app php artisan db:seed --class=RolePermissionSeeder --force
docker compose exec -u www-data app php artisan db:seed --class=DocumentCategorySeeder --force
```

Le disque `documents` se trouve dans `storage/app/private/documents`, hors de `public`, dans le volume `app_storage`. Sauvegarder **ensemble et de manière cohérente MySQL, `app_storage` et `app_runtime`**, vérifier régulièrement la restauration et conserver la clé d'application. Ne jamais utiliser `docker compose down -v` sur des données à conserver. Aucun fichier n'est servi via une URL publique : les téléchargements et aperçus passent par les policies et des réponses privées. PDF et images sont affichables ; les autres formats sont uniquement téléchargés.

`config/documents.php` configure les extensions, les MIME détectés côté serveur, la taille, les formats affichables et les limites ZIP. Par défaut `DOCUMENT_MAX_FILE_KB=25600` (25 Mio), `DOCUMENT_MAX_ZIP_BYTES=262144000` (250 Mio) et `DOCUMENT_MAX_ZIP_FILES=50`. PHP-FPM accepte 25M par fichier et 128M par requête ; Nginx limite la requête à 128M. Modifier ensemble configuration applicative, `Dockerfile` et `docker/nginx/default.conf`, puis reconstruire, si des tailles supérieures sont nécessaires. Prévoir aussi l'espace disque temporaire pour les ZIP (jusqu'à la taille des fichiers plus celle de l'archive) et les sauvegardes. L'envoi multiple transmet les fichiers séparément ; chaque fichier réussit ou échoue indépendamment.

Les contrôles de MIME et d'extension ne garantissent pas l'absence de code malveillant. **Aucun antivirus n'est activé** : l'interface `DocumentScanner` est un point d'extension, pas un scan. Les signatures DWG/DXF et des anciens formats Office peuvent varier ; tester avec vos fichiers avant usage. Un fichier doit avoir une seule extension (les noms contenant plusieurs points sont refusés). Les contenus ne sont jamais convertis.

Une transaction MySQL et un stockage externe ne peuvent pas être atomiques en cas de panne brutale. Un journal durable permet de retrouver les dépôts interrompus. Après arrêt des dépôts et contrôle de la base et des sauvegardes, l'administrateur peut lister puis réconcilier les intentions datant d'au moins 24 heures :

```sh
docker compose exec -u www-data app php artisan documents:reconcile
docker compose exec -u www-data app php artisan documents:reconcile --delete
```

La seconde commande supprime les fichiers non référencés des intentions périmées : **arrêter les dépôts actifs et inspecter la liste avant son exécution**. Ne pas la lancer pendant un dépôt de longue durée. Les erreurs de stockage doivent être surveillées et les sauvegardes testées ; le journal ne remplace pas ces mesures.

Pour une démo locale uniquement, sur une base de test avec des projets et un architecte existants : `docker compose exec -u www-data app php artisan db:seed --class=DocumentDemoSeeder`. Ce seeder ne doit pas être utilisé en production.

## Phase 5 — Planning et tâches

Le planning est un module de projet : tâches, sous-tâches (une profondeur), responsables multiples, statuts, priorités, commentaires internes, historique, jalons, dépendances fin→début, calendriers, Kanban, Gantt, modèles, charge et rappels. L'archivage est logique ; la restauration d'une tâche permet notamment de la réaffecter avant de retirer un membre. Les dépendances et sous-tâches doivent être retirées avant l'archivage d'une tâche. Les tâches archivées les plus récentes sont accessibles depuis le planning aux administrateurs et architectes.

### Schéma et règles

- `tasks`, `task_assignees`, `task_followers`, `task_dependencies`, `task_comments`, `milestones` : les clés composites imposent un même projet et des responsables/abonnés membres du projet. Les clients ne voient que les tâches marquées visibles et les jalons, sans heures, responsables, historique, commentaires ni notes internes ; les listes et le JSON sont filtrés côté SQL. Les entrepreneurs sont exclus.
- `planning_templates`, `planning_template_items`, `planning_template_dependencies` : titres FR/AR, durée et décalage en jours ouvrés, dépendances intra-modèle. Le modèle « Villa — démonstration » est indicatif et doit être adapté ; les jalons ne participent pas aux dépendances. L'application exige un aperçu puis une confirmation ; tout changement de modèle ou de calendrier invalide l'aperçu.
- `working_calendar_days`, `holidays`, `planning_calendar_state` : calendrier commun stocké en base ; lundi à vendredi par défaut. Dates inclusives et fuseau `PLANNING_TIMEZONE` (par défaut `Africa/Tunis`). Un déplacement recalcule l'aperçu en transaction sous verrous ; si une tâche déplacée n'est pas modifiable par l'acteur, la cascade est refusée. Les dates des tâches liées doivent respecter le prochain jour ouvré.
- `notifications`, `planning_daily_digests` : notifications propres à l'utilisateur, choix d'email et clé unique utilisateur/jour. Les événements sont notifiés après transaction ; le suivi est automatique à l'affectation et peut être activé/désactivé par un membre. Les rappels couvrent les tâches assignées en retard, aujourd'hui ou demain et les jalons non atteints dans la fenêtre configurée. Une entrée quotidienne est créée au maximum par utilisateur, même en cas de relance concurrente. Le récapitulatif inclut au plus 500 tâches et 500 jalons ; l’email en montre 20 de chaque au maximum et le centre de notifications recontrôle les droits avant d’afficher les titres.

Matrice : `tasks.view` autorise la lecture des projets accessibles, avec filtre client ; `tasks.create` la création pour les membres collaborateur (pas les `viewer`) et les architectes/admins ; `tasks.update` la modification des tâches assignées au collaborateur ou de toutes celles de l'architecte/admin ; `tasks.assign` l'affectation de membres actifs ; `tasks.delete` l'archivage/restauration par architecte/admin ; `milestones.manage` la gestion des jalons par architecte/admin ; `planning.manage_templates` les modèles par architecte/admin ; `planning.manage_calendar` le calendrier par admin ; `planning.view_workload` la charge par architecte/admin. Toutes les mutations utilisent une validation serveur, CSRF et contrôles de droits ; les IDs imbriqués sont vérifiés.

La charge attribue **l'intégralité** des heures estimées à chacun des responsables et la répartit entre les jours ouvrés de la tâche. Le seuil est `PLANNING_WEEKLY_CAPACITY_HOURS` (40 h par défaut). Les tâches terminées/annulées, sans estimation ou dates, et sans jour ouvré exploitable sont exclues. La charge est bornée à 2 000 tâches et 12 semaines, avec signalement en cas de troncature. Les listes/Kanban/calendriers, les jalons et Mes tâches sont paginés ; les regroupements portent sur la page courante.

DHTMLX Gantt Community 10.0.3 est distribué sous licence **MIT** (`node_modules/dhtmlx-gantt/LICENSE.md`). Il est chargé uniquement sur la vue Gantt, en lecture seule ; les modifications passent par les formulaires et l'aperçu de cascade. Le fond coloré indique la cellule contenant la date courante, plutôt que le plugin de marqueur réservé à l'édition commerciale. Le JSON est limité par `PLANNING_GANTT_MAX_ITEMS` (500, plafonné à 1 000), avec signalement des éléments omis. La liste et le Kanban à boutons offrent une alternative clavier au Gantt. La compilation retire les fontes Google distantes incluses dans sa feuille de style ; les polices système servent de repli. Le chargement JS génère un avertissement Vite de taille pour ce paquet.

### Installation et jours fériés

Après récupération du code et reconstruction des images, appliquer les migrations puis initialiser les permissions et données du planning :

```bash
docker compose up -d --build
docker compose exec -u www-data app php artisan migrate --force
docker compose exec -u www-data app php artisan db:seed --class=RolePermissionSeeder --force
docker compose exec -u www-data app php artisan db:seed --class=WorkingCalendarSeeder --force
docker compose exec -u www-data app php artisan db:seed --class=PlanningTemplateSeeder --force
```

Les seeders ajoutent les données absentes sans écraser les modifications existantes. Supprimer un férié issu du fichier puis relancer le seeder le recrée. `database/data/planning_holidays.php` ne préremplit que le 20 mars et le 25 juillet comme dates civiles annuelles ; leur applicabilité doit être vérifiée selon l'organisation. `database/data/planning_holidays_extra.php` est prévu pour les compléments. **Aucune date religieuse n'est codée en dur** : vérifier chaque année les annonces officielles tunisiennes et saisir les dates applicables dans l'administration du calendrier. Le samedi et les autres jours ouvrés sont configurables ; conserver au moins un jour ouvré.

### Ordonnanceur et emails

En développement, après migrations et seeders, démarrer le conteneur optionnel, qui partage les volumes et l'environnement applicatifs :

```bash
docker compose --profile scheduler up -d scheduler
docker compose exec -u www-data app php artisan planning:remind
```

En production, exécuter `schedule:run` chaque minute sous l'utilisateur applicatif et utiliser une seule configuration de base/horloge pour tous les serveurs :

```cron
* * * * * cd /chemin/vers/prime-architecture && php artisan schedule:run >> /dev/null 2>&1
```

Le rappel est programmé à `PLANNING_REMINDER_TIME` (08:00 par défaut), dans `PLANNING_TIMEZONE`, avec une fenêtre de jalon `PLANNING_MILESTONE_REMINDER_DAYS` (7 par défaut). En environnement multisite, utiliser un cache partagé pour le verrou du scheduler. Une entrée `processing`, `sent` ou `failed` interdit une seconde tentative automatique le même jour ; consulter les journaux Laravel et `planning_daily_digests` après un échec. Les emails utilisent le transport configuré (Mailpit en développement). Une panne SMTP ambiguë peut avoir livré un message avant l'erreur : l'exactement-une-fois n'est pas garanti et une relance manuelle doit être examinée avant intervention. Les emails restent optionnels par utilisateur ; la base reste la source du centre de notifications.
