# Mémoire du projet — état inter-sessions

Fichier de reprise. Il résume l'état réel du dépôt à la fin de la dernière session
validée, les conventions à ne pas casser et les points ouverts. Mettre à jour à la fin de
chaque phase, en même temps que le README.

Dernière mise à jour : phase 8 livrée (suivi de chantier), en attente de validation.

## Méthode de travail

1. Une phase à la fois, jamais de phase suivante sans validation explicite.
2. Plan court et questions **avant** d'écrire du code.
3. Exécution par petites étapes : migration, modèles, services, policies, requests,
   contrôleurs, vues, traductions FR/AR, tests.
4. Vérifications : `php artisan test`, `vendor/bin/pint --test`,
   `vendor/bin/phpstan analyse`, CI verte, recette manuelle FR/AR mobile et desktop.
5. Mise à jour du README et de ce fichier, puis résumé et arrêt avant fusion.

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
| 9 et suivantes | Non commencées | — |

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
7. Chantier : faire valider par le cabinet les facteurs d'avancement, la numérotation des comptes rendus et la durée de conservation des photos ; prévoir l'orientation EXIF (extension non installée) et une politique de sauvegarde des images.

## Prochaine étape

Attendre la validation de la phase 8, puis ouvrir la phase 9 (base de prix et métré) avec un
plan court et les questions associées. Les phases 1 à 7 restent à fusionner selon le
processus prévu ; aucune branche `develop` n'est touchée par cette session.
