# SmartCart - E-Commerce Platform with AI Recommendations

**SmartCart** is a modern e-commerce platform equipped with an intelligent product recommendation engine capable of analyzing purchase behaviors and user preferences to provide real-time suggestions.

## 🎯 Project Overview

SmartCart is designed to solve the challenge of modern e-commerce by providing:
- **Intelligent Recommendations**: Real-time product suggestions based on user behavior and preferences
- **Modern Architecture**: Microservices-ready design with clear separation of concerns
- **Scalability**: Docker-based deployment for easy horizontal scaling
- **Security**: JWT-based authentication and authorization

## 📋 Technology Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | Symfony 6.x (PHP) |
| **Frontend** | React 18.x (JavaScript/TypeScript) |
| **Database** | PostgreSQL 15 |
| **Authentication** | JWT (JSON Web Tokens) |
| **Containerization** | Docker & Docker Compose |
| **Deployment** | GitHub Actions (CI/CD) |

## 🏗️ Project Structure

```
smartcart/
├── backend/                 # Symfony API Application
│   ├── src/
│   │   ├── Controller/      # API endpoints
│   │   ├── Entity/          # Database models
│   │   ├── Repository/      # Data access layer
│   │   ├── Service/         # Business logic
│   │   ├── Recommendation/  # AI recommendation engine
│   │   ├── Security/        # JWT & authentication
│   │   └── DTO/             # Data transfer objects
│   ├── config/              # Symfony configuration
│   ├── migrations/          # Database migrations
│   └── tests/               # Unit & integration tests
│
├── frontend/                # React Application
│   ├── src/
│   │   ├── components/      # Reusable React components
│   │   ├── pages/           # Page components
│   │   ├── services/        # API communication
│   │   ├── hooks/           # Custom React hooks
│   │   ├── context/         # Context API state
│   │   ├── styles/          # CSS/styled components
│   │   └── utils/           # Helper functions
│   └── public/              # Static assets
│
├── docker/                  # Docker configurations
│   ├── nginx/               # Nginx reverse proxy config
│   └── php/                 # PHP-FPM configuration
│
├── database/                # Database scripts
│   ├── migrations/          # Database migrations
│   └── seeds/               # Seed data
│
├── docs/                    # Project documentation
│
├── .github/
│   └── workflows/           # CI/CD pipelines
│
└── docker-compose.yml       # Docker Compose orchestration
```

## 🚀 Quick Start

### Prerequisites
- Docker & Docker Compose installed
- Git configured

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/smartcart.git
   cd smartcart
   ```

2. **Setup environment variables**
   ```bash
   cp backend/.env.example backend/.env
   cp frontend/.env.example frontend/.env
   ```

3. **Start Docker containers**
   ```bash
   docker-compose up -d
   ```

4. **Initialize the database**
   ```bash
   docker-compose exec backend php bin/console doctrine:migrations:migrate
   docker-compose exec backend php bin/console doctrine:fixtures:load
   ```

### Access the application
- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000/api
- **API Documentation**: http://localhost:8000/api/doc

## 📚 Architecture Details

### Backend Architecture
- **MVC Pattern**: Clear separation between controllers, services, and repositories
- **JWT Authentication**: Secure token-based authentication
- **API-First Design**: RESTful API with clear endpoints
- **Recommendation Engine**: Intelligent algorithm for product suggestions

### Frontend Architecture
- **Component-Based**: Modular React components
- **Context API**: State management for authentication and user data
- **Service Layer**: Centralized API communication
- **Custom Hooks**: Reusable logic across components

## 🔑 Key Features

### E-Commerce Core
- Product catalog management
- Shopping cart functionality
- Order processing
- Payment integration ready

### Recommendation Engine
- Collaborative filtering
- Content-based recommendations
- Real-time behavior analysis
- Personalized product suggestions

### Security
- JWT token-based authentication
- Password hashing
- CORS configuration
- Input validation and sanitization

## 🔒 JWT Authentication Flow

1. User logs in with credentials
2. Backend validates and returns JWT token
3. Frontend stores token in secure storage
4. All subsequent requests include token in Authorization header
5. Backend validates token before processing requests

## 🗄️ Database Schema

Key entities:
- **Users**: User accounts with authentication info
- **Products**: Product catalog
- **Orders**: Customer orders
- **OrderItems**: Items in orders
- **Reviews**: Product reviews and ratings
- **UserBehavior**: Tracks user interactions for recommendations
- **Recommendations**: Cached recommendation results

## 🐳 Docker Services

| Service | Port | Purpose |
|---------|------|---------|
| PostgreSQL | 5432 | Database |
| Backend (Symfony) | 8000 | API Server |
| Frontend (React) | 3000 | Web Application |
| Nginx | 80/443 | Reverse Proxy |

## 📖 Documentation

- [Backend Setup Guide](docs/BACKEND_SETUP.md)
- [Frontend Setup Guide](docs/FRONTEND_SETUP.md)
- [Database Schema](docs/DATABASE_SCHEMA.md)
- [API Documentation](docs/API_DOCS.md)
- [Recommendation Algorithm](docs/RECOMMENDATION_ENGINE.md)
- [Deployment Guide](docs/DEPLOYMENT.md)

## 🛠️ Development

### Backend Development
```bash
cd backend
composer install
php bin/console server:run
```

### Frontend Development
```bash
cd frontend
npm install
npm start
```

### Run Tests
```bash
# Backend tests
docker-compose exec backend php bin/phpunit

# Frontend tests
docker-compose exec frontend npm test
```

## 🚢 Deployment

The project includes GitHub Actions workflows for:
- Automated testing
- Building Docker images
- Pushing to container registry
- Deploying to production

See [Deployment Guide](docs/DEPLOYMENT.md) for detailed instructions.

## 📝 Environment Variables

See `.env.example` files in backend and frontend directories for all available configuration options.

## 🤝 Contributing

1. Create a feature branch
2. Commit your changes
3. Push to the branch
4. Open a Pull Request

## 📄 License

This project is licensed under the MIT License.

## 👥 Team

- **Project**: SmartCart E-Commerce Platform
- **Started**: June 2026

## 📞 Support

For issues, questions, or suggestions, please open an issue on GitHub.
