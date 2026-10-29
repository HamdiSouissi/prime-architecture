# Mémoire du projet — état inter-sessions

Fichier de reprise. Il résume l'état réel du dépôt à la fin de la dernière session
validée, les conventions à ne pas casser et les points ouverts. Mettre à jour à la fin de
chaque phase, en même temps que le README.

Dernière mise à jour : phase 8 livrée (suivi de chantier), en attente de validation.

## Méthode de travail

1. Enchaîner les phases 9 à 14 sans validation intermédiaire, selon la demande du cabinet ; conserver les vérifications et bilans de chaque phase.
2. Plan court et questions **avant** d'écrire du code.
3. Exécution par petites étapes : migration, modèles, services, policies, requests,
   contrôleurs, vues, traductions FR/AR, tests.
4. Vérifications : `php artisan test`, `vendor/bin/pint --test`,
   `vendor/bin/phpstan analyse`, CI verte, recette manuelle FR/AR mobile et desktop.
5. Mise à jour du README et de ce fichier, puis résumé ; aucune fusion sans accord.

## État des phases

| Phase | Contenu | État |
|---|---|---|
| 1–2 | Socle Laravel 12, authentification, rôles et permissions, activité, profil | Livré |
| 3 | Clients, projets, équipe, notes, géographie | Livré |
| 4 | Documents versionnés, catégories, commentaires, ZIP | Livré |
| 5 | Planning, tâches, dépendances, charge, calendrier ouvré, notifications | Livré |
| 6 | Devis, factures, avoirs, paiements, retenues, PDF FR/AR, interface | Livré avec réserves (PDF arabe, fiscalité, revue humaine) |
| 7 | Tableau de bord par rôle, temps et chronomètre, rentabilité, exports | Livré, CI verte (`3b21a97`), en attente de validation |
| 8 | Suivi de chantier : comptes rendus, réserves, avancement, photos privées, PDF | Livré, CI verte (`aadee8c`), en attente de validation |
| 9 | Base de prix et métré | Développé ; vérifications et recette en cours |
| 10 | Permis et urbanisme | Dossiers, étapes, documents et rappels développés ; recette humaine à réaliser |
| 11 | Portail client | En cours : périmètre de base développé, recette et vérifications à poursuivre |
| 12 | PWA et hors-ligne | Relevés, réserves préparées et photos chiffrés ; consultation générale hors ligne à réaliser |
| 13–14 | Sauvegardes et recette | Non commencées |

Le détail des réserves et la liste de corrections sont dans
[`docs/phase7-mvp-review.md`](phase7-mvp-review.md) ; les preuves de la phase 6 dans
[`docs/phase6-validation.md`](phase6-validation.md) et, pour le chantier,
[`docs/phase8-validation.md`](phase8-validation.md).

## Architecture et points d'entrée

- **Permissions** : `config/access.php` est la source unique ; `RolePermissionSeeder` la
  synchronise. Toute nouvelle permission doit être ajoutée aux cinq rôles concernés et testée.
- **Logique métier** : uniquement dans `app/Services`. Les contrôleurs orchestrent, les vues
  affichent, les policies décident.
- **Montants** : chaînes décimales TND, calculs BCMath (`BillingDecimal`, `BillingCalculation`,
  `TaxCalculator`), arrondi demi-supérieur au millime, jamais de flottant.
- **Dashboard** : `DashboardService::overview()` retourne `projects_by_status` (tableau
  `statut => nombre`), `financial` (`periods.month|quarter|year`, `outstanding`,
  `overdue_count`, `overdue[]`), `quotes`, `tasks`, `workload`, `trend`. `financial`,
  `quotes` et `trend` valent `null` sans la permission `reports.financial`.
- **Rentabilité** : `ProfitabilityService::forProjects()` ; coût = Σ(minutes × coût horaire
  figé) / 60, arrondi une fois par projet ; taux horaire effectif = facturé / heures ; taux
  de marge = marge × 100 / facturé (`null` si facturé = 0).
- **Temps** : `TimeEntryService` (`log`, `start`, `stop`, `update`, `delete`), plafond de
  1 440 minutes par jour sous `lockForUpdate`, coût horaire figé à la création.
- **Exports** : `ReportController` construit les lignes, `XlsxWriter` écrit le classeur
  (inlineStr, format devise `#,##0.000`), `PdfGenerator::report()` réutilise le rendu privé.
- **Chantier** : `SiteSupervisionService` (numérotation `CR-`/`R-` par projet et année sous verrou, gel des comptes rendus finalisés, cycle des réserves), `SiteProgressService` (pourcentage déclaré + calcul indicatif pondéré depuis le planning, instantané conservé à chaque relevé), `SitePhotoService` (ré-encodage GD, vignette, EXIF supprimé, disque privé).
- **Vues** : composants Blade `x-page-header`, `x-card`, `x-alert`, `x-button`, `x-form-field`,
  `x-icon` ; tokens `resources/css/app.css` et `tailwind.config.js` ; utilitaires logiques
  `ms-`, `me-`, `ps-`, `pe-`. Chart.js servi localement, chargé à la demande.

## Conventions acquises

- Traductions dans `lang/{fr,ar}`, une clé ajoutée dans les deux langues au même commit ;
  aucun texte en dur dans les vues.
- Toute action importante est journalisée (`activity(...)`), y compris exports et
  paramétrage des coûts.
- Migrations versionnées, réversibles, refusant un rollback destructeur.
- Aucune valeur métier en dur : taux, coûts, délais et règles sont en base ou en config.

## Environnement de développement

- Laravel 12.70.2, PHP `^8.2`, MySQL 8, Docker Compose en local, Node 22 pour les assets.
- Sans Docker : `npm ci && npm run build`, `php artisan test`, `vendor/bin/pint --test`,
  `vendor/bin/phpstan analyse` (niveau 5, `app/` uniquement).
- La CI GitHub Actions (`.github/workflows/ci.yml`) exécute qualité, montée de version
  Laravel 11 et fumée Docker ; elle reste la seule validation possible quand l'environnement
  local n'a ni PHP ni Composer.
- Le rendu PDF nécessite le profil Compose `pdf` et `BILLING_PDF_TOKEN` (32 caractères
  minimum) ; les polices doivent être présentes dans `public/build/assets`.

## Phase 8 — repères

- Migration `2026_10_31_000012_create_site_supervision_tables.php` : `site_reports`, `site_progress_updates`, `snags`, `site_photos`.
- Permissions `site.*` dans `config/access.php` ; `site.client.view` uniquement pour le rôle client.
- Extension **GD** ajoutée au `Dockerfile` et à la CI ; sans elle, le téléversement de photos échoue volontairement.
- `PdfGenerator::siteReport()` réutilise le rendu privé existant (`site/pdf.blade.php`).
- Facteurs d'avancement par statut dans `config/site.php` (`progress_factors`), limites photo dans le même fichier.

## Points ouverts

1. PDF arabe : extraction et copier-coller non résolus (phase 6).
2. Validation comptable des taux, du timbre, des retenues et des mentions (phases 6 et 7).
3. Revue humaine de l'accessibilité (clavier, lecteur d'écran, zoom).
4. Writer XLSX maison à éprouver, à remplacer par `openspout` dès que Composer est disponible.
5. Audits navigateur de la phase 7 et recette manuelle FR/AR encore à consigner.
6. Confirmer que seul l'administrateur gère les coûts horaires et le format des nombres.
7. Chantier : faire valider par le cabinet les facteurs d'avancement, la numérotation des comptes rendus et la durée de conservation des photos ; vérifier l'orientation EXIF sur des photos réelles et une politique de sauvegarde des images.

## Prochaine étape

Poursuivre les phases suivantes selon
[`docs/phases-9-14-plan.md`](phases-9-14-plan.md). Les phases 1 à 7 restent à fusionner selon le
processus prévu ; aucune branche `develop` n'est touchée par cette session.

## Reprise phase 9

Le catalogue et son historique sont exposés aux administrateurs et architectes ; les métrés utilisent les prix courants et se transfèrent vers des devis brouillon via les services de facturation. La CI complète est verte sur `585c454`. La recette humaine FR/AR reste à effectuer. Les notes internes des réserves sont séparées et masquées au client ; des tests de non-divulgation couvrent la page chantier et la sérialisation.

## Reprise phase 10

Dossiers par projet, informations saisies par le cabinet, étapes modifiables et liens
vers documents du projet : `PermitAdministration`, `PermitDossierPolicy`, écrans FR/AR
`permits.*`. Les administrateurs et architectes modifient, les collaborateurs membres
consultent. Les étapes non terminées et échues ou proches sont rappelées dans le centre
de notifications ; horizon, heure et fuseau sont configurables (`config/permits.php`),
un rappel par jour et par utilisateur, limité aux 500 premières étapes. Aucun courriel
n'est envoyé et la date du dossier ne déclenche pas de rappel sans étape correspondante.
L'accès courant est revérifié à l'affichage. Tests automatisés des droits, du rattachement
entre projets, de la déduplication et du retrait des droits ; audits navigateur FR/AR des
pages de dossier sur bureau et mobile. Recette humaine toujours nécessaire.

## Reprise phase 11

Accès client par `client_portal_access`, sans retirer le lien existant `clients.user_id` ;
seul un administrateur attribue explicitement l'accès financier. La révocation supprime
également le lien historique. Portail, dépôt privé via `DocumentAdministration` et
commentaires existants, consultation des documents financiers émis et PDF. Tests de
cloisonnement et CI verte sur `1afbece`, audit navigateur spécifique au client FR/AR sur mobile et bureau inclus. Pas de validation client des devis. Aucun antivirus n'est
installé. Recette humaine et tests de bout en bout complémentaires à réaliser.

## Reprise phase 12

Manifeste et service worker : seul l'écran hors-ligne public et son script sont mis en
cache. `public/pwa/offline.js` chiffre les projets préparés, relevés, nouvelles réserves et photos dans IndexedDB
(AES-GCM, PBKDF2, phrase non stockée) ; une seule opération en attente par projet.
`OfflineProgressSync` applique les relevés sous verrou, vérifie l'utilisateur et les
permissions, déduplique les reprises et refuse les révisions périmées (serveur prioritaire,
brouillon conservé en conflit). La synchronisation nécessite une session valide et la
phrase du coffre ; aucune synchronisation automatique en arrière-plan. Les réserves et photos sont synchronisées de façon idempotente et selon les policies de chantier ; les photos restent internes et non rattachées. CI complète verte
sur `3d00b9c`, dont test navigateur déconnecté, chiffrement et reprise.

Les réserves existantes peuvent être préparées (500 maximum par projet), consultées et modifiées hors ligne ; révision vérifiée sous verrou, conflit conservé localement et priorité serveur. Les réserves validées restent figées. Après synchronisation, préparer à nouveau pour récupérer la version courante. Tests PHP et navigateur déconnecté en CI verte sur `24b8cc3` (run `40948226484`). La consultation générale hors ligne reste à réaliser. Les originaux photo, EXIF compris, restent chiffrés localement jusqu’à synchronisation.
Prévoir tests complémentaires de concurrence, revue de sécurité et recette humaine ;
la perte de phrase ou la purge navigateur rend les données locales irrécupérables.
Les sauvegardes et la recette finale restent à développer (phases 13 et 14).
