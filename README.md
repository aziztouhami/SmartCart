# SmartCart — Plateforme E-Commerce avec Recommandations Intelligentes

SmartCart est une plateforme e-commerce développée dans le cadre d'un stage chez Sofiatech. Elle combine une API REST sécurisée par JWT, une interface React multilingue, un assistant IA intégré (Groq / Llama 3.3), et un moteur de recommandation basé sur le filtrage collaboratif pour personnaliser l'expérience de chaque utilisateur.

---

## Vue d'ensemble du projet

L'objectif de SmartCart est de proposer une boutique en ligne complète où chaque utilisateur reçoit des suggestions de produits adaptées à ses habitudes d'achat. Le moteur de recommandation analyse les interactions enregistrées (vues, ajouts au panier, achats, notes) pour calculer des scores de similarité entre utilisateurs et suggérer des produits pertinents en temps réel.

Le projet suit une architecture découplée (Headless) :

- **Frontend React** : application SPA qui communique avec le backend exclusivement via l'API REST, sécurisée par JWT.
- **Backend Symfony 7** : exposition d'une API REST, gestion des entités métier, sécurité, et logique applicative.
- **Moteur de recommandation** : service Symfony indépendant basé sur le filtrage collaboratif, calculant des scores de similarité entre utilisateurs à partir de leurs interactions.
- **Assistant IA (Chatbot)** : intégration de l'API Groq (Llama 3.3 70B) pour répondre aux questions produits des visiteurs en langage naturel, et pour suggérer automatiquement les attributs standards d'un type de produit dans le panneau admin.
- **Base de données PostgreSQL** : stockage relationnel optimisé avec index sur les tables produits et historique des interactions.

---

## Stack technique

| Couche | Technologie |
|---|---|
| Backend | Symfony 7.4 (PHP 8.2) |
| Frontend | React 18 (JavaScript) |
| Base de données | PostgreSQL 15 |
| Authentification | JWT — LexikJWTAuthenticationBundle v3 |
| OAuth social | Google OAuth 2.0 |
| Chatbot IA | Groq API (Llama 3.3 70B) |
| Traduction (chatbot) | MyMemory Translation API |
| Documentation API | swagger-php v4 + Swagger UI (CDN) |
| Conteneurisation | Docker et Docker Compose |
| ORM | Doctrine ORM v2 |
| Validation | Symfony Validator |
| Sérialisation | Symfony Serializer |
| Génération PDF | DomPDF |
| Internationalisation | i18next (EN / FR) |
| Cartographie | Leaflet (adresses de livraison) |

---

## Architecture du projet

```
SmartCart/
├── backend/
│   ├── config/
│   │   ├── packages/          # Configuration Symfony (doctrine, security, jwt, cors, mailer...)
│   │   ├── routes/            # Déclaration des routes
│   │   └── jwt/               # Clés RSA pour la signature JWT (générées automatiquement)
│   ├── database/
│   │   └── seeders/           # Données de démarrage (produits, catégories, marques...)
│   ├── migrations/            # Migrations Doctrine
│   ├── public/
│   │   ├── index.php          # Point d'entrée de l'application
│   │   └── uploads/           # Images uploadées (produits, marques)
│   └── src/
│       ├── Command/
│       │   ├── AnalyzeSeasonalTrendsCommand.php    # Tendances saisonnières par catégorie (recommandation)
│       │   ├── ExportFeaturesCommand.php           # Export CSV des features ML
│       │   ├── PruneDeletedAccountsCommand.php     # Purge des comptes supprimés (RGPD)
│       │   └── RebuildRecommendationsCommand.php   # Recalcul batch des recommandations
│       ├── Controller/
│       │   ├── Admin/          # Tableau de bord, produits, catégories, marques, commandes,
│       │   │                   # promotions, types de produits, utilisateurs, images, recommandations
│       │   ├── Auth/           # login, register, verify-email, resend-verification, me, google-login, logout
│       │   ├── Brand/          # Catalogue marques (public)
│       │   ├── Cart/           # Panier (ajout, mise à jour, suppression, synchronisation)
│       │   ├── Category/       # Arbre de catégories (public)
│       │   ├── Chatbot/        # ChatbotController — endpoint POST /api/chatbot/message
│       │   ├── Documentation/  # Génération et affichage de la doc Swagger
│       │   ├── Order/          # Commandes utilisateur + téléchargement PDF
│       │   ├── Product/        # Catalogue, avis, interactions, événements anonymes
│       │   ├── Profile/        # Profil, adresses, favoris, tableau de bord, avis
│       │   └── Recommendation/ # RecommendationController — endpoint GET /api/recommendations
│       ├── DTO/                # Objets de transfert de données, un sous-dossier par domaine
│       │                       # (Address, Admin, Auth, Brand, Cart, Category, Chatbot, Favorite,
│       │                       # Order, Pagination, Product, Profile, Promotion, Review) —
│       │                       # classes de données pures, aucune contrainte de validation dedans
│       ├── Entity/             # Entités Doctrine (19), y compris celles du moteur de recommandation
│       │                       # (ProductRelation, UserRecommendation, ColdStartRecommendation,
│       │                       # CategorySeasonalScore)
│       ├── EventListener/      # RuntimeExceptionListener (rendu JSON uniforme des erreurs)
│       ├── ML/                 # LogisticRegressionTrainer, MatrixFactorizationTrainer — algorithmes
│       │                       # d'apprentissage (PHP pur, sans dépendance) utilisés par le moteur
│       │                       # de recommandation
│       ├── OpenApi/            # Configuration globale de la spec OpenAPI
│       ├── Repository/         # Repositories Doctrine (19)
│       ├── Service/            # Logique métier (21 services)
│       │   ├── Ai/             # GroqClientService (client HTTP Groq générique)
│       │   │   └── Prompt/     # Un builder de prompt par fonctionnalité IA
│       │   │                   # (ShopAssistantPrompt, ProductAttributesPrompt)
│       │   ├── Chatbot/        # ChatbotService (logique), TranslationService (MyMemory)
│       │   └── Recommendation/ # 8 services (collaborative, content, cold start, seasonal...)
│       └── Validation/         # Contraintes Symfony Validator en YAML, un fichier par DTO,
│                                # organisées en miroir de DTO/ (Address, Auth, Brand, Cart,
│                                # Category, Chatbot, Order, Product, Profile, Promotion, Review)
│
├── frontend/
│   ├── public/
│   └── src/
│       ├── components/        # Composants React réutilisables
│       │   ├── admin/         # TypeFeatureFields (attributs dynamiques)
│       │   ├── ui/            # Badge, Button, IconButton, Price, Skeleton
│       │   ├── Chatbot.jsx    # Interface de l'assistant IA
│       │   ├── ImageUpload.jsx
│       │   ├── LanguageSwitcher.jsx
│       │   ├── Navbar.jsx
│       │   └── ProductCard.jsx
│       ├── constants/         # categoryIcons.js
│       ├── context/           # CartContext, CategoryContext, FavoriteContext
│       ├── i18n/              # Configuration i18next + traductions (EN / FR)
│       │   └── locales/
│       │       ├── en/        # 5 fichiers de traduction anglais
│       │       └── fr/        # 5 fichiers de traduction français
│       ├── pages/
│       │   ├── admin/         # AdminDashboard, AdminProducts, AdminCategories, AdminBrands,
│       │   │                  # AdminOrders, AdminPromotions, AdminTypes, AdminLayout
│       │   ├── auth/          # Login, Register, VerifyEmail
│       │   ├── brands/        # Brands
│       │   ├── cart/          # Cart
│       │   ├── favorites/     # Favorites
│       │   ├── home/          # Home
│       │   ├── orders/        # Orders
│       │   ├── product/       # ProductDetail
│       │   ├── profile/       # Profile, AddressMapModal
│       │   └── promotions/    # Promotions
│       ├── services/          # api.js, authService.js, cartService.js, chatbotService.js,
│       │                      # sessionService.js, uploadService.js
│       ├── styles/            # variables.css
│       └── utils/             # fetchAllProducts.js, format.js
│
├── docker/
│   └── nginx/                 # Configuration Nginx (profil production)
│
└── docker-compose.yml
```

---

## Démarrage rapide avec Docker

C'est la méthode recommandée. Docker Compose orchestre les services principaux (base de données, MailPit, backend, frontend) et gère automatiquement les migrations et la génération des clés JWT au démarrage.

### Prérequis

- Docker Desktop installé et en cours d'exécution
- Git

### Installation

**1. Cloner le dépôt**

```bash
git clone <url-du-depot>
cd SmartCart
```

**2. Créer le fichier d'environnement backend**

```bash
cp backend/.env.example backend/.env
```

Renseigner les valeurs suivantes dans `backend/.env` :
- `APP_SECRET` — générer avec `openssl rand -hex 32`
- `JWT_SECRET` — générer avec `openssl rand -hex 32`
- `GOOGLE_CLIENT_ID` — depuis la Google Cloud Console
- `GROQ_API_KEY` — clé gratuite depuis https://console.groq.com/keys (chatbot + suggestion d'attributs IA)
- `MAILER_DSN` — connexion au serveur mail (MailPit en développement, voir ci-dessous ; pour un envoi réel via Gmail, voir [Configurer l'envoi d'emails](#configurer-lenvoi-demails))

**3. Créer le fichier d'environnement frontend**

```bash
cp frontend/.env.example frontend/.env
```

Renseigner `REACT_APP_GOOGLE_CLIENT_ID` avec le même Client ID Google.

**4. Lancer les conteneurs**

```bash
docker compose up -d
```

Au premier démarrage, Docker va :
- construire les images backend et frontend,
- initialiser la base de données PostgreSQL,
- installer les dépendances Composer,
- générer les clés RSA pour les tokens JWT,
- exécuter les migrations Doctrine.

**5. Vérifier que tout fonctionne**

```bash
docker compose ps
```

Les services `smartcart_postgres`, `smartcart_backend` et `smartcart_frontend` doivent afficher le statut `Up`.

### Accès aux services

| Service | URL |
|---|---|
| Frontend | http://localhost:3000 |
| API Backend | http://localhost:8000/api |
| Documentation Swagger | http://localhost:8000/api/docs |
| MailPit (emails de dev) | http://localhost:8025 |
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

## Démarrage hybride (backend + base de données en Docker, frontend en local)

Un compromis pratique en développement : la base de données et l'API tournent en conteneurs (pas de PHP/PostgreSQL à installer sur la machine), tandis que le frontend tourne directement avec `npm start` (rechargement à chaud plus rapide que le conteneur React).

**1 et 2.** Créer `backend/.env` et `frontend/.env` comme décrit dans la section [Démarrage rapide avec Docker](#démarrage-rapide-avec-docker) ci-dessus.

**3. Démarrer uniquement la base de données, MailPit et le backend**

```bash
docker compose up -d postgres mailer backend
```

Le service `frontend` du `docker-compose.yml` n'est volontairement pas démarré.

**4. Démarrer le frontend en local**

```bash
cd frontend
npm install
npm start
```

Le frontend local (`http://localhost:3000`) appelle l'API exposée par le conteneur backend sur `http://localhost:8000/api` exactement comme en tout-Docker — `REACT_APP_API_URL` n'a pas besoin de changer.

---

## Variables d'environnement

### Backend (`backend/.env`)

| Variable | Valeur par défaut | Description |
|---|---|---|
| `APP_ENV` | `dev` | Environnement Symfony (`dev` ou `prod`) |
| `APP_DEBUG` | `1` | Activation du mode debug |
| `APP_SECRET` | à définir | Clé secrète Symfony — générer avec `openssl rand -hex 32` |
| `DATABASE_URL` | voir ci-dessous | URL de connexion Doctrine |
| `JWT_SECRET` | à définir | Secret de signature JWT — générer avec `openssl rand -hex 32` |
| `JWT_EXPIRATION` | `3600` | Durée de validité du token JWT en secondes |
| `CORS_ALLOW_ORIGIN` | `http://localhost:3000` | Origines autorisées pour les requêtes cross-origin |
| `GOOGLE_CLIENT_ID` | à définir | Client ID OAuth 2.0 pour la connexion Google |
| `GROQ_API_KEY` | à définir | Clé API Groq — chatbot + suggestion d'attributs IA (gratuite sur https://console.groq.com/keys) |
| `GROQ_MODEL` | `llama-3.3-70b-versatile` | Modèle Groq utilisé |
| `MYMEMORY_EMAIL` | vide | Email optionnel pour la traduction du chatbot (MyMemory) — passe le quota de 1 000 à 10 000 mots/jour, aucune carte bancaire requise |
| `MAILER_DSN` | `smtp://mailer:1025` | Connexion au serveur mail (MailPit en dev — voir [Configurer l'envoi d'emails](#configurer-lenvoi-demails) pour un envoi réel) |
| `ADMIN_EMAIL` | `admin@smartcart.local` | Adresse expéditrice des emails envoyés par l'application |
| `FRONTEND_URL` | `http://localhost:3000` | Base des liens générés dans les emails (confirmation de compte, etc.) |

Exemple de `DATABASE_URL` en local (identifiants par défaut définis dans `docker-compose.yml`) :

```
postgresql://scu:scp@localhost:5436/scdb
```

En environnement Docker, le backend se connecte au service `postgres` sur le réseau interne :

```
postgresql://scu:scp@postgres:5432/scdb
```

Ces identifiants (`DB_USER`, `DB_PASSWORD`, `DB_NAME`) peuvent être surchargés via un fichier `.env` à la racine du dépôt, lu par `docker-compose.yml`.

### Frontend (`frontend/.env`)

| Variable | Valeur par défaut | Description |
|---|---|---|
| `REACT_APP_API_URL` | `http://localhost:8000/api` | URL de base de l'API backend |
| `REACT_APP_GOOGLE_CLIENT_ID` | à définir | Client ID Google OAuth (identique au backend) |
| `REACT_APP_JWT_STORAGE_KEY` | `smartcart_token` | Clé localStorage du token JWT |
| `REACT_APP_JWT_EXPIRATION_KEY` | `smartcart_token_exp` | Clé localStorage de l'expiration du token |
| `REACT_APP_REQUEST_TIMEOUT` | `30000` | Timeout des requêtes HTTP en ms |
| `REACT_APP_PAGINATION_LIMIT` | `20` | Nombre d'éléments par page |
| `REACT_APP_ENABLE_RECOMMENDATIONS` | `true` | Activation du moteur de recommandation |
| `REACT_APP_ENABLE_ANALYTICS` | `true` | Activation du tracking des interactions |
| `REACT_APP_ENVIRONMENT` | `development` | Environnement courant |
| `REACT_APP_DEBUG` | `true` | Mode debug frontend |

---

## Configurer l'envoi d'emails

Par défaut (`MAILER_DSN=smtp://mailer:1025`), tous les emails envoyés par l'application (confirmation de compte, confirmation/expédition/livraison de commande, promotions) sont capturés par **MailPit** et consultables sur http://localhost:8025 — rien n'est réellement envoyé. C'est le réglage recommandé en développement.

Pour un envoi réel via Gmail :

1. Activer la validation en deux étapes sur le compte Google : https://myaccount.google.com/security
2. Générer un mot de passe d'application : https://myaccount.google.com/apppasswords
3. Renseigner `MAILER_DSN` dans `backend/.env` **et** `.env` (racine du projet, lu par `docker-compose.yml`) :

   ```
   MAILER_DSN=smtp://votre.email%40gmail.com:motdepasseapplication@smtp.gmail.com:587
   ```

   - Encoder le `@` de l'adresse email en `%40`.
   - Retirer les espaces du mot de passe d'application affiché par Google (16 caractères collés).

4. Recréer le conteneur backend pour appliquer la variable : `docker compose up -d --force-recreate backend`
5. Vérifier avec : `docker exec smartcart_backend php bin/console mailer:test votre@email.com --from=admin@smartcart.local`

Les échecs d'envoi n'interrompent jamais une inscription ou une commande (`MailService` est toujours appelé dans un `try/catch` silencieux) — en cas de doute, ce test `mailer:test` est le moyen le plus direct de vérifier que les identifiants SMTP sont valides.

---

## Services Docker

Le fichier `docker-compose.yml` définit cinq services. Le service `nginx` n'est activé que pour le profil `production`.

| Service | Conteneur | Port exposé | Rôle |
|---|---|---|---|
| `postgres` | smartcart_postgres | 5436 (hôte) → 5432 (conteneur) | Base de données PostgreSQL |
| `backend` | smartcart_backend | 8000 | API Symfony (serveur PHP intégré) |
| `frontend` | smartcart_frontend | 3000 | Application React |
| `mailer` | smartcart_mailer | 8025 (UI), 1025 (SMTP) | MailPit — capture des emails en développement |
| `nginx` | smartcart_nginx | 80 / 443 | Reverse proxy (production uniquement) |

Pour accéder à la base de données depuis un outil externe comme DBeaver :

```
Hôte        : 127.0.0.1
Port        : 5436
Base        : scdb
Utilisateur : scu
Mot de passe: scp
```

(valeurs par défaut de `docker-compose.yml` — voir `DB_USER` / `DB_PASSWORD` / `DB_NAME` ci-dessus si surchargées)

Pour forcer la recréation d'un service après une modification de `docker-compose.yml` :

```bash
docker compose up -d --force-recreate backend
```

Pour réinitialiser complètement la base de données (supprime toutes les données) :

```bash
docker compose down
docker volume rm smartcart_postgres_data
docker compose up -d
```

---

## Fonctionnalités

### Authentification

Le module Auth expose six endpoints :

| Endpoint | Méthode | Description |
|---|---|---|
| `/api/auth/register` | POST | Création de compte (déclenche un email de confirmation) |
| `/api/auth/verify-email` | POST | Confirmation du compte via le token reçu par email |
| `/api/auth/resend-verification` | POST | Renvoi de l'email de confirmation |
| `/api/auth/login` | POST | Connexion email + mot de passe, retourne un JWT |
| `/api/auth/google-login` | POST | Connexion via Google OAuth 2.0 |
| `/api/auth/me` | GET | Informations de l'utilisateur connecté |
| `/api/auth/logout` | POST | Déconnexion (côté client — JWT stateless) |

Le flux d'inscription inclut une **vérification d'email obligatoire** : le compte est bloqué jusqu'à confirmation. La connexion Google créé automatiquement un compte si l'adresse n'existe pas encore.

### Catalogue produits

- Listage avec filtres (catégorie, marque, prix, attributs), tri et pagination.
- Recherche par texte + autocomplétion (`GET /api/products/autocomplete`).
- Schéma d'attributs **dynamique par type de produit** : chaque type (ex. "Smartphone", "T-shirt") définit ses propres attributs (ex. "Couleur", "Stockage") gérés via `ProductType` et `ProductTypeAttribute`.
- **Suggestion d'attributs par IA** (`POST /api/admin/product-types/suggest-attributes`) : à la création ou à l'édition d'un type, l'admin peut demander à l'IA (Groq/Llama 3.3) de proposer les attributs standards du marché pour ce type de produit. En édition, les attributs déjà définis sont exclus de la suggestion ; renommer le type puis re-suggérer remplace le lot précédent plutôt que de l'accumuler. Rien n'est persisté avant validation explicite de l'admin.
- Gestion des images produits avec upload vers `public/uploads/`.
- Génération automatique de slugs uniques.

### Marques

- Listage public des marques avec leurs produits associés.
- Gestion CRUD complète depuis le panneau admin.
- Upload de logo de marque.

### Catégories

- Structure **hiérarchique** (parent / enfant).
- Arbre de catégories exposé en lecture publique.
- Icônes par catégorie côté frontend.
- Gestion CRUD depuis le panneau admin.

### Panier

- Ajout, mise à jour de quantité et suppression d'articles.
- **Synchronisation du panier** entre session anonyme et compte connecté au login (`POST /api/cart/sync`).
- Contexte React dédié (`CartContext`) pour la mise à jour en temps réel du compteur panier.

### Commandes

- Passage de commande avec sélection d'adresse de livraison (`POST /api/orders/checkout`).
- Le numéro de téléphone de contact saisi à la commande est **mémorisé comme valeur par défaut** du profil pour la prochaine commande (mis à jour à chaque checkout, pas seulement à la première fois).
- Prix des articles **figés au moment de l'achat**.
- Application automatique des promotions actives.
- Historique des commandes avec détail des lignes.
- **Téléchargement de facture PDF** par commande (`GET /api/orders/{id}/pdf`) — généré dynamiquement avec DomPDF.
- Gestion du statut de commande depuis le panneau admin (en attente, expédiée, livrée, annulée).

### Promotions

- Création de promotions en **pourcentage ou en montant fixe**.
- Périmètre : produit spécifique, marque entière, ou ensemble de la boutique.
- Activation / désactivation et dates de validité.
- Les promotions actives sont appliquées automatiquement au panier et à la commande.
- Page dédiée `Promotions` côté frontend.

### Avis et notes

- Les utilisateurs peuvent déposer un avis et une note (0–100) sur les produits achetés.
- Un avis par utilisateur par produit.
- Consultation des avis sur la fiche produit.
- Gestion de ses propres avis depuis le profil.

### Favoris

- Ajout / suppression de produits en liste de souhaits.
- Contexte React dédié (`FavoriteContext`) pour la synchronisation de l'état en temps réel.
- Page `Favorites` dédiée.

### Profil utilisateur

- Mise à jour des informations personnelles (prénom, nom).
- Changement de mot de passe sécurisé.
- **Suppression de compte avec période de grâce** : le compte est marqué pour suppression, puis purgé automatiquement après un délai paramétrable.
- Tableau de bord personnel (`/api/profile/dashboard`) : résumé des commandes, favoris, avis.

### Adresses de livraison

- Gestion CRUD d'adresses enregistrées (plusieurs adresses par compte).
- **Sélection sur carte interactive** (Leaflet) via `AddressMapModal`.
- Champ de coordonnées géographiques stocké en base.

### Upload d'images

- Upload d'images produits et logos de marques via `FileUploadService`.
- Stockage dans `backend/public/uploads/` (exclu du dépôt git).
- Validation du type et de la taille côté backend.
- Composant React `ImageUpload` avec aperçu.

### Chatbot IA (Groq / Llama 3.3)

Un assistant conversationnel est intégré dans le frontend (bulle flottante `Chatbot.jsx`) et répond aux questions des visiteurs sur les produits, catégories et commandes.

- Endpoint public : `POST /api/chatbot/message` (header `X-Session-Id` requis) — accessible sans authentification.
- `ChatbotService` trouve les produits pertinents par mot-clé (avec repli sur une correspondance de catégorie), construit le prompt via `ShopAssistantPrompt` (`Service/Ai/Prompt/`), puis appelle `GroqClientService`.
- Les messages non écrits en anglais sont traduits au préalable par `TranslationService` (API MyMemory) afin de retrouver des produits dont le nom/catégorie est en anglais.
- Limite de débit : 12 messages / 60 secondes par session (`X-Session-Id`), au-delà réponse `429`.
- Le modèle et la clé API sont configurés via les variables d'environnement `GROQ_MODEL` et `GROQ_API_KEY`.
- Si la clé API est absente, le chatbot se désactive silencieusement (réponse de repli).
- Les échanges sont enregistrés dans `chat_message_log` pour analyse.

### Internationalisation (i18n)

L'interface frontend est entièrement disponible en **anglais et en français**.

- Basé sur `i18next` et `react-i18next`.
- Traductions organisées en 5 namespaces : `common`, `auth`, `product`, `order`, `admin`.
- Sélecteur de langue `LanguageSwitcher` dans la barre de navigation.
- La langue est persistée en localStorage.

### Panneau d'administration

Accessible uniquement aux comptes avec le rôle `ROLE_ADMIN` (routes sous `/admin`).

| Page | Description |
|---|---|
| Dashboard | KPIs globaux : chiffre d'affaires, commandes, utilisateurs actifs, produits |
| Produits | CRUD complet avec gestion des attributs dynamiques et images |
| Catégories | CRUD avec structure hiérarchique |
| Marques | CRUD avec upload de logo |
| Commandes | Consultation et mise à jour du statut |
| Promotions | Création et gestion des promotions |
| Types de produits | Gestion des types et de leurs attributs |
| Recommandations | Déclenchement manuel du recalcul (`POST /api/admin/recommendations/rebuild`) |

---

## Authentification JWT

Le flux d'authentification fonctionne de la façon suivante :

1. L'utilisateur envoie ses identifiants en POST sur `/api/auth/login`.
2. Le backend vérifie les identifiants et génère un token JWT signé avec la clé RSA privée.
3. Le token est retourné au client avec sa durée de validité (`expiresIn`).
4. Le frontend stocke le token en `localStorage` et l'inclut dans toutes les requêtes via `Authorization: Bearer <token>`.
5. Le backend valide la signature à chaque requête avant de traiter la demande.

Les tokens expirent au bout de 3600 secondes (1 heure). Il n'y a pas de mécanisme de refresh token — l'utilisateur doit se reconnecter à l'expiration.

Les endpoints publics (sans token) sont :

- `POST /api/auth/login`, `POST /api/auth/register`, `POST /api/auth/verify-email`, `POST /api/auth/resend-verification`, `POST /api/auth/google-login`
- `GET /api/products`, `GET /api/categories`, `GET /api/brands` (lecture catalogue)
- `GET /api/recommendations` (recommandations anonymes)
- `POST /api/guest/events` (tracking visiteurs anonymes)
- `POST /api/chatbot/message`
- `GET /api/docs`, `GET /api/docs.json`

Tous les autres endpoints sous `/api/` exigent un token valide. Les routes sous `/api/admin/` exigent en plus le rôle `ROLE_ADMIN`.

---

## Documentation API

La documentation interactive est générée automatiquement à partir des attributs PHP `#[OA\...]` via `zircote/swagger-php`.

Elle est accessible à l'adresse `http://localhost:8000/api/docs`. Pour tester les endpoints protégés, cliquez sur "Authorize" et collez votre token JWT obtenu après connexion.

Tous les endpoints sont documentés : Auth, Product, Category, Brand, Cart, Order, Profile, Admin, Recommendation, Chatbot.

---

## Moteur de recommandation

Le moteur combine trois approches, réparties dans `Controller/Recommendation/`, `Service/Recommendation/`, `Repository/` et `ML/` (voir l'arborescence ci-dessus) :

- **Filtrage collaboratif** (`CollaborativeFilteringService`) — "les utilisateurs comme vous ont aussi aimé...". Une factorisation matricielle (mini funk-SVD entraîné par descente de gradient stochastique, en PHP pur) apprend un vecteur de facteurs latents par utilisateur et par produit à partir de la matrice de goût (vues/paniers/achats/notes pondérés).
- **Recommandation par contenu** (`ContentRecommendationService` + `ContentSimilarityService`) — "similaire à ce que vous avez aimé", basé sur la catégorie, la marque, le type de produit et les valeurs d'attributs partagées. Les poids sont appris via régression logistique (`LogisticRegressionTrainer`) à partir des co-occurrences réelles.
- **Cold start** (`ColdStartRecommendationService`) — la liste servie à un visiteur sans aucun historique : mélange de tendances récentes et de scores saisonniers par catégorie (`SeasonalBoostService` + `AnalyzeSeasonalTrendsCommand`).

Pour un utilisateur connecté, `UserRecommendationBuilderService` mélange les deux premiers moteurs, applique des règles métier (exclusion des produits déjà achetés, boost promotion/nouveauté, diversité des catégories), avec repli sur les préférences déclarées puis sur le cold start. Pour un visiteur anonyme, `RecommendationBuilderService` exploite l'historique de session (`guest_event`).

Le calcul lourd tourne hors ligne (batch), jamais sur le chemin de la requête :

```bash
php bin/console app:rebuild-recommendations
```

ou via l'API : `POST /api/admin/recommendations/rebuild` (ROLE_ADMIN).

Servir une recommandation (`GET /api/recommendations`) n'est alors qu'une lecture indexée dans les tables précalculées.

---

## Commandes CLI

| Commande | Description |
|---|---|
| `app:rebuild-recommendations` | Recalcule toutes les recommandations (collaborative + contenu + cold start) |
| `app:analyze-seasonal-trends` | Analyse les tendances saisonnières par catégorie et met à jour `category_seasonal_score` |
| `app:export-features` | Exporte les features ML en CSV pour analyse externe |
| `app:prune-deleted-accounts` | Purge définitivement les comptes dont la période de grâce est écoulée |

---

## Structure de la base de données

**Identité**
- `user` — comptes utilisateurs. Rôles en JSON (`ROLE_USER`, `ROLE_ADMIN`), mot de passe haché, préférences de catégories/marques pour le cold-start, champ `deletion_requested_at` pour la suppression différée.

**Catalogue**
- `product` — catalogue avec stock, slug, images, attributs (JSON dépendant du `product_type`).
- `category` — hiérarchique (parent/enfant).
- `brand` — marques avec logo.
- `product_type` / `product_type_attribute` — schéma dynamique d'attributs par type de produit.

**Commerce**
- `order` / `order_item` — commandes et lignes de commande (prix figé à l'achat).
- `promotion` — promotion produit / marque / boutique, en pourcentage ou montant fixe.
- `address` — adresses de livraison avec coordonnées géographiques.
- `favorite` — liste de souhaits.
- `review` — avis et notes (0–100) par utilisateur et par produit.

**Signaux de recommandation**
- `interaction` — comportement des utilisateurs connectés (view, cart, purchase, rating).
- `guest_event` — comportement des visiteurs anonymes, rattaché à un `session_id`.

**Chatbot**
- `chat_message_log` — historique des échanges avec le chatbot IA.

**Moteur de recommandation**
- `product_relation`, `user_recommendation`, `cold_start_recommendation` — résultats précalculés du batch job.
- `category_seasonal_score` — scores saisonniers calculés par `AnalyzeSeasonalTrendsCommand`.

---

## Licence

MIT — projet réalisé dans le cadre d'un stage chez Sofiatech, juin 2026.
