# Phase 6 — dossier de validation (accessibilité, fiscalité, PDF)

Ce document rassemble les preuves disponibles et les réserves **non levées**. Il ne constitue ni une certification d’accessibilité WCAG, ni un avis fiscal, ni une garantie de conformité réglementaire.

Dernière mise à jour : commit `c05d90e` (branche `arena/a7217585-prime-architecture`).

## 1. Refonte et couverture des écrans

- 67 vues Blade, dont un composant de titre de page partagé (`x-page-header`) utilisé par les écrans planning et documents.
- Les écrans financiers, documents, planning, utilisateurs, profil, authentification, erreurs et tableau de bord utilisent les tokens de la palette approuvée, des cartes, des alertes typées, des états vides et des tableaux à en-têtes explicites.
- Les écrans restants (catégories de documents, modèles et aperçus planning, calendrier administratif, notifications) partagent désormais le même en-tête, la même hiérarchie et les mêmes espacements.
- Les états interactifs suivants sont vérifiés automatiquement : sections repliables ouvertes, erreurs de validation sur six formulaires (clients, projets, utilisateurs, tâche, document, devis), menu de navigation mobile ouvert, page 404.
- **Non couvert** : tous les rôles (l’audit utilise un administrateur), les modales, les notifications éphémères, la pagination avancée, les écrans 403/410/419/429/500 en situation réelle, les courriels, les PDF.

## 2. Accessibilité — preuves automatisées

- `tests/smoke/accessibility_ui.mjs` exécute axe-core 4.10.3 sur les règles automatisables `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa` en français et en arabe, sur desktop (1440 px) et mobile (375 px).
- Le script vérifie aussi l’absence de débordement horizontal, l’unicité des identifiants, l’état replié/déplié, les erreurs de validation, le menu mobile et le 404.
- `tests/smoke/contrast_tokens.mjs` vérifie 15 paires de couleurs du design system avec les seuils 4,5:1 (texte), 3:1 (indicateur de focus) et documente les bordures décoratives. Les valeurs mesurées sont supérieures aux seuils ; les rapports exacts sont imprimés par le script et rejoués en CI.
- Le layout fournit un lien d’évitement vers `#main-content`, un repère `<main>`, une navigation étiquetée, un fil d’Ariane et un contour de focus visible global (`:focus-visible`).
- La CI exécute ces contrôles ; un échec bloque la fusion.

## 3. Accessibilité — ce qui reste à faire humainement

Une validation automatisée ne couvre qu’une partie des critères WCAG. Pour considérer l’accessibilité « validée » au sens d’un audit, il reste à réaliser, sur le déploiement cible :

1. parcours clavier complet (tabulation, ordre, pièges de focus, échappement des menus) ;
2. lecture avec NVDA (Windows) et VoiceOver (macOS/iOS), en français et en arabe ;
3. zoom navigateur à 200 % et 400 %, largeur équivalente à 320 CSS px ;
4. mode contraste forcé et préférence de réduction des animations ;
5. vérification du sens de lecture et de l’ordre des annonces en RTL ;
6. test avec au moins un utilisateur en situation de handicap ;
7. contrôle des PDF (voir section 5) et des courriels.

Tant que ces points ne sont pas exécutés et documentés, la formulation correcte est : **contrôles automatisés verts, accessibilité non certifiée**.

## 4. Fiscalité — règles implémentées

- Montants en chaînes décimales TND (3 décimales) calculés avec BCMath ; aucun `float`.
- Remises ligne puis document appliquées avant TVA ; TVA calculée par groupe de taux et arrondie une fois par groupe (demi-supérieur au millime).
- Timbre paramétrable, ajouté après la TVA, non soumis à la TVA, et **exclu de l’assiette des retenues** enregistrées par l’interface.
- Assiette de retenue paramétrable entre montant TTC hors timbre et montant HT ; taux multiples configurables.
- Numérotation séquentielle par type et année, allouée sous verrou transactionnel ; annulations conservées.
- Taux, profil, coordonnées, logo et totaux figés à l’émission ; factures émises non supprimables ; devis archivables en brouillon.
- Aucune règle fiscale codée en dur dans les services : les valeurs viennent des versions fiscales administrées.

Sources consultées (consultation du 29 octobre 2026) :

- Loi n° 2025-17 du 12 décembre 2025 (loi de finances 2026), notamment article 53 : <https://www.finances.gov.tn/sites/default/files/2026-01/115725.pdf>
- Obligations de facturation (DGI) : <https://www.finances.gov.tn/fr/node/952> et <https://www.finances.gov.tn/fr/node/75>
- Aperçu du système fiscal tunisien (TVA, fait générateur, obligations) : <https://www.finances.gov.tn/fr/apercu-general-sur-la-fiscalite>

### Points bloquants à faire trancher par un comptable

1. **Facturation électronique** : l’article 53 étend l’obligation aux prestations de services. L’application ne produit ni TEIF, ni signature ANCE, ni envoi ou référence Tunisie TradeNet. Le PDF n’est pas une facture électronique conforme. Vérifier le champ et la date d’entrée en vigueur applicables au cabinet, la procédure d’adhésion et le support admis.
2. **Taux et timbre** : 19/13/7 % et 1,000 TND sont des exemples de seeder non validés. Les remplacer par les taux et montants applicables à la date d’émission.
3. **Retenues** : nature, taux et assiette (TTC hors timbre ou HT) dépendent de l’opération et du client. Le logiciel ne décide pas du régime applicable ; il applique la configuration.
4. **Avoirs partiels et remboursements** : seul l’avoir intégral d’une facture impayée est implémenté.
5. **Exports comptables, TVA suspendue, autoliquidation, opérations en devises** : hors périmètre.

Aucune de ces règles ne doit être présentée comme fiscalement validée avant la réponse écrite du professionnel.

## 5. PDF et copier-coller arabe

- Le rendu arabe est visuellement correct : sens RTL, lettres liées, montants et dates en LTR.
- Le copier-coller reste défaillant selon le lecteur : Chromium écrit les glyphes dans l’ordre visuel et ajoute des balises `/ActualText` par fragment, ce qui inverse des mots et altère certains clusters à l’extraction.
- Essais réalisés sans intégration : désactivation de `rlig` (améliore l’extraction mais casse les ligatures), polices Amiri, Noto, IBM Plex, Tajawal, Typst, `/ActualText` global ou par paragraphe, `aria-label` sur un rôle image. Aucune variante ne donne un résultat fiable et simultanément correct dans PyMuPDF, PDFium et PDF.js sans dégrader le rendu ou la sémantique.
- Décision : ne pas réécrire heuristiquement le texte de documents financiers. Le diagnostic reproductible reste `python3 tests/manual/check_pdf_text.py fichier.pdf --expect 'phrase exacte'`.
- À faire pour résoudre réellement : produire le PDF avec un moteur d’écriture en ordre logique (par exemple via le format TEIF officiel, qui devient de toute façon nécessaire) ou ajouter une couche de texte logique avec une stratégie d’accessibilité validée. Cela dépasse la phase 6 et doit être arbitré.

## 6. Checklist de recette manuelle

- [ ] Remplacer les valeurs fiscales d’exemple par des valeurs validées et enregistrer une version fiscale vérifiée.
- [ ] Créer un profil de cabinet, les trois formats de numérotation et un logo acceptable.
- [ ] Émettre un devis, l’accepter, le convertir, émettre la facture, encaisser un paiement, consulter le reçu, générer un avoir.
- [ ] Ouvrir les PDF FR et AR sur le poste cible et vérifier pagination, mentions et lisibilité.
- [ ] Copier-coller les montants et les noms ; consigner les écarts par lecteur.
- [ ] Exécuter les parcours clavier et lecteurs d’écran listés en section 3.
- [ ] Faire valider les points de la section 4 par écrit.
