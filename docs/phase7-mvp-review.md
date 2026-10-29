# Bilan du MVP (phases 1 à 7) et corrections avant la phase 8

Document de travail interne, à relire en début de phase 8. Il décrit ce qui est livré,
ce qui est vérifié et ce qui ne l'est pas. Aucune ligne ne constitue une validation
fiscale, comptable ou juridique.

## 1. Périmètre livré

| Phase | Contenu | État |
|---|---|---|
| 1–2 | Socle Laravel 12, authentification, vérification d'email, invitations, rôles et permissions (`spatie/laravel-permission`), journal d'activité, profil, durcissement des sessions | Livré, CI verte |
| 3 | Clients et contacts, projets et statuts, membres, notes internes, références, gouvernorats et délégations | Livré, CI verte |
| 4 | Bibliothèque documentaire privée : catégories, versions, commentaires, inspection de fichiers, ZIP, restaurations | Livré, CI verte |
| 5 | Planning et tâches : modèles de phases, dépendances, charge, calendrier ouvré et jours fériés, notifications planifiées | Livré, CI verte |
| 6 | Devis, factures, avoirs, paiements, retenues, paramètres fiscaux, PDF FR/AR, refonte de l'interface et design tokens | Livré ; **réserves ouvertes** (voir § 4) |
| 7 | Tableau de bord par rôle, graphiques accessibles, temps et chronomètre, rentabilité, permissions, exports XLSX/PDF | Livré sur cette branche ; validation en cours |

## 2. Ce que la phase 7 apporte au MVP

- **Pilotage** : projets actifs par statut, CA facturé et encaissé (mois, trimestre, année),
  impayés et retards, devis en attente, tâches en retard et échéances, charge par
  collaborateur, tendance sur six mois. Calculs dans `DashboardService`, jamais dans les vues.
- **Temps** : `time_entries` avec chronomètre serveur, une entrée en cours par utilisateur,
  plafond de 1 440 minutes par jour contrôlé sous verrou de ligne, coût horaire figé à la
  saisie, correction réservée à l'auteur.
- **Rentabilité** : `ProfitabilityService` produit heures, coût du temps, honoraires HT,
  encaissements, marge, taux horaire effectif et taux de marge, en BCMath, arrondi une fois
  par projet. Coûts paramétrables et versionnés, sans aucune valeur en dur.
- **Rapports** : filtres période, client et projet ; exports XLSX (writer interne) et PDF
  (rendu privé existant), dans la langue et le sens de la session.
- **Droits** : `reports.view`, `reports.financial`, `reports.export`, `time.log`,
  `time.view_all`, `time.rates.manage` répartis entre admin, architecte et collaborateur,
  avec tests de non-fuite des montants financiers.

## 3. Vérifications réalisées pour la phase 7

- Tests Feature `tests/Feature/PhaseSevenTest.php` : indicateurs financiers réservés aux
  rôles autorisés, absence de montants pour un collaborateur, 403 sur rapports, temps et
  export, plafond journalier, chronomètre unique, correction interdite à un tiers, validité
  du classeur XLSX, PDF envoyé au rendu privé, rendu FR/LTR et AR/RTL des écrans.
- Pint, PHPStan niveau 5 et suite PHPUnit exécutés par la CI, sur MySQL 8 : commit
  `3b21a97`, exécution `123581991503` (quality, laravel-upgrade et docker-smoke vertes).
- Audit navigateur étendu à `/time`, `/reports` et aux paramètres de rentabilité, en
  français, à 1 440 px, 375 px et 320 px ; débordement, contrastes, structure de titres,
  repères et identifiants uniques vérifiés automatiquement.
- Contrôle des clés de traduction des vues de la phase 7 en FR et AR (aucune clé manquante).

## 4. Réserves ouvertes héritées des phases précédentes

1. **PDF arabe** : le copier-coller du texte arabe reste fautif selon le lecteur. Aucun
   contournement heuristique n'est appliqué sur des documents financiers. La phase 6 ne peut
   pas être déclarée achevée sur ce point.
2. **Fiscalité** : montants, timbre, retenues et mentions obligatoires n'ont pas été validés
   par un comptable. Aucune facture électronique TEIF/TTN n'est produite ; le module ne
   revendique pas la conformité à l'article 53 de la loi n° 2025-17.
3. **Accessibilité** : les contrôles automatisés (axe-core, contrastes, reflow) ne remplacent
   pas une revue humaine au clavier et au lecteur d'écran, ni une vérification AA complète.
4. **Recette manuelle** : la recette FR/AR mobile et desktop de la refonte graphique reste
   à consigner dans `docs/phase6-validation.md`.

## 5. Corrections à traiter avant la phase 8

### Priorité 1 — bloquant pour un usage réel

| # | Sujet | Action attendue |
|---|---|---|
| 1 | Extraction du texte arabe des PDF | Trancher entre changer de moteur de rendu, produire un PDF non balisé assumé, ou documenter contractuellement la limite. Décision produit, pas correctif silencieux. |
| 2 | Paramètres fiscaux | Faire vérifier TVA, timbre, retenue et assiette par un comptable, puis enregistrer une version fiscale validée. |
| 3 | Sauvegarde et restauration | Documenter et tester une restauration MySQL + volume `app_runtime` avant toute donnée réelle. |

### Priorité 2 — qualité de livraison

| # | Sujet | Action attendue |
|---|---|---|
| 4 | Writer XLSX maison | Ajouter des tests unitaires sur échappement XML, noms de feuilles, caractères arabes, colonnes de devise ; mesurer le coût sur 5 000 lignes ; prévoir le remplacement par `openspout` dès que Composer est disponible. |
| 5 | Export volumineux | L'export XLSX des temps est plafonné à 5 000 lignes en mémoire : paginer, découper par période ou générer en flux avant un usage massif. |
| 6 | Audit navigateur phase 7 | Ajouter `/time`, `/reports` et les paramètres de rentabilité au parcours `tests/smoke/accessibility_ui.mjs`, en FR et AR, desktop et mobile. |
| 7 | Revue humaine | Clavier, lecteur d'écran, zoom 200 % et contraste réel sur le tableau de bord, le chronomètre et les rapports. |
| 8 | Graphiques | Vérifier dans un navigateur réel le rendu RTL des axes et infobulles, le repli sans JavaScript et le respect de `prefers-reduced-motion`. |

### Priorité 3 — confort et cohérence

| # | Sujet | Action attendue |
|---|---|---|
| 9 | Format des nombres | Les montants restent des chaînes décimales `1234.567` avec séparateur point, y compris en français. Décider d'un formatage localisé (`1 234,567`) sans perdre la précision en base. |
| 10 | Édition d'un temps | Le projet n'est pas modifiable après création, volontairement. Le préciser dans l'interface ou ouvrir un transfert contrôlé. |
| 11 | Sélecteurs de tâches | La liste des tâches est limitée à 300 éléments non terminés ; prévoir une recherche serveur pour les gros portefeuilles. |
| 12 | Vue des temps | Vue hebdomadaire uniquement ; une vue mensuelle et un filtre projet sont à prévoir si l'usage le demande. |
| 13 | Coûts horaires | `time.rates.manage` est réservé à l'administrateur : confirmer que les architectes ne doivent pas saisir eux-mêmes les coûts, ou ouvrir la permission. |
| 14 | Indicateur de marge | Sans coût horaire configuré, la marge égale les honoraires : afficher un avertissement explicite tant qu'aucun taux n'est défini. |

## 6. Décisions de la phase 7 à valider

- **Chart.js 4.6.1 servi localement** (MIT, import dynamique) plutôt qu'un rendu SVG maison.
- **Chronomètre côté serveur** plutôt que côté navigateur, pour éviter les durées fabriquées.
- **Writer XLSX interne** en remplacement d'`openspout`, faute d'installation Composer possible
  dans l'environnement de construction ; à remplacer dès que possible.
- **Facturé HT sur la date du document**, encaissé sur la date de paiement, impayé =
  TTC − paiements − retenues justifiées.
- **Arrondi unique par projet** pour le coût du temps, afin que les totaux restent reproductibles.
