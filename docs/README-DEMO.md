# Démonstration locale : accès et installation

Ce jeu simule un cabinet avec 15 clients fictifs, 30 projets détaillés, 150 tâches
(à faire, en cours et terminées), 30 jalons, des calendriers de projet, 30 devis et
30 factures fictifs affichables en PDF, des paiements et deux brouillons. Les dates
du planning sont relatives au jour d'installation. Les tâches sont assignées au
collaborateur et visibles sur les calendriers des projets. Le premier client dispose
du portail et de l'accès aux finances de ses deux projets.

**Usage local isolé uniquement.** Les documents marqués « DÉMONSTRATION » sont
synthétiques, sans valeur commerciale ou fiscale : ils ne passent pas par l'émission
réelle, ne valident pas les paramètres fiscaux et ne doivent jamais être transmis à
un client. Ne pas utiliser cette base pour une activité réelle. Les montants, noms,
adresses et coordonnées sont fictifs. Le seeder refuse les bases contenant déjà des
clients, projets, devis ou factures, y compris archivés. Il refuse aussi les adresses
de démonstration déjà utilisées et ne réinitialise aucun mot de passe. Une deuxième
exécution est donc refusée plutôt que de dupliquer ou écraser des données.

## Installation sur Windows / PowerShell

Récupérer le code de la branche indiquée dans la réponse et vérifier qu'il n'y a pas
de modifications locales non sauvegardées. Une **instance Compose distincte** crée
ses propres volumes MySQL et de stockage ; ne pas modifier ou supprimer les volumes
d'une installation existante. Depuis la racine du dépôt :

```powershell
$env:APP_PORT = '8082'
$env:MAILPIT_PORT = '8026'
docker compose -p prime-demo up -d --build
docker compose -p prime-demo exec -u www-data app php artisan migrate --force
docker compose -p prime-demo exec -u www-data app php artisan db:seed --force
docker compose -p prime-demo exec -u www-data app php artisan config:clear
docker compose -p prime-demo exec -u www-data -e DEMO_PASSWORD='Choisir-un-secret-local-unique' app php artisan db:seed --class=RealisticDemoSeeder
```

Utiliser un mot de passe personnel d'au moins 12 caractères à la place de l'exemple.
Ces variables PowerShell changent les ports uniquement dans la session courante.
Ouvrir <http://localhost:8082>. Si les ports sont déjà utilisés, choisir deux autres
ports. Vérifier que le nom Compose `prime-demo` n'est pas déjà utilisé pour des données
à conserver avant de démarrer. Si une instance créée précédemment contient déjà des
données, choisir **un autre nom Compose** et répéter les commandes avec ce même nom ;
ne jamais exécuter `docker compose down -v` sur une installation à conserver.

Si un administrateur a déjà été créé dans cette base isolée, le seeder demande
`-e DEMO_ALLOW_EXISTING_DATA=1` en plus du mot de passe. Les comptes démo admin et
architecte ont des droits globaux sur cette installation. Ne pas exposer ces comptes
sur Internet. N'activer cette option qu'après avoir vérifié que cette base est isolée.

## Comptes de démonstration

Tous les comptes utilisent le mot de passe choisi lors de l'installation ; aucun mot
de passe n'est écrit dans Git. Les comptes sont vérifiés et activés.

| Identifiant | Rôle et accès |
| --- | --- |
| `demo-admin@example.test` | Administration complète, utilisateurs, projets, facturation et configuration |
| `demo-architect@example.test` | Gestion des projets, planning et finances selon les permissions architecte |
| `demo-collaborator@example.test` | Projets auxquels il est affecté, tâches et suivi ; pas d'accès financier |
| `demo-client@example.test` | Portail du premier client fictif et finances de ses deux projets |
| `demo-contractor@example.test` | Tableau de bord limité ; pas d'accès aux projets avec les permissions actuelles |

Les rôles restent ceux de l'application, sans permissions spéciales attribuées par le
seeder. Le mot de passe déjà partagé dans une conversation ou un journal ne doit pas
être réutilisé. Sur chaque projet, ouvrir **Planning**, puis **Calendrier** ou **Gantt**.
Les documents financiers de démonstration apparaissent dans la facturation et dans le
portail du premier client. Pour afficher/télécharger les PDF, configurer le rendu privé
et son jeton comme décrit dans la section « PDF local et précautions » du README principal,
puis démarrer le profil Compose `pdf` avec le même nom `-p prime-demo` et les mêmes ports.
