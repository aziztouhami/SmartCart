# SmartCart Architecture Documentation

## System Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    Client Layer                              │
├─────────────────────────────────────────────────────────────┤
│  React Frontend (Port 3000)                                  │
│  - Components, Pages, Services                               │
│  - JWT Token Storage                                         │
│  - Context API State Management                              │
└────────────────────┬────────────────────────────────────────┘
                     │ HTTPS/HTTP
┌────────────────────▼────────────────────────────────────────┐
│              API Gateway / Reverse Proxy                     │
├─────────────────────────────────────────────────────────────┤
│  Nginx (Port 80/443)                                         │
│  - CORS Configuration                                        │
│  - SSL/TLS Termination                                       │
│  - Request Routing                                           │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│              Application Layer                               │
├─────────────────────────────────────────────────────────────┤
│  Symfony Backend API (Port 8000)                             │
│  ├─ API Routes & Controllers                                │
│  ├─ JWT Authentication & Authorization                       │
│  ├─ Service Layer (Business Logic)                          │
│  ├─ Recommendation Engine                                    │
│  └─ Repository Pattern (Data Access)                        │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│              Data Layer                                      │
├─────────────────────────────────────────────────────────────┤
│  PostgreSQL (Port 5432)                                      │
│  - User Management                                           │
│  - Products & Categories                                     │
│  - Orders & Transactions                                     │
│  - User Behavior Tracking                                    │
│  - Recommendation Cache                                      │
└─────────────────────────────────────────────────────────────┘
```

## Backend Architecture (Symfony)

### Request Flow

```
HTTP Request
    ↓
Middleware (CORS, JWT Validation)
    ↓
Router (Route Matching)
    ↓
Controller (Request Handling)
    ↓
Service Layer (Business Logic)
    ↓
Repository (Data Access)
    ↓
Doctrine ORM (Database Query)
    ↓
PostgreSQL Database
    ↓
Response (JSON with JWT Token)
```

### Layered Architecture

```
┌─────────────────────────────────────┐
│   Controllers                       │  API Endpoints
│   (Request/Response Handling)       │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│   Services                          │  Business Logic
│   (Recommendation, Cart, Order)     │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│   Repositories                      │  Data Access
│   (Doctrine ORM)                    │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│   Entities                          │  Domain Models
│   (User, Product, Order, etc.)      │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│   PostgreSQL Database               │  Persistence
└─────────────────────────────────────┘
```

## Frontend Architecture (React)

### Component Hierarchy

```
App
├─ Layout
│  ├─ Header (Navigation, User Menu)
│  ├─ Sidebar (Categories, Filters)
│  └─ Footer
├─ Pages
│  ├─ Home (Featured Products, Recommendations)
│  ├─ ProductDetail (Product Info, Reviews)
│  ├─ Cart (Shopping Cart Items)
│  ├─ Checkout (Order Processing)
│  ├─ Account (User Profile)
│  └─ Login (Authentication)
└─ Context
   ├─ AuthContext (User, Token, Login/Logout)
   ├─ CartContext (Items, Totals)
   └─ RecommendationContext (Suggestions)
```

### State Management

```
Context API
├─ AuthContext
│  └─ User state, JWT token, login/logout
├─ CartContext
│  └─ Cart items, quantities, totals
└─ RecommendationContext
   └─ Personalized product suggestions
```

## Database Schema

### Core Entities

#### Users
```sql
users (id, email, password_hash, first_name, last_name, 
       created_at, updated_at)
```

#### Products
```sql
products (id, name, description, price, category_id,
          stock_quantity, created_at, updated_at)
categories (id, name, description)
```

#### Orders & Cart
```sql
orders (id, user_id, status, total_amount, 
        created_at, updated_at)
order_items (id, order_id, product_id, quantity, price)
cart_items (id, user_id, product_id, quantity)
```

#### Recommendations & Behavior
```sql
user_behavior (id, user_id, product_id, action_type, 
               timestamp)
recommendations (id, user_id, product_id, score, 
                 created_at, expires_at)
```

#### Reviews & Ratings
```sql
reviews (id, user_id, product_id, rating, comment, 
         created_at, updated_at)
```

## Recommendation Engine

### Algorithm Flow

```
User Action (View, Add to Cart, Purchase)
    ↓
Store in UserBehavior Table
    ↓
Trigger Recommendation Engine
    ↓
┌─────────────────────────────────────┐
│ Collaborative Filtering             │
│ - Find similar users                │
│ - Products they liked               │
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│ Content-Based Filtering             │
│ - Product attributes                │
│ - User preferences                  │
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│ Score & Rank Results                │
│ - Combine algorithms                │
│ - Filter duplicates                 │
│ - Sort by relevance                 │
└─────────────────────────────────────┘
    ↓
Cache Results in DB
    ↓
Return to Frontend
```

## JWT Authentication Flow

```
1. User Login
   └─> POST /api/auth/login
       {email, password}

2. Backend Validates
   └─> Generate JWT Token
       Header: {alg: "HS256", typ: "JWT"}
       Payload: {user_id, email, exp}
       Signature: Hash(Header + Payload + Secret)

3. Return Token to Frontend
   └─> Frontend stores in localStorage/sessionStorage

4. Subsequent Requests
   └─> Authorization: Bearer {token}
   └─> Backend validates token signature
   └─> Extract user_id from payload
   └─> Process request

5. Token Expiration
   └─> Generate new token with refresh token
   └─> Or re-login
```

## API Endpoints Structure

```
/api/v1/
├─ /auth
│  ├─ POST   /login
│  ├─ POST   /logout
│  └─ POST   /refresh
├─ /products
│  ├─ GET    /
│  ├─ GET    /{id}
│  ├─ POST   / (admin)
│  └─ PUT    /{id} (admin)
├─ /categories
│  ├─ GET    /
│  └─ GET    /{id}
├─ /orders
│  ├─ GET    /
│  ├─ POST   /
│  └─ GET    /{id}
├─ /cart
│  ├─ GET    /
│  ├─ POST   /items
│  └─ DELETE /items/{id}
├─ /recommendations
│  ├─ GET    /for-user
│  └─ GET    /similar/{product_id}
├─ /reviews
│  ├─ GET    /product/{product_id}
│  └─ POST   /
└─ /users
   ├─ GET    /profile
   └─ PUT    /profile
```

## Docker Architecture

### Service Isolation

```
Docker Network: smartcart_network

postgres (Database)
  ├─ Volume: postgres_data
  └─ Port: 5432 (internal only)

backend (Symfony API)
  ├─ Volume: /app (code)
  ├─ Port: 8000
  └─ Depends on: postgres

frontend (React)
  ├─ Volume: /app (code)
  ├─ Port: 3000
  └─ Depends on: backend

nginx (Reverse Proxy)
  ├─ Port: 80/443
  └─ Depends on: backend, frontend
```

## Deployment Architecture (CI/CD)

```
GitHub Repository Push
    ↓
GitHub Actions Trigger
    ↓
┌─────────────────────────────────────┐
│ Continuous Integration (CI)         │
├─────────────────────────────────────┤
│ ✓ Run tests (Backend & Frontend)    │
│ ✓ Code quality checks               │
│ ✓ Build Docker images               │
│ ✓ Push to registry                  │
└────────┬────────────────────────────┘
         ↓
┌─────────────────────────────────────┐
│ Continuous Deployment (CD)          │
├─────────────────────────────────────┤
│ ✓ Deploy to production              │
│ ✓ Run migrations                    │
│ ✓ Health checks                     │
│ ✓ Monitor logs                      │
└─────────────────────────────────────┘
```

## Security Considerations

1. **JWT**: Token-based stateless authentication
2. **CORS**: Configure allowed origins
3. **HTTPS**: Enable in production
4. **Database**: Parameterized queries (Doctrine ORM)
5. **Input Validation**: Server-side validation
6. **Rate Limiting**: Prevent API abuse
7. **Secrets Management**: Environment variables
8. **Password Hashing**: Bcrypt with salt

## Performance Optimization

1. **Caching**: Redis for frequent queries
2. **Pagination**: Limit result sets
3. **Database Indexing**: On frequently queried columns
4. **Lazy Loading**: Components load on demand
5. **Code Splitting**: React bundle optimization
6. **CDN**: Static asset delivery
7. **Database Connection Pooling**: Reuse connections

## Scalability Strategy

1. **Horizontal Scaling**: Multiple backend instances
2. **Load Balancing**: Distribute requests
3. **Message Queue**: Async tasks (recommendations)
4. **Database Replication**: Master-slave setup
5. **Microservices**: Separate recommendation service
6. **Containerization**: Docker for easy deployment

## Monitoring & Logging

- **Application Logs**: Symfony monolog, React console
- **Database Logs**: PostgreSQL logs
- **Docker Logs**: docker-compose logs
- **Performance**: Monitor response times
- **Errors**: Track and alert on exceptions
- **User Analytics**: Track user behavior for recommendations
