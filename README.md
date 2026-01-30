# TurkPay - Virtual Banking & Payment Gateway Simulation

<div align="center">

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

**A comprehensive virtual banking system with integrated payment gateway functionality, designed for educational and demonstration purposes.**

[Features](#-key-features) • [Architecture](#-system-architecture) • [Installation](#-installation) • [API Documentation](#-api-documentation) • [Security](#-security-features)

</div>

---

## 📋 Abstract

TurkPay is a full-stack financial technology simulation platform that emulates core banking operations and merchant payment processing. The system implements industry-standard practices including double-entry bookkeeping, fraud detection mechanisms, and RESTful API architecture. This project serves as both an educational resource for understanding fintech systems and a demonstration of modern PHP application development.

---

## 🎯 Key Features

### Banking Module
- **Multi-Currency Wallet Management** - Support for TRY, USD, and EUR with real-time exchange rates
- **Peer-to-Peer Transfers** - Instant money transfers between users with transaction verification
- **Virtual Card Issuance** - Luhn-algorithm validated virtual Visa/Mastercard generation
- **Transaction History** - Comprehensive audit trail with filtering and pagination
- **Real-time Balance Updates** - Live balance synchronization using polling mechanism

### Payment Gateway
- **Merchant Integration API** - RESTful endpoints for payment processing
- **Secure Checkout Flow** - Token-based payment sessions with expiration
- **Webhook Notifications** - Automated payment status callbacks with retry logic
- **Sandbox Environment** - Separate testing mode for integration development

### Administrative Dashboard
- **User Management** - Account status control, role assignment, and activity monitoring
- **Transaction Approval System** - Manual review workflow for high-value transfers
- **Security Audit Logs** - Risk-level categorized activity logging
- **Real-time Analytics** - Transaction volume charts and system health metrics

---

## 🏗 System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        PRESENTATION LAYER                        │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐  │
│  │   Web Views  │  │  Admin Panel │  │   Merchant Portal    │  │
│  └──────────────┘  └──────────────┘  └──────────────────────┘  │
├─────────────────────────────────────────────────────────────────┤
│                        APPLICATION LAYER                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐  │
│  │    Router    │  │  Middleware  │  │      Services        │  │
│  │              │  │  (Auth/API)  │  │  (Exchange/Fraud)    │  │
│  └──────────────┘  └──────────────┘  └──────────────────────┘  │
├─────────────────────────────────────────────────────────────────┤
│                          DATA LAYER                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐  │
│  │    Models    │  │   Database   │  │    Repositories      │  │
│  │   (Active    │  │   (MySQL/    │  │                      │  │
│  │    Record)   │  │    PDO)      │  │                      │  │
│  └──────────────┘  └──────────────┘  └──────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### Design Patterns Implemented
- **MVC Architecture** - Separation of concerns between data, logic, and presentation
- **Active Record Pattern** - Object-relational mapping for database entities
- **Singleton Pattern** - Database connection management
- **Middleware Pattern** - Request/response pipeline for authentication and authorization
- **Double-Entry Bookkeeping** - Financial transaction integrity

---

## 💻 Technology Stack

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.0+ (Pure PHP, no framework) |
| Database | MySQL 8.0+ with InnoDB engine |
| Frontend | HTML5, CSS3 (Custom design system), JavaScript ES6+ |
| Authentication | Session-based with BCRYPT password hashing |
| API | RESTful with JSON responses |

---

## 📦 Installation

### Prerequisites
- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache/Nginx web server (or XAMPP/WAMP for local development)
- Composer (optional, for future dependencies)

### Setup Instructions

1. **Clone the Repository**
   ```bash
   git clone https://github.com/yourusername/turkpay.git
   cd turkpay
   ```

2. **Database Configuration**
   ```bash
   # Create database and import schema
   mysql -u root -p < database/schema.sql
   ```

3. **Environment Configuration**
   ```bash
   # Copy and configure environment file
   cp .env.example .env
   # Edit .env with your database credentials
   ```

4. **Web Server Configuration**
   - Point document root to `/public` directory
   - Ensure `mod_rewrite` is enabled for Apache
   - For XAMPP: Place project in `htdocs/banka`

5. **Access the Application**
   ```
   http://localhost/banka/public/
   ```

### Default Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@turkpay.com | password |
| Demo User | Register new account | - |

---

## 📚 API Documentation

### Authentication
All merchant API requests require authentication headers:
```
X-API-KEY: pk_live_xxxxx
X-API-SECRET: sk_live_xxxxx
```

### Endpoints

#### Initialize Payment
```http
POST /api/v1/payments/initiate
Content-Type: application/json

{
    "amount": 150.00,
    "currency": "TRY",
    "order_id": "ORDER-123",
    "callback_url": "https://yoursite.com/callback",
    "description": "Product Purchase"
}
```

#### Check Payment Status
```http
GET /api/v1/payments/status/{payment_token}
```

#### Process Refund
```http
POST /api/v1/payments/refund

{
    "transaction_id": "txn_xxxxx",
    "amount": 50.00,
    "reason": "Customer request"
}
```

---

## 🔒 Security Features

| Feature | Implementation |
|---------|----------------|
| Password Security | BCRYPT hashing with cost factor 12 |
| Session Management | Secure session handling with regeneration |
| CSRF Protection | Token-based form validation |
| SQL Injection Prevention | Prepared statements (PDO) |
| XSS Prevention | Output encoding with `htmlspecialchars()` |
| Fraud Detection | Velocity checks, amount limits, IP monitoring |
| API Rate Limiting | Request throttling per API key |
| Audit Logging | Comprehensive activity tracking with risk levels |

---

## 📁 Project Structure

```
turkpay/
├── api/                    # API endpoint handlers
│   └── v1/
│       ├── payments/
│       └── merchant/
├── database/
│   ├── schema.sql         # Database structure
│   └── create_admin.sql   # Admin user creation
├── public/                # Web root
│   ├── index.php          # Application entry point
│   └── assets/
│       └── css/
│           └── style.css  # Design system
├── src/
│   ├── Config/            # Configuration classes
│   ├── Core/              # Framework core (Router, Request, Response)
│   ├── Models/            # Data models (User, Wallet, Transaction, etc.)
│   └── Services/          # Business logic services
├── views/
│   ├── admin/             # Admin panel views
│   ├── auth/              # Authentication views
│   ├── layouts/           # Shared layouts
│   ├── merchant/          # Merchant portal views
│   └── user/              # User dashboard views
├── .env.example           # Environment template
└── README.md
```

---

## 🧪 Testing

### Demo Scenarios

1. **User Registration & Wallet Funding**
   - Register new account
   - Receive welcome bonus (demo)
   - View multi-currency wallets

2. **Peer-to-Peer Transfer**
   - Open two browser windows with different users
   - Initiate transfer from User A to User B
   - Observe real-time balance updates on both dashboards

3. **Merchant Integration**
   - Register as merchant
   - Obtain API credentials
   - Test payment flow in sandbox mode

---

## 📖 Academic Context

This project demonstrates practical implementation of several computer science and financial technology concepts:

- **Database Design**: Normalized relational schema with referential integrity
- **Financial Systems**: Double-entry bookkeeping, transaction atomicity
- **Security Engineering**: Defense-in-depth approach to application security
- **API Design**: RESTful principles, stateless communication
- **User Experience**: Modern glassmorphism UI design, responsive layouts

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 👤 Author

**[Your Name]**

- GitHub: [@yourusername](https://github.com/yourusername)
- LinkedIn: [Your LinkedIn](https://linkedin.com/in/yourprofile)

---

## 🙏 Acknowledgments

- Modern UI design inspired by contemporary fintech applications
- Security patterns based on OWASP guidelines
- Financial transaction patterns following industry standards

---

<div align="center">

**⭐ If you find this project useful, please consider giving it a star! ⭐**

</div>
