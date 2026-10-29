# Phase 8 — dossier de validation du suivi de chantier

Document interne. Il distingue ce qui est **vérifié automatiquement**, ce qui reste à
**vérifier humainement** et les **limites assumées** du module. Aucune ligne ne constitue une
validation métier, contractuelle ou juridique.

## 1. Ce qui est couvert par la CI

Dernière exécution verte : commit `aadee8c`, trois travaux (`quality`, `laravel-upgrade`,
`docker-smoke`).

| Sujet | Vérification |
|---|---|
| Numérotation des comptes rendus | `CR-<année>-0001` puis `0002` par projet, sous verrou de ligne |
| Numérotation des réserves | `R-<année>-0001` par projet |
| Gel d'un compte rendu finalisé | Mise à jour HTTP refusée (403) **et** écriture directe au modèle refusée (`LogicException`) |
| Suppression | Interdite après finalisation, autorisée sur un brouillon |
| Lecture client | Brouillon invisible et inaccessible ; compte rendu finalisé visible |
| Cycle des réserves | ouvert → levé → validé, levée validée réservée à l'architecte, réserve validée figée |
| Transition impossible | Refusée par la politique, puis par le service si appelé directement |
| Avancement | Pourcentage déclaré, pourcentage calculé pondéré (50,00 sur 8 h faites / 8 h restantes, tâche annulée exclue), instantané conservé quand le planning change |
| Permissions | Collaborateur sans relevé d'avancement, sans validation ; entreprise sans accès ; utilisateur hors projet refusé ; visiteur redirigé |
| Photos | Ré-encodage 2 400 × 1 600 → 1 600 × 1 066, JPEG produit par le serveur, empreinte SHA-256 vérifiée, vignette distincte, fichier privé |
| Visibilité des photos | Photo de brouillon interdite au client ; photo publiée avec un compte rendu finalisé servie en `image/jpeg`, `nosniff` ; suppression refusée après publication |
| PDF | Compte rendu transmis au rendu privé, contenu contrôlé |
| Bilinguisme | `/site` et `/site/reports/{report}` rendus en `lang="fr" dir="ltr"` et `lang="ar" dir="rtl"` |
| Audit navigateur | `/projects/{id}/site` ajouté aux parcours 1 440 px, 375 px et 320 px, en français |

Formatage (Pint), analyse statique (PHPStan niveau 5) et suite PHPUnit complète passent ;
la fumée Docker reconstruit l'image avec l'extension GD.

## 2. Ce qui reste à vérifier humainement

1. **Recette visuelle FR/AR** de `/site`, du formulaire de compte rendu, de la fiche d'un
   compte rendu et de la grille de photos, sur mobile (375 px, 320 px) et bureau, avec un
   vrai jeu de données (l'audit automatique ne voit que l'état vide du jeu Docker).
2. **Accessibilité** : parcours clavier de bout en bout (sélecteurs, formulaires de réserve,
   boutons de transition), lecteur d'écran, zoom 200 %, contrastes réels des badges de
   gravité et de statut.
3. **PDF arabe** : la mise en page du compte rendu réutilise le rendu de la phase 6 ; la
   limite connue d'extraction du texte arabe s'applique aussi ici.
4. **Photo réelle** : téléverser une photo issue d'un téléphone (orientation EXIF, fichiers
   lourds) et vérifier le rendu de la vignette.

## 3. Limites assumées

- **Orientation EXIF** : l'extension `exif` n'est pas installée ; une photo portrait dont
  l'orientation est portée par les métadonnées peut donc apparaître pivotée. Le ré-encodage
  supprime ces métadonnées, y compris la géolocalisation.
- **Aucun antivirus** : le ré-encodage garantit qu'un fichier image valide est produit, pas
  l'absence de charge malveillante dans une image piégée. Une analyse antivirus reste à
  prévoir si le module reçoit des fichiers externes.
- **Pas de vignettes paresseuses** : toutes les vignettes de la page sont chargées
  directement ; une galerie paginée et un chargement différé seront nécessaires au-delà de
  quelques dizaines de photos.
- **Réserves non paginées** : le registre charge toutes les réserves du projet, filtrage et
  tri compris ; à paginer pour les très gros chantiers.
- **Visibilité client** : le client lit toutes les réserves du projet, y compris celles
  rédigées en interne ; aucun champ « note interne » n'existe encore.
- **Facteurs d'avancement** : les pondérations par statut sont des conventions de gestion
  (`config/site.php`), à valider par le cabinet avant tout usage contractuel.
- **Conservation** : aucune purge automatique des photos ni des comptes rendus ; la durée de
  conservation doit être décidée et documentée.
