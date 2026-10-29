# Archi TN

Socle de gestion pour cabinets d'architecture en Tunisie : Laravel 12 / PHP 8.2+, MySQL 8, Blade, Tailwind, Alpine, FR/AR et RTL. Cette version comprend l’authentification, les rôles, les clients, les projets, les documents versionnés et la planification. La facturation et la refonte graphique sont en cours de validation : ne pas utiliser les documents financiers en production avant les vérifications métier, fiscales et PDF décrites ci-dessous.

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

La CIN est une chaîne de huit chiffres. Les téléphones tunisiens sont contrôlés sur leur structure à huit chiffres puis stockés sous `+216XXXXXXXX`, avec ou sans indicatif en entrée ; l’attribution effective du numéro n’est pas vérifiée. Le matricule fiscal est contrôlé **syntaxiquement seulement** : par défaut sept chiffres, trois lettres et trois chiffres, par exemple `1234567/A/A/M/000`. La règle est configurable dans `config/projects.php` (`CLIENT_TAX_ID_PATTERN`). **Ce format et les catégories acceptées doivent être confirmés auprès de l’administration compétente** ; aucun contrôle de validité officiel ou d’existence n’est effectué. Les champs financiers du projet sont indicatifs, en TND avec trois décimales ; ils ne constituent pas une facture.

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

DHTMLX Gantt Community 10.0.3 est distribué sous licence **MIT** (`node_modules/dhtmlx-gantt/LICENSE.md`). Il est chargé uniquement sur la vue Gantt, en lecture seule ; les modifications passent par les formulaires et l'aperçu de cascade. Le fond coloré indique la cellule contenant la date courante lorsqu’elle se trouve dans la plage affichée, plutôt que le plugin de marqueur réservé à l'édition commerciale. Les tâches sans dates ne figurent pas dans ce diagramme ni dans le calendrier ; utiliser la liste. Le JSON est limité par `PLANNING_GANTT_MAX_ITEMS` (500, plafonné à 1 000), avec signalement des éléments omis. La liste et le Kanban à boutons offrent une alternative clavier au Gantt. La compilation retire les fontes Google distantes incluses dans sa feuille de style ; les polices système servent de repli. Le chargement JS génère un avertissement Vite de taille pour ce paquet.

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


## Phase 6 — facturation et interface, validation en cours

Le dossier de preuves et de réserves (accessibilité, fiscalité, PDF, recette manuelle) est tenu dans [`docs/phase6-validation.md`](docs/phase6-validation.md).

**Ne pas utiliser les documents financiers en production sans validation préalable par un comptable et sans vérifier les PDF FR/AR sur le déploiement cible.** Les règles applicables dépendent des textes et de la situation ; le logiciel ne garantit ni conformité fiscale ni valeur légale. Un administrateur doit vérifier et enregistrer une version fiscale, un profil de cabinet et les trois formats de numérotation avant toute émission. Cocher la confirmation dans l’interface indique une décision de l’administrateur ; cela ne constitue pas une vérification juridique automatisée.

Depuis un projet, les utilisateurs autorisés peuvent créer des devis ou factures, modifier leurs brouillons et consulter les honoraires par phase. La page **Finances** permet de rechercher, filtrer et consulter les documents. Les montants et taux sont des chaînes décimales TND : le calcul s’effectue sur le serveur avec BCMath, avec arrondi demi-supérieur au millime des lignes et de la TVA par groupe. L’éditeur Alpine demande un recalcul au serveur ; aucun total fourni par le navigateur n’est enregistré comme source de vérité. Le timbre et l’assiette des retenues sont paramétrables. Le net prévu est distinct des retenues effectivement justifiées, et le solde réel déduit les paiements et justificatifs enregistrés.

### Facturation électronique (loi de finances 2026, article 53)

L’article 53 de la loi n° 2025-17 du 12 décembre 2025 et la note commune n° 02/2026 étendent la facturation électronique obligatoire aux opérations de prestations de services, y compris les activités libérales, sous réserve des précisions et des modalités d’adhésion publiées par l’administration fiscale. **Cette application ne produit pas de facture électronique conforme** : elle génère un PDF archivé et lisible, sans génération ni transmission du format TEIF, sans signature électronique ANCE et sans référence d’enregistrement Tunisie TradeNet. Un PDF, même signé visuellement, ne constitue pas un document structuré au sens du dispositif. Avant d’utiliser ce module comme outil de facturation, vérifier avec un comptable le champ d’application, la date d’entrée en vigueur applicable au cabinet, la procédure d’adhésion et le support admis. L’intégration TEIF/TTN relève d’une phase ultérieure et n’est pas revendiquée ici.

### Assiette des retenues et timbre

Le timbre est ajouté après la TVA et n’est pas soumis à la TVA. Il est désormais **toujours exclu** de l’assiette des retenues enregistrées par l’interface, qui n’expose plus cette option ; l’assiette reste paramétrable entre montant TTC hors timbre et montant HT, car le régime dépend de la nature de l’opération. Les valeurs initiales (TVA 19/13/7 %, timbre 1,000 TND, aucune retenue) sont indicatives et doivent être vérifiées avec un professionnel. La démonstration de calcul de la retenue sur le TTC hors timbre est couverte par les tests unitaires.

Les honoraires fixes acceptent jusqu’à 999999999999,999 TND. La migration de précision des honoraires refuse son retour arrière si des valeurs ne tiennent plus dans l’ancien format ; les corriger avant rollback.

Les devis passent de brouillon à émis, puis accepté/refusé ou expiré ; ils peuvent être dupliqués et un devis accepté peut donner une facture. Seuls les brouillons de devis sont archivables. Les factures émises sont figées au niveau des modèles et des services : numéro, lignes, profil, client, taux et totaux sont conservés ; leur statut peut évoluer. Les écritures SQL directes ou d’autres applications peuvent contourner ces protections : restreindre strictement les accès à la base. Les paiements partiels sont enregistrés séparément. Une facture ayant un paiement ou une retenue justifiée ne peut pas être annulée ; l’annulation d’une facture impayée crée un avoir intégral conservé. Le remboursement et les avoirs partiels ne sont pas définis.

L’émission attribue un numéro transactionnel par type et année ; la date du document doit être celle du jour dans le fuseau de l’application. La configuration de taux et de profil est figée à l’émission, pas à la création du brouillon. Si la version fiscale a changé, les taux d’un devis dupliqué ou converti doivent encore exister dans la version applicable, sinon l’opération est refusée. La continuité des numéros ne couvre pas les modifications directes de base ou les restaurations de sauvegardes. Le scheduler `billing:update-statuses` marque les factures impayées en retard et les devis expirés, sans envoyer d’email.

L’administrateur configure les versions fiscales, le profil et les formats dans **Finances → Paramètres financiers**. Les versions ne sont pas modifiées via cette interface ; une nouvelle version peut remplacer la version applicable. Chaque format doit contenir exactement une fois `{year}` et `{sequence}`. Le seeder `TaxSettingsSeeder`, appelé par `DatabaseSeeder` et exécutable séparément, ne crée que des exemples **non validés** : TVA 19/13/7 %, timbre 1,000 TND, sans retenue, et timbre désactivé. Ces chiffres sont indicatifs et doivent être confirmés avant emploi. Pour une mise à niveau :

```sh
docker compose up -d --build
docker compose exec -u www-data app php artisan migrate --force
docker compose exec -u www-data app php artisan db:seed --class=RolePermissionSeeder --force
# Facultatif : exemples indicatifs, non validés
docker compose exec -u www-data app php artisan db:seed --class=TaxSettingsSeeder --force
```

`RolePermissionSeeder` synchronise les permissions des rôles suivant `config/access.php`. Les permissions financières sont attribuées aux administrateurs et architectes ; un collaborateur doit recevoir les permissions dédiées et être membre du projet, avec `viewer` en lecture seule. Clients et entreprises sont exclus. Les listes sont filtrées en base et les actions sont contrôlées par les policies et les services.

### PDF local et précautions

Le profil Compose facultatif `pdf` lance un service Chromium privé, non publié sur l’hôte et accessible uniquement via un réseau interne partagé avec l’application. Il reçoit uniquement le HTML produit par le serveur, refuse les ressources réseau, désactive JavaScript et les service workers, limite la taille, le temps et la concurrence ; le conteneur est non-root, en lecture seule et sans capacités Linux. Le binaire headless shell x86-64 est épinglé via `@sparticuz/chromium` (MIT ; licences propres à Chromium) : le mettre régulièrement à jour et prévoir une image adaptée sur ARM. Chromium fonctionne avec `--no-sandbox` dans cet environnement Docker : l’isolation du conteneur ne remplace pas un durcissement et une revue de sécurité de production. Les polices Inter et Cairo sont intégrées au PDF. Les devis, factures, avoirs et reçus disposent de liens FR/AR autorisés. Aucun service PDF externe ni CDN n’est requis.

Dans `.env` **local, non versionné**, définir `BILLING_PDF_TOKEN` avec au moins 32 caractères aléatoires (par exemple, générer une valeur avec `openssl rand -hex 32` puis l’inscrire manuellement). Recréer l’application pour lui transmettre la variable et démarrer le service :

```sh
docker compose --profile pdf up -d --build
```

Si le service est arrêté ou le secret absent, les liens PDF renvoient une indisponibilité. Le code serveur communique avec le conteneur privé, jamais avec `localhost` depuis le navigateur. Des exemples fictifs FR et AR ont été inspectés visuellement : sens RTL et lettres liées sur une page. Valider aussi sur le système cible la pagination et l’incorporation des polices, ainsi que les mentions et le modèle d’avoir avec le comptable. Les PDF sont balisés, mais l’extraction et le copier-coller du texte arabe présentent encore des erreurs d’ordre ou de caractères selon le lecteur ; le problème n’est pas résolu et le balisage seul ne certifie pas leur accessibilité. Le logo du profil est un PNG ou JPEG privé de 256 Ko et 2 048 × 2 048 pixels maximum, vérifié sur son extension, son type réel et ses dimensions, puis figé dans le document à l’émission. Cette vérification ne remplace pas un antivirus. Un échec entre stockage et transaction peut laisser un fichier orphelin ; ne pas supprimer automatiquement un fichier après un commit incertain.

### Interface et vérifications

L’interface distingue les actions principales et secondaires, les états vides, les messages de validation et les sections repliables ; les écrans financiers affichent les limites fiscales connues plutôt que de les masquer. Les tokens Tailwind définissent un fond clair `#F6F8FC`, le texte `#172033`, l’indigo `#4338CA` et les couleurs sémantiques. Inter et Cairo sont distribuées localement sous SIL OFL et les icônes Heroicons sous MIT ; leurs licences sont dans `resources/licenses`. Le layout, les pages d’accueil, les composants de base, les écrans financiers et plusieurs écrans clients, projets, documents et planning ont été retravaillés ; les anciens écrans ne sont pas tous refondus. Le responsive et la conformité AA de l’ensemble des pages ne sont pas encore certifiés.

Commandes : `npm ci && npm run build`, `php artisan test`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`. La CI utilise MySQL, vérifie notamment calculs, autorisations et concurrence, et exécute les smoke tests Chrome et les rendus PDF. Ces tests n’établissent pas une conformité comptable ou réglementaire. Les exemples de test contiennent des données fictives et ne doivent pas être utilisés comme documents réels.


L’audit navigateur `tests/smoke/accessibility_ui.mjs` utilise axe-core sur les règles automatisables WCAG 2/2.1 A/AA et détecte le débordement horizontal sur 43 routes staff en français et en arabe, chacune aux tailles desktop et mobile, avec des données fictives. Il vérifie aussi les sections repliables ouvertes, les erreurs de validation de six formulaires, le menu de navigation mobile ouvert, la page 404, l’unicité du titre principal, les repères `main`, les attributs `lang`/`dir`, un parcours clavier de huit tabulations et le remaillage de cinq écrans à 320 px. `tests/smoke/contrast_tokens.mjs`, exécuté en CI, contrôle 15 paires de couleurs du design system. Ces contrôles ne couvrent pas tous les rôles, largeurs, modales, notifications ou états de chargement, et ne constituent pas une certification : la revue humaine du clavier, des lecteurs d’écran et le zoom restent nécessaires (voir le dossier de validation). Les PDF ne sont pas inclus dans ce contrôle.

Pour reproduire le diagnostic d’extraction d’un PDF fictif avec MuPDF (le résultat d’un autre lecteur peut différer) :

```sh
python3 -m pip install PyMuPDF
python3 tests/manual/check_pdf_text.py chemin/vers/devis-exemple-ar.pdf \
  --expect 'شركة المثال للتصميم' --expect 'مشروع سكني نموذجي'
```

La commande échoue actuellement sur l’exemple arabe connu : elle vérifie des phrases exactes, sans inverser les caractères ni masquer les problèmes de ligatures et de texte mixte. Utiliser uniquement des données fictives pour partager les diagnostics.

## Phase 7 — tableau de bord, temps et rentabilité

Le tableau de bord est calculé côté serveur par `DashboardService` et adapté au rôle : projets actifs par statut, chiffre d'affaires facturé et encaissé sur le mois, le trimestre et l'année, impayés et retards, devis en attente, tâches en retard et échéances, charge par collaborateur. Les rôles sans `reports.financial` (collaborateurs) reçoivent les indicateurs d'activité et de temps, jamais les montants ; clients et entreprises ne voient que leurs projets. Les graphiques utilisent Chart.js servi localement, chargé à la demande et désactivé si `prefers-reduced-motion` est actif ; chaque graphique possède un équivalent textuel dans un tableau repliable.

Les temps se saisissent depuis **Temps** : chronomètre serveur (une seule entrée en cours par utilisateur) ou saisie manuelle, avec projet, tâche facultative, date, durée et note. La durée journalière est plafonnée à 1 440 minutes, contrôlée dans une transaction avec verrou de ligne ; un chronomètre oublié est ramené aux heures restantes. Chaque entrée fige le coût horaire applicable au moment de la saisie, de sorte qu'une modification ultérieure des taux ne réécrit pas l'historique. Seul l'auteur corrige ses entrées ; `time.view_all` permet de les consulter toutes.

**Rapports** présente la rentabilité par projet sur une période, avec filtres client et projet : heures, coût du temps, honoraires facturés HT, encaissements, marge, taux horaire effectif et taux de marge. Les montants sont calculés en BCMath, arrondis une fois par projet ; l'indicateur de marge est vide lorsque rien n'est facturé. Les exports `XLSX` et `PDF` respectent la langue et le sens de lecture de la session. L'export XLSX utilise un writer interne fondé sur `ext-zip` (`app/Services/XlsxWriter.php`) : la dépendance `openspout` n'a pas pu être installée dans l'environnement de construction, le writer reste isolé derrière `XlsxWriter::write()` pour être remplacé sans toucher aux contrôleurs. L'export PDF réutilise le rendu privé décrit plus haut, avec les mêmes réserves sur l'extraction du texte arabe.

Les coûts sont paramétrables dans **Rapports → Paramètres de rentabilité**, réservé à `time.rates.manage` (administrateur) : versions datées du coût horaire par défaut et surcharges par utilisateur. Aucun taux n'est écrit en dur ; sans configuration, le coût vaut `0,000` et la marge est donc égale aux honoraires. Ces coûts sont des hypothèses de gestion internes, sans incidence fiscale.

Permissions ajoutées dans `config/access.php` :

| Permission | admin | architect | collaborator |
|---|---|---|---|
| `reports.view` | oui | oui | oui |
| `reports.financial` | oui | oui | non |
| `reports.export` | oui | oui | non |
| `time.log` | oui | oui | oui |
| `time.view_all` | oui | oui | non |
| `time.rates.manage` | oui | non | non |

Les agrégats sont calculés par requêtes agrégées bornées par période, jamais par chargement de collections complètes. Le bilan du MVP et la liste des corrections à traiter avant la phase 8 sont tenus dans [`docs/phase7-mvp-review.md`](docs/phase7-mvp-review.md), et l'état inter-sessions dans [`docs/PROGRESS.md`](docs/PROGRESS.md).

## Phase 8 — suivi de chantier

Le module **Chantier** d'un projet (lien depuis la fiche projet) réunit trois objets complémentaires :

- **Comptes rendus de réunion** : numérotés par projet et par année (`CR-2026-0001`), avec ordre du jour, décisions, date de la prochaine réunion et rattachement des réserves et photos. Un brouillon est modifiable ; la finalisation fige le contenu (le modèle refuse toute écriture ultérieure) et le rend visible au client. Seuls `site.reports.finalize` (administrateur, architecte) peuvent finaliser ; `site.reports.manage` (y compris collaborateurs) permet de rédiger.
- **Réserves et observations** : cycle `ouverte → levée → validée`, gravité (mineure, majeure, critique), localisation, responsable, échéance et retard signalé par un libellé explicite. Une réserve validée est figée et ne peut plus être supprimée ; la levée est validée par `site.snags.validate` (administrateur, architecte).
- **Photos** : stockées sur le disque privé, jamais accessibles en statique. Chaque fichier est **ré-encodé avec GD** (JPEG, 1 600 px au plus, vignette 400 px), ce qui normalise le format et supprime les métadonnées EXIF, dont la géolocalisation. Elles sont servies par une route authentifiée (`Cache-Control: private`, `nosniff`). Une photo rattachée à un compte rendu finalisé fait partie de l'archive et ne peut plus être supprimée.

### Avancement

Deux pourcentages coexistent : la **valeur déclarée** par l'architecte (relevés datés et historisés, `site.progress.record`) qui fait foi, et une **valeur calculée** depuis le planning, indicative, pondérée par les heures estimées ou, à défaut, par la durée planifiée des tâches ; elle applique des facteurs par statut définis dans `config/site.php` (`done` 1, `in_review` 0,75, `in_progress` 0,5, `blocked` 0,25, `todo` 0). Chaque relevé conserve la valeur calculée du moment : modifier le planning plus tard ne réécrit pas l'historique. Un écart de 10 points ou plus déclenche un avertissement explicite invitant à justifier l'écart ou à mettre à jour les tâches. Ces règles sont des conventions internes, sans portée contractuelle.

### Export et périmètre client

Un compte rendu peut être exporté en PDF français ou arabe via le rendu privé décrit plus haut, avec le même avertissement sur l'extraction du texte arabe. Le client voit, en lecture seule, la fiche chantier, les comptes rendus **finalisés**, les réserves, l'avancement déclaré et les photos publiées (rattachées à un compte rendu finalisé ou à une réserve) ; il ne voit ni les brouillons, ni les notes de suivi, ni les photos de travail. Les entreprises (`contractor`) n'ont aucun accès. La permission `site.client.view` est la seule ajoutée au rôle client.

Permissions de la phase 8 :

| Permission | admin | architect | collaborator | client |
|---|---|---|---|---|
| `site.view` | oui | oui | oui | — |
| `site.reports.manage` | oui | oui | oui | — |
| `site.reports.finalize` | oui | oui | — | — |
| `site.snags.manage` | oui | oui | oui | — |
| `site.snags.validate` | oui | oui | — | — |
| `site.progress.record` | oui | oui | — | — |
| `site.photos.manage` | oui | oui | oui | — |
| `site.client.view` | — | — | — | oui |

Le module requiert l'extension **GD** (JPEG, WebP) : elle est ajoutée à l'image Docker et aux extensions de la CI. Sans GD, un téléversement échoue explicitement plutôt que d'enregistrer un fichier non vérifié. Les fichiers restent soumis aux limites `SITE_PHOTO_MAX_KB` (8 Mo par défaut) et ne sont pas analysés par un antivirus ; l'isolation du stockage et une politique de sauvegarde restent nécessaires.

## Phase 9 — base de prix et métré

Le catalogue, initialement vide, permet aux administrateurs et architectes de gérer
des articles (catégorie, désignation, unité, prix TND) et conserve l'historique des
prix. Un métré par projet réutilise les articles et affiche les prix **courants** ;
une variation de prix modifie donc les montants des métrés. Les lignes peuvent être
copiées vers un nouveau devis ou ajoutées à un devis brouillon, avec un taux de TVA
choisi parmi les taux configurés. Les prix copiés restent indépendants du catalogue
et les devis émis restent immuables. Permissions : `prices.view`, `prices.manage`
(administrateur et architecte). Aucune recette visuelle humaine n'est revendiquée.

## Phase 10 — permis et urbanisme (en cours)

Des dossiers par projet permettent de saisir le type, la commune, l'état, les dates,
les observations et des étapes ou pièces à fournir. Il est possible de lier des
documents existants du même projet. Les règles et échéances sont saisies par le
cabinet et ne constituent pas des règles officielles préremplies. La recette humaine reste à réaliser. Après migration,
resynchroniser les rôles via `php artisan db:seed --class=RolePermissionSeeder` ;
les droits sont `permits.view` et `permits.manage`.

Les rappels des étapes de permis non terminées sont envoyés dans le centre de
notifications une fois par jour et par utilisateur autorisé, y compris pour les
échéances dépassées. Régler `PERMIT_REMINDER_DAYS`, `PERMIT_REMINDER_TIME` et
`PERMIT_TIMEZONE` selon les pratiques du cabinet et faire fonctionner le scheduler
Laravel. Ces rappels sont internes à l'application, sans envoi de courriel ; ils ne
constituent pas un calcul de délai légal. Les dates d'échéance du dossier lui-même ne
créent pas de rappel : saisir une étape pour être rappelé.

## Phase 11 — espace client (en cours)

L'espace `/portal` regroupe les projets visibles du compte client, leurs documents
partagés, planning et chantier. L'administrateur gère les comptes associés à chaque
client depuis sa fiche et active **explicitement** l'accès financier par compte ; les
comptes existants liés par `clients.user_id` conservent l'accès au projet sans accès
financier par défaut. La révocation retire aussi ce lien historique s'il existe.
Seuls devis et factures émis sont affichés, avec PDF FR/AR et paiements ; aucun devis
n'est validable par le client. Il peut commenter les documents partagés et déposer un
nouveau document, soumis à l'inspection habituelle et conservé sur le disque privé.
Cette inspection n'est pas un antivirus. Après migration, resynchroniser les rôles
(`php artisan db:seed --class=RolePermissionSeeder`). Recette humaine, sécurité et
accessibilité restent à vérifier ; limites PDF arabe déjà documentées en phase 6.

## Phase 12 — PWA (socle seulement)

Un manifeste permet l'installation et le service worker ne met en cache que l'écran
public et son script, jamais les pages authentifiées. Un coffre local AES-GCM (clé
issue d'une phrase secrète via PBKDF2) permet de préparer les projets en ligne et de
saisir un relevé d'avancement hors ligne. La synchronisation manuelle nécessite une
session du même utilisateur et réapplique les autorisations. Les opérations sont
idempotentes ; si un nouveau relevé serveur est intervenu, la version serveur est
conservée et le brouillon local marqué en conflit. Un seul relevé en attente par projet.

La phrase secrète n'est pas transmise ni récupérable ; sa perte ou l'effacement des
données du navigateur rend les brouillons irrécupérables. Les données sont déchiffrées
pendant l'utilisation sur l'appareil ; éviter les appareils partagés ou compromis et
purger le coffre après usage. Les photos sont synchronisées comme photos internes non rattachées ; elles ne sont pas
publiées automatiquement. Le fichier original et ses métadonnées restent chiffrés dans
le coffre jusqu'à synchronisation, puis le serveur ré-encode l'image et supprime EXIF.
Les réserves existantes peuvent être préparées en ligne (500 maximum par projet),
consultées et modifiées hors ligne selon les permissions. Une version périmée est
refusée : le serveur reste prioritaire et le brouillon est conservé en conflit. Les
réserves validées sont figées. Après synchronisation, préparez à nouveau la liste pour
obtenir les données courantes. La consultation générale hors ligne reste **à réaliser**. Une recette humaine et une revue de sécurité
dédiée restent nécessaires.

## Espace de démonstration local

Sur une installation **locale isolée**, après les migrations et le seed habituel,
lancer explicitement `DEMO_PASSWORD='choisir-un-mot-de-passe-local-unique' php artisan db:seed --class=DemoWorkspaceSeeder`.
Si l'installation contient déjà des comptes ou des données, il faut confirmer explicitement
le risque en ajoutant `DEMO_ALLOW_EXISTING_DATA=1` à cette commande. Les comptes de
démonstration administrateur et architecte bénéficient de leurs permissions normales :
ils peuvent donc voir les données réelles existantes (et l'administrateur les gérer).
Ne jamais activer cela sur une installation exposée ou contenant des données sensibles.
Si la configuration est en cache, lancer d'abord `php artisan config:clear`.
Le mot de passe (12 caractères minimum) est fourni par l'opérateur et n'est pas enregistré
par l'application en clair. Ne pas utiliser cette base pour des données réelles ni exposer
ces comptes sur Internet. Le seeder refuse la production et ne modifie jamais les comptes ou projets existants ;
une collision avec une adresse de démonstration, même archivée, interrompt l'opération. Il crée 15
clients et 30 projets fictifs. Les comptes sont :

| Compte | Accès |
| --- | --- |
| `demo-admin@example.test` | Administration et configuration |
| `demo-architect@example.test` | Gestion métier des projets de démonstration |
| `demo-collaborator@example.test` | Collaboration sur ces projets, sans accès financier |
| `demo-client@example.test` | Portail des projets du premier client fictif, finances autorisées |
| `demo-contractor@example.test` | Tableau de bord limité, selon les permissions existantes |

Tous utilisent le mot de passe choisi dans `DEMO_PASSWORD`. Les taux initiaux restent
indicatifs et non validés : si un taux fiscal applicable est configuré, un devis et une facture fictifs sont créés en brouillon, mais aucun document fiscal émis n'est fabriqué. Pour les PDF,
configurer le profil du cabinet (dont le logo) et faire valider les paramètres fiscaux ;
seuls les documents émis sont exportables. L'identité et le logo sont figés à l'émission :
changer le profil ne modifie pas les PDF des documents déjà émis.

Pour une simulation complète isolée (clients, projets, planning, devis, factures et accès), voir [`docs/README-DEMO.md`](docs/README-DEMO.md).
