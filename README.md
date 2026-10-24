# API SYNERGIE

Backend REST Laravel 12 pour l'application d'adhésion React. Les pièces jointes sont enregistrées sur le disque privé Laravel et ne sont servies qu'aux comptes ayant la permission dédiée.

## Configuration locale (Laragon)

1. Créer une base MySQL vide nommée `synergie_adhesion`.
2. Copier `.env.example` vers `.env`, puis adapter `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` et `DB_PASSWORD` à la configuration MySQL locale.
3. Depuis ce dossier, exécuter :

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan app:create-bureau-user
php artisan serve --host=127.0.0.1 --port=8000
```

La commande de création du compte demande interactivement le nom, l'adresse email et un mot de passe d'au moins 12 caractères. Aucun compte par défaut n'est créé. Pour une installation déjà en cours, relancer `php artisan migrate` après la mise à jour : la nouvelle migration attribue le rôle Administrateur aux comptes bureau existants pour préserver leur accès. La commande Artisan crée aussi un nouveau compte en tant qu'administrateur.

Pour créer le super administrateur par seeder, renseigner `SUPER_ADMIN_NAME`, `SUPER_ADMIN_EMAIL` et `SUPER_ADMIN_PASSWORD` dans `.env` (mot de passe d'au moins 12 caractères), puis exécuter `php artisan db:seed`. Le seeder peut être relancé : il met à jour le compte correspondant à l'email et lui attribue le rôle `admin`. Ne pas enregistrer le mot de passe dans le dépôt.

Pour le frontend, définir `FRONTEND_URL` dans `.env` du backend avec son origine exacte (par exemple `http://localhost:5173`), puis depuis la racine copier `.env.example` vers `.env`, vérifier `VITE_API_URL=http://127.0.0.1:8000/api` et lancer React avec `npm run dev`.

## Tester la connexion avec Postman

1. Créer d'abord un compte de bureau dans le terminal du dossier `backend` avec `php artisan app:create-bureau-user`.
2. Dans Postman, envoyer un `POST` à `http://127.0.0.1:8000/api/auth/login`, avec `Accept: application/json`, `Content-Type: application/json` et ce corps JSON :

```json
{
  "email": "adresse utilisée lors de la création du compte",
  "password": "mot de passe choisi",
  "device_name": "Postman"
}
```

3. Copier la valeur `token` renvoyée par la réponse `200`.
4. Pour tester une route protégée, par exemple `GET http://127.0.0.1:8000/api/auth/me`, ajouter `Authorization: Bearer <token>` ainsi que `Accept: application/json`.

Une route de bureau appelée sans jeton renvoie maintenant `401 Unauthenticated` en JSON au lieu d'essayer de rediriger vers une page de connexion web. Un `422` sur `/api/auth/login` indique que le compte ou le mot de passe ne correspond pas; vérifiez l'adresse utilisée et recréez ou mettez à jour le compte avec la commande interactive ci-dessus.

## API

Les erreurs de validation utilisent le format Laravel `422` avec un objet `errors`.

| Méthode | Route | Accès | Fonction |
|---|---|---|---|
| `POST` | `/api/auth/login` | Public, limité | Connexion et émission d'un jeton Sanctum |
| `GET` | `/api/auth/me` | Jeton | Identité du compte connecté |
| `POST` | `/api/auth/logout` | Jeton | Révocation du jeton courant |
| `GET` | `/api/links/{code}` | Public | Vérification du lien actif et enregistrement d'une visite |
| `POST` | `/api/links/{code}/dossiers` | Public | Validation et enregistrement d'une adhésion |
| `GET` | `/api/admin/dashboard` | `dashboard.view` | Statistiques des dossiers, liens et visites |
| `GET` | `/api/admin/dossiers` | `adhesions.view` | Liste paginée, recherche avec `search` et `per_page` |
| `GET` | `/api/admin/dossiers/{id}` | `adhesions.view` | Détail d'un dossier |
| `PATCH` | `/api/admin/dossiers/{id}` | `adhesions.update` | Modification des informations d'un dossier |
| `DELETE` | `/api/admin/dossiers/{id}` | `adhesions.delete` | Suppression du dossier et de ses pièces jointes privées |
| `GET` | `/api/admin/dossiers/export` | `adhesions.export` | Extraction CSV des informations (sans pièces jointes) |
| `GET` | `/api/admin/dossiers/{id}/documents/{type}` | `documents.view` | Document privé (`photo`, `cni-recto`, `cni-verso`, `signature`) |
| `GET` | `/api/admin/links` | `links.view` | Liste des liens avec nombres de visites et dossiers |
| `POST` | `/api/admin/links` | `links.create` | Création d'un lien actif (`nom`) |
| `PATCH` | `/api/admin/links/{id}` | `links.update` | Activation/désactivation (`actif`) |
| `GET`, `POST`, `PATCH`, `DELETE` | `/api/admin/users` | `users.manage` | Gestion des comptes et attribution de rôles |

## Rôles et permissions

Les administrateurs gèrent les comptes dans **Bureau → Comptes et rôles**. Plusieurs rôles peuvent être attribués à un compte ; leurs permissions s'additionnent. Le rôle Administrateur donne accès à toutes les fonctions.

Le catalogue des rôles prédéfinis est maintenu dans `config/rbac.php` et servi par l'API ; il n'existe donc pas de table de rôles à peupler avec un seeder. La migration crée uniquement la table `user_roles`, qui stocke les rôles effectivement attribués aux comptes.

| Rôle prédéfini | Accès |
|---|---|
| Consultation des adhésions | Lire les dossiers, sans pièces jointes |
| Consultation des pièces | Lire dossiers et pièces jointes |
| Modification des adhésions | Lire et modifier les dossiers |
| Suppression des adhésions | Lire et supprimer les dossiers |
| Extraction des adhésions | Lire et extraire un CSV sans pièces jointes |
| Gestion des liens | Consulter, générer et activer/désactiver les liens |

Les permissions sont vérifiées côté API, pas uniquement dans l'interface. Un utilisateur ne peut pas modifier ses propres rôles ni supprimer son compte, et le dernier administrateur ne peut ni être rétrogradé ni supprimé. Les pièces jointes sont téléchargées par requête authentifiée ; elles ne reposent pas sur des URLs temporaires partageables.

Une adhésion attend les champs de la fiche (`civilite`, `nom`, `prenoms`, `matricule`, `emploi`, `fonctionSyndicale`, `employeur`, `etablissement`, `dejaSyndique`, `syndicats`, `telephone`, `whatsapp`, `lieu`), les autorisations `precompte` et `consentement`, ainsi que `photo`, `cniRecto`, `cniVerso` et `signature.data` en Data URL PNG, JPEG ou WebP. Le nom du syndicat précédent est obligatoire lorsque `dejaSyndique` vaut `true`. Chaque image est limitée à 1 Mo; les numéros fournis doivent contenir 10 chiffres.

Le tableau de bord accepte le filtre facultatif `?year=2026` et retourne les agrégats mensuels, annuels, par lien, ainsi que les dernières adhésions.

Pour supprimer un dossier dans Postman, envoyer `DELETE http://127.0.0.1:8000/api/admin/dossiers/{id}` avec `Authorization: Bearer <token>` et `Accept: application/json`. La suppression enlève aussi les quatre fichiers privés liés au dossier.

## Vérification

```bash
php artisan route:list --path=api
php artisan test
```
