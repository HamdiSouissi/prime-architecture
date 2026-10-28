# Archi TN — socle technique

Application de gestion pour cabinets d'architecture en Tunisie. Cette première phase fournit Laravel 11, PHP 8.2, MySQL 8, Blade, Tailwind CSS, Alpine.js, FR/AR et Docker. Aucun module métier ni authentification n'est encore implémenté.

> **Sécurité : Laravel 11 est en fin de support depuis le 12 mars 2026.** Il a été retenu conformément à la demande, mais ne doit pas être déployé en production en l'état. Composer est configuré pour autoriser la résolution de dépendances signalées vulnérables (`audit.block-insecure=false`) afin de permettre l'installation de cette version en fin de vie ; cela ne corrige aucune vulnérabilité. Faire `composer audit` et décider d'une migration vers une version de Laravel maintenue avant toute mise en production.

## Prérequis

Git, Docker Engine et Docker Compose v2, accès à Internet pour la construction des images. Les ports 8080 et, si souhaité, 8081 doivent être libres. Aucune installation locale de PHP ou Node n'est nécessaire.

## Installation en cinq commandes

```sh
git clone https://github.com/HamdiSouissi/prime-architecture.git
cd prime-architecture
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

Ouvrir <http://localhost:8080>. Le bouton de langue conserve le choix en session ; l'arabe active la direction RTL. Le premier démarrage peut prendre plusieurs minutes. `docker compose up -d` suffit aussi sans fichier `.env` : les valeurs de développement par défaut sont alors utilisées. `.env` pilote les variables Compose ; le conteneur crée au premier démarrage sa propre clé dans le volume `app_runtime`. La conserver, car sa perte invalide les données chiffrées et les sessions. Les changements au code nécessitent `docker compose up -d --build` (pas de montage du code source). Les fichiers de session et les journaux sont dans `app_storage` ; MySQL est dans `mysql_data`. `docker compose down` conserve les données ; `down -v` les détruit ainsi que la clé.

L'interface phpMyAdmin est facultative : `docker compose --profile dev up -d`, puis <http://localhost:8081> (accessible uniquement depuis la machine hôte). Utiliser l'utilisateur et le mot de passe MySQL du `.env` ; ne jamais exposer phpMyAdmin sur Internet. Les identifiants par défaut sont strictement locaux. Modifier les identifiants avant le premier démarrage : MySQL ne réinitialise pas un volume existant lors d'un changement de variables. Pour la production, définir des secrets uniques hors Git, HTTPS et cookies sécurisés, et remplacer la configuration Compose de développement (qui impose `APP_ENV=local`) par une configuration de déploiement adaptée.

## Emails en développement

Docker Compose inclut Mailpit : l'application envoie ses emails vers le service SMTP interne `mailpit:1025`, et sa boîte de réception est accessible sur <http://localhost:8025>, uniquement depuis la machine hôte. Mailpit intercepte les messages et ne les remet pas à leurs destinataires. Ne pas utiliser cette configuration en production ; les messages de test peuvent contenir des liens sensibles. Il n'y a pas encore de flux d'invitation ou d'authentification dans le socle actuel.

## Vérification et développement

```sh
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
docker compose exec app vendor/bin/phpstan analyse --no-progress
docker compose exec app composer audit
```

`docker compose logs -f app` affiche les logs du processus ; ceux de Laravel sont sous `storage/logs` dans le volume, avec rotation quotidienne. Pour les lire : `docker compose exec app ls -l storage/logs`.

La CI exécute la migration sur MySQL, les tests, Pint, Larastan niveau 5 et la compilation Vite sur chaque push et pull request. Les fichiers `package-lock.json` et `composer.lock` sont versionnés ; utiliser `npm ci` et `composer install` pour installer les versions verrouillées.

## Organisation

- `app/Models`, `app/Http/Controllers` : modèles et contrôleurs Laravel de base.
- `app/Services`, `app/Policies`, `app/Enums` : emplacements réservés aux futures phases.
- `routes/web.php`, `app/Http/Middleware` : routes et sélection de langue.
- `lang/fr`, `lang/ar`, `resources/views` : traductions et composants Blade.
- `database/migrations`, `database/seeders` : schéma versionné et seeder vide.
- `tests/Unit`, `tests/Feature` : tests automatisés.
- `docker/`, `Dockerfile`, `docker-compose.yml` : environnement local.

Les migrations de base Laravel (utilisateurs, cache et jobs) sont présentes, mais aucune authentification n'est activée. `APP_CURRENCY` fixe la devise par défaut sans figer les futurs taux ; toute règle fiscale devra être configurable et validée séparément. Les composants utilisent les classes logiques de Tailwind pour FR/AR.

## Conventions Git

Une fonctionnalité par branche, issue de `develop` selon votre organisation ; PR revue vers la branche cible. Utiliser des commits Conventional Commits (`feat:`, `fix:`, `docs:`, `test:`, `chore:`). La branche de travail de cette session est imposée par Arena ; ni `main` ni `develop` n'ont été créées ou modifiées ici. Ne jamais committer `.env`, les secrets, `vendor` ou `node_modules`.

## Dépendances ajoutées

- Laravel : infrastructure MVC, sessions, migrations et protection CSRF.
- Breeze, Spatie Permission et Spatie Activitylog sont installés pour préparer la phase 2 ; ils ne sont pas encore configurés ni utilisables comme authentification ou contrôle d’accès.
- Tailwind/PostCSS/Autoprefixer et Vite : styles et compilation des ressources ; Alpine.js : menu mobile léger.
- PHPUnit, Mockery, Faker, Collision : tests et outils Laravel de développement ; Pint : formatage ; Larastan/PHPStan : analyse statique.
- Composer et Node sont utilisés dans les images pour installer/compiler les dépendances ; Nginx, MySQL et phpMyAdmin servent respectivement HTTP, persistance et administration locale optionnelle.

### Phase 2 en cours

La migration ajoute les champs de sécurité et la suppression logique aux utilisateurs. **Elle n'active pas l'authentification et ne bloque pas encore les comptes désactivés** : Breeze, les permissions, les invitations et le journal restent à implémenter et vérifier avant toute utilisation. Les cookies de session sont `HttpOnly` et `SameSite=Lax` par défaut ; le flag `Secure` est activé par défaut lorsque `APP_ENV=production`, sauf surcharge explicite. La configuration Compose reste réservée au développement.
