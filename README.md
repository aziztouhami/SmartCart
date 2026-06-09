# SmartCart — Plateforme E-Commerce avec Recommandations Intelligentes

SmartCart est une plateforme e-commerce développée dans le cadre d'un stage chez Sofiatech. Elle combine une API REST sécurisée par JWT, une interface React, et un moteur de recommandation basé sur le filtrage collaboratif pour personnaliser l'expérience de chaque utilisateur.

---

## Vue d'ensemble du projet

L'objectif de SmartCart est de proposer une boutique en ligne complète où chaque utilisateur reçoit des suggestions de produits adaptées à ses habitudes d'achat. Le moteur de recommandation analyse les interactions enregistrées (vues, ajouts au panier, achats, notes) pour calculer des scores de similarité entre utilisateurs et suggérer des produits pertinents en temps réel.

Le projet suit une architecture découplée (Headless) :

- **Frontend React** : application SPA qui communique avec le backend exclusivement via l'API REST, sécurisée par JWT.
- **Backend Symfony 7** : exposition d'une API REST, gestion des entités métier, sécurité, et logique applicative.
- **Moteur de recommandation** : service Symfony indépendant basé sur le filtrage collaboratif, calculant des scores de similarité entre utilisateurs à partir de leurs interactions.
- **Base de données PostgreSQL** : stockage relationnel optimisé avec index sur les tables produits et historique des interactions.

---

## Stack technique

| Couche | Technologie |
|---|---|
| Backend | Symfony 7.4 (PHP 8.2) |
| Frontend | React 18 (JavaScript) |
| Base de données | PostgreSQL 15 |
| Authentification | JWT — LexikJWTAuthenticationBundle v3 |
| Documentation API | swagger-php v4 + Swagger UI (CDN) |
| Conteneurisation | Docker et Docker Compose |
| ORM | Doctrine ORM v2 |
| Validation | Symfony Validator |
| Sérialisation | Symfony Serializer |

---

## Architecture du projet

```
Stage Sofiatech/
├── backend/
│   ├── config/
│   │   ├── packages/          # Configuration Symfony (doctrine, security, jwt, cors...)
│   │   ├── routes/            # Déclaration des routes
│   │   └── jwt/               # Clés RSA pour la signature JWT (générées automatiquement)
│   ├── migrations/            # Migrations Doctrine
│   ├── public/
│   │   └── index.php          # Point d'entrée de l'application
│   └── src/
│       ├── Controller/
│       │   ├── Auth/          # Endpoints d'authentification (login, register, me, logout)
│       │   └── Documentation/ # Génération et affichage de la doc Swagger
│       ├── DTO/
│       │   └── Auth/          # Objets de transfert de données pour l'authentification
│       ├── Entity/            # Entités Doctrine (User, Product, Category, Order...)
│       ├── OpenApi/           # Configuration globale de la spec OpenAPI
│       ├── Recommendation/    # Moteur de recommandation (filtrage collaboratif)
│       ├── Repository/        # Repositories Doctrine pour l'accès aux données
│       ├── Security/          # Voter et composants de sécurité
│       └── Service/           # Logique métier (AuthenticationService...)
│
├── frontend/
│   ├── public/
│   └── src/
│       ├── components/        # Composants React réutilisables
│       ├── context/           # Contextes React (auth, panier...)
│       ├── hooks/             # Hooks personnalisés
│       ├── pages/             # Pages de l'application
│       ├── services/          # Couche de communication avec l'API
│       ├── styles/            # Feuilles de style
│       └── utils/             # Fonctions utilitaires
│
├── docker/
│   └── nginx/                 # Configuration Nginx (profil production)
│
└── docker-compose.yml
```

---

## Démarrage rapide avec Docker

C'est la méthode recommandée. Docker Compose orchestre les trois services (base de données, backend, frontend) et gère automatiquement les migrations et la génération des clés JWT au démarrage.

### Prérequis

- Docker Desktop installé et en cours d'exécution
- Git

### Installation

**1. Cloner le dépôt**

```bash
git clone <url-du-depot>
cd "Stage Sofiatech"
```

**2. Lancer les conteneurs**

```bash
docker compose up -d
```

Au premier démarrage, Docker va :
- construire les images backend et frontend,
- initialiser la base de données PostgreSQL avec l'utilisateur et la base configurés,
- installer les dépendances Composer,
- générer les clés RSA pour les tokens JWT,
- exécuter les migrations Doctrine.

**3. Vérifier que tout fonctionne**

```bash
docker compose ps
```

Les trois services `smartcart_postgres`, `smartcart_backend` et `smartcart_frontend` doivent afficher le statut `Up`.

### Accès aux services

| Service | URL |
|---|---|
| Frontend | http://localhost:3000 |
| API Backend | http://localhost:8000/api |
| Documentation Swagger | http://localhost:8000/api/docs |
| Base de données (externe) | 127.0.0.1:5436 |

---

## Démarrage sans Docker (développement local)

### Backend

```bash
cd backend
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php -S 0.0.0.0:8000 -t public public/index.php
```

Les clés JWT doivent exister dans `config/jwt/`. Si ce n'est pas le cas :

```bash
mkdir -p config/jwt
openssl genrsa -out config/jwt/private.pem 4096
openssl rsa -in config/jwt/private.pem -pubout -out config/jwt/public.pem
```

### Frontend

```bash
cd frontend
npm install
npm start
```

---

## Variables d'environnement

### Backend (`backend/.env`)

| Variable | Valeur par défaut | Description |
|---|---|---|
| `APP_ENV` | `dev` | Environnement Symfony (`dev` ou `prod`) |
| `APP_DEBUG` | `1` | Activation du mode debug |
| `APP_SECRET` | à définir | Clé secrète Symfony |
| `DATABASE_URL` | voir ci-dessous | URL de connexion Doctrine |
| `JWT_EXPIRATION` | `3600` | Durée de validité du token JWT en secondes |
| `CORS_ALLOW_ORIGIN` | `http://localhost:3000` | Origines autorisées pour les requêtes cross-origin |

Exemple de `DATABASE_URL` :

```
postgresql://scu:scp@localhost:5436/scdb
```

En environnement Docker, le backend se connecte au service `postgres` sur le réseau interne :

```
postgresql://scu:scp@postgres:5432/scdb
```

### Frontend (`frontend/.env`)

| Variable | Valeur par défaut | Description |
|---|---|---|
| `REACT_APP_API_URL` | `http://localhost:8000/api` | URL de base de l'API backend |
| `REACT_APP_JWT_STORAGE_KEY` | `smartcart_token` | Clé de stockage du token JWT |
| `REACT_APP_REQUEST_TIMEOUT` | `30000` | Timeout des requêtes HTTP en ms |
| `REACT_APP_PAGINATION_LIMIT` | `20` | Nombre d'éléments par page |
| `REACT_APP_ENABLE_RECOMMENDATIONS` | `true` | Activation du moteur de recommandation |

---

## Services Docker

Le fichier `docker-compose.yml` définit quatre services. Le service `nginx` n'est activé que pour le profil `production`.

| Service | Conteneur | Port exposé | Rôle |
|---|---|---|---|
| `postgres` | smartcart_postgres | 5436 (hôte) → 5432 (conteneur) | Base de données PostgreSQL |
| `backend` | smartcart_backend | 8000 | API Symfony (serveur PHP intégré) |
| `frontend` | smartcart_frontend | 3000 | Application React |
| `nginx` | smartcart_nginx | 80 / 443 | Reverse proxy (production uniquement) |

Pour accéder à la base de données depuis un outil externe comme DBeaver :

```
Hôte     : 127.0.0.1
Port     : 5436
Base     : scdb
Utilisateur : scu
Mot de passe : scp
```

Pour forcer la recréation d'un service après une modification de `docker-compose.yml` :

```bash
docker compose up -d --force-recreate backend
```

Pour réinitialiser complètement la base de données (supprime toutes les données) :

```bash
docker compose down
docker volume rm stagesofiatech_postgres_data
docker compose up -d
```

---

## Structure de la base de données

La base comporte sept tables créées par la migration `Version20260603190000`.

**user**
Stocke les comptes utilisateurs. Les rôles sont enregistrés en JSON (`ROLE_USER`, `ROLE_ADMIN`). Le mot de passe est haché avec bcrypt.

| Colonne | Type | Description |
|---|---|---|
| id | integer | Clé primaire |
| email | varchar | Identifiant unique de connexion |
| password | varchar | Hash bcrypt |
| first_name | varchar | Prénom |
| last_name | varchar | Nom |
| roles | json | Tableau de rôles |
| created_at | timestamp | Date de création |
| updated_at | timestamp | Dernière modification |

**product**
Catalogue des produits avec gestion du stock.

| Colonne | Type | Description |
|---|---|---|
| id | integer | Clé primaire |
| name | varchar | Nom du produit |
| description | text | Description |
| price | decimal | Prix unitaire |
| stock | integer | Quantité en stock |
| slug | varchar | Identifiant URL unique |
| images | json | Tableau d'URLs d'images |
| category_id | integer | Clé étrangère → category |

**category**
Catégories hiérarchiques (une catégorie peut avoir une catégorie parente).

| Colonne | Type | Description |
|---|---|---|
| id | integer | Clé primaire |
| name | varchar | Nom |
| slug | varchar | Identifiant URL |
| parent_id | integer | Clé étrangère auto-référencée |

**order**
Commandes passées par les utilisateurs.

| Colonne | Type | Description |
|---|---|---|
| id | integer | Clé primaire |
| user_id | integer | Clé étrangère → user |
| status | varchar | État (pending, confirmed, shipped, delivered) |
| total_amount | decimal | Montant total |
| shipping_address | json | Adresse de livraison |

**order_item**
Lignes de commande (produit + quantité + prix au moment de l'achat).

| Colonne | Type | Description |
|---|---|---|
| id | integer | Clé primaire |
| order_id | integer | Clé étrangère → order |
| product_id | integer | Clé étrangère → product |
| quantity | integer | Quantité commandée |
| price | decimal | Prix unitaire au moment de l'achat |

**review**
Avis et notes laissés par les utilisateurs sur les produits (note de 1 à 5).

| Colonne | Type | Description |
|---|---|---|
| id | integer | Clé primaire |
| user_id | integer | Clé étrangère → user |
| product_id | integer | Clé étrangère → product |
| rating | integer | Note (1 à 5) |
| comment | text | Commentaire |

**interaction**
Historique des comportements utilisateurs, utilisé par le moteur de recommandation.

| Colonne | Type | Description |
|---|---|---|
| id | integer | Clé primaire |
| user_id | integer | Clé étrangère → user |
| product_id | integer | Clé étrangère → product |
| type | varchar | Type d'action (view, cart, purchase, rating) |
| value | float | Score de l'interaction |
| created_at | timestamp | Horodatage |

---

## Authentification JWT

Le flux d'authentification fonctionne de la façon suivante :

1. L'utilisateur envoie ses identifiants (email + mot de passe) en POST sur `/api/auth/login`.
2. Le backend vérifie les identifiants, puis génère un token JWT signé avec la clé RSA privée.
3. Le token est retourné au client avec sa durée de validité (`expiresIn`).
4. Le frontend stocke le token et l'inclut dans toutes les requêtes suivantes via l'en-tête `Authorization: Bearer <token>`.
5. Le backend valide la signature du token à chaque requête avant de traiter la demande.

Les tokens expirent au bout de 3600 secondes (1 heure) par défaut. Il n'y a pas de mécanisme de refresh token dans la version actuelle — l'utilisateur doit se reconnecter une fois le token expiré.

Les endpoints publics (accessibles sans token) sont :

- `POST /api/auth/login`
- `POST /api/auth/register`
- `GET /api/docs`
- `GET /api/docs.json`

Tous les autres endpoints sous `/api/` exigent un token valide.

---

## Documentation API

La documentation interactive est générée automatiquement à partir des attributs PHP `#[OA\...]` présents dans le code source, via la bibliothèque `zircote/swagger-php`.

Elle est accessible à l'adresse `http://localhost:8000/api/docs` une fois l'application démarrée. Pour tester les endpoints protégés directement depuis l'interface Swagger, cliquez sur le bouton "Authorize" et collez votre token JWT obtenu après connexion.

---

## Moteur de recommandation

Le moteur est implémenté dans `backend/src/Recommendation/`. Il repose sur le filtrage collaboratif (Collaborative Filtering) : pour un utilisateur donné, il identifie les utilisateurs ayant des comportements similaires (basé sur la table `interaction`), puis suggère les produits avec lesquels ces utilisateurs ont interagi positivement mais que l'utilisateur cible n'a pas encore consultés.

Les types d'interactions ont des poids différents dans le calcul du score de similarité : un achat compte davantage qu'une simple vue.

---

## Licence

MIT — projet réalisé dans le cadre d'un stage chez Sofiatech, juin 2026.
