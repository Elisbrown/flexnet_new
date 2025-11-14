# FlexNet - GPS Household Management System

## 🎯 Overview

FlexNet is a complete GPS-based household management system built with Node.js/Express backend and interactive HTML/CSS/JavaScript frontend. The system manages locations, households, subscriptions, payments, and administrative operations.

## ✨ Features

### Admin Features
- **Dashboard**: Overview of locations, households, and system metrics
- **Location Management**: Create, read, update, delete locations
- **Household Management**: Manage households within locations
- **Admin Management**: Create and manage admin users with role-based access
- **Payment Management**: View and track all payments
- **FAQs & Support**: Manage frequently asked questions and support tickets
- **System Logs**: Track all administrative actions
- **User Management**: View and manage end users

### User Features
- **Login**: Secure phone/PIN authentication
- **Dashboard**: Personal subscription and billing overview
- **Profile Management**: Update personal information
- **Subscriptions**: View active subscriptions
- **Billing**: View payment history
- **Settings**: Manage user preferences
- **PIN Management**: Change PIN for security
- **Onboarding**: First-time setup wizard

### Payment Integration
- **Fapshi Payment SDK**: Integrated payment processing
- **Payment Initiation**: Users can initiate payments
- **Payment Verification**: Check payment status
- **Webhook Handling**: Receive payment callbacks
- **Transaction History**: Complete payment records

## 🏗️ Architecture

### Tech Stack
- **Frontend**: HTML5, CSS3, Bootstrap 5.3.3, Vanilla JavaScript
- **Backend**: Node.js, Express.js
- **Database**: MySQL
- **Authentication**: JWT (JSON Web Tokens)
- **Payment**: Fapshi SDK
- **HTTP Client**: Axios

### Project Structure
```
GPSSS/
├── server.js                 # Main Express server & API
├── package.json              # Dependencies
├── .env                       # Environment variables
├── index.html                # Home page
├── admin/                     # Admin application
│   ├── login.html            # Admin login
│   ├── dashboard.html        # Admin dashboard
│   ├── locations.html        # Location management
│   ├── households.html       # Household management
│   ├── payments.html         # Payment management
│   ├── admins.html           # Admin user management
│   ├── faqs.html             # FAQ management
│   ├── support.html          # Support ticket management
│   ├── logs.html             # System logs
│   ├── profile.html          # Admin profile
│   └── ... (other pages)
├── user/                      # User application
│   ├── login.html            # User login
│   ├── dashboard.html        # User dashboard
│   ├── subscriptions.html    # Subscription management
│   ├── billing.html          # Billing information
│   ├── settings.html         # User settings
│   ├── change-pin.html       # PIN change
│   ├── onboarding.html       # Onboarding wizard
│   ├── profile.html          # User profile
│   ├── favicon/              # Icons and favicon
│   ├── SDKs-main/            # Fapshi SDK files
│   └── ... (other pages)
└── backup/                    # PHP files (backup)
```

## 🚀 Getting Started

### Prerequisites
- Node.js 16+
- MySQL 5.7+
- npm or yarn

### Installation

1. **Clone the repository**
```bash
cd /Users/maitre/Downloads/Documents/GPSSS
```

2. **Install dependencies**
```bash
npm install
```

3. **Configure environment variables**
Create/edit `.env` file with:
```env
# Database
DB_HOST=localhost
DB_USER=root
DB_PASS=mysql
DB_NAME=flexnet
DB_PORT=3306

# Server
PORT=3000
NODE_ENV=development
APP_URL=http://localhost:3000

# JWT
JWT_SECRET=your_jwt_secret_change_in_production
JWT_ADMIN_EXPIRY=1h
JWT_USER_EXPIRY=180d

# Fapshi Payment SDK
FAPSHI_API_USER=your_api_user
FAPSHI_API_KEY=your_api_key
FAPSHI_MIN_AMOUNT=100

# App
APP_NAME=FlexNet
VERSION=1.0.0
LOG_LEVEL=info
```

4. **Start the server**
```bash
npm start
```

Server runs on `http://localhost:3000`

## 🔌 API Endpoints

### Health Check
- `GET /api/health` - Server health status

### Admin Authentication
- `POST /api/admin/login` - Admin login
- `POST /api/admin/logout` - Admin logout
- `POST /api/setup/create-admin` - Create first admin (setup only)

### User Authentication
- `POST /api/user/login` - User login
- `POST /api/user/logout` - User logout
- `POST /api/user/change-pin` - Change user PIN

### Locations (Admin Only)
- `GET /api/admin/locations` - List all locations
- `GET /api/admin/locations/:id` - Get single location
- `POST /api/admin/locations` - Create location
- `PUT /api/admin/locations/:id` - Update location
- `DELETE /api/admin/locations/:id` - Delete location

### Households (Admin Only)
- `GET /api/admin/households` - List all households
- `GET /api/admin/households/:id` - Get single household
- `POST /api/admin/households` - Create household
- `PUT /api/admin/households/:id` - Update household
- `DELETE /api/admin/households/:id` - Delete household

### Payments (Admin Only)
- `GET /api/admin/payments` - List all payments
- `GET /api/admin/payments/:id` - Get single payment
- `POST /api/payments/initiate` - Initiate payment (User)
- `POST /api/payments/webhook` - Payment webhook callback
- `GET /api/payments/status/:transactionId` - Check payment status (User)

### Admin Users (Admin Only)
- `GET /api/admin/admins` - List all admins
- `POST /api/admin/admins` - Create new admin
- `PUT /api/admin/admins/:id` - Update admin
- `DELETE /api/admin/admins/:id` - Delete admin

### User Profile (User Only)
- `GET /api/user/profile` - Get user profile
- `PUT /api/user/profile` - Update user profile
- `GET /api/user/subscriptions` - Get user subscriptions
- `GET /api/user/billing` - Get user billing history

### Logs (Admin Only)
- `GET /api/admin/logs` - Get system logs

## 🔐 Authentication

### Admin Authentication
```javascript
// Login with email and password
POST /api/admin/login
{
  "email": "admin@flexnet.local",
  "password": "Admin123!"
}

// Response
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "admin": {
      "id": 2,
      "name": "Admin User",
      "email": "admin@flexnet.local",
      "roles": []
    }
  }
}
```

### User Authentication
```javascript
// Login with phone and PIN
POST /api/user/login
{
  "phone_number": "+237679690703",
  "pin": "1234"
}

// Response
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": {
      "id": 1,
      "name": "Test User",
      "phone": "+237679690703",
      "household_id": 1,
      "requires_pin_change": false
    }
  }
}
```

### Using Tokens
Include token in Authorization header:
```javascript
Authorization: Bearer {token}
```

## 📊 Database Schema

### Key Tables
- **admins**: Admin user accounts with hashed passwords
- **users**: End user accounts with phone/PIN
- **locations**: GPS locations/complexes
- **households**: Apartments/units within locations
- **subscriptions**: User subscription records
- **payments**: Payment transaction history
- **roles**: Admin role definitions
- **admin_roles**: Role assignments for admins
- **system_logs**: Audit trail of all actions
- **support_tickets**: Support requests from users
- **faqs**: Frequently asked questions

## 📱 Frontend Pages

### Admin Application (`/admin/`)
- **login.html** - Admin login page
- **dashboard.html** - Admin dashboard
- **locations.html** - Location management
- **location-households.html** - Households in a location
- **household-detail.html** - Household details
- **payments.html** - Payment management
- **admins.html** - Admin user management
- **faqs.html** - FAQ management
- **support.html** - Support ticket management
- **logs.html** - System activity logs
- **profile.html** - Admin profile

### User Application (`/user/`)
- **login.html** - User login page
- **onboarding.html** - First-time setup
- **dashboard.html** - User dashboard
- **subscriptions.html** - Subscription details
- **billing.html** - Billing history
- **settings.html** - User settings
- **change-pin.html** - PIN change
- **profile.html** - User profile
- **pin-change-success.html** - PIN change confirmation

## 🧪 Testing the API

### Test Admin Login
```bash
curl -X POST http://localhost:3000/api/admin/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@flexnet.local","password":"Admin123!"}'
```

### Test User Login
```bash
curl -X POST http://localhost:3000/api/user/login \
  -H "Content-Type: application/json" \
  -d '{"phone_number":"+237679690703","pin":"1234"}'
```

### Test Protected Route
```bash
curl -X GET http://localhost:3000/api/admin/locations \
  -H "Authorization: Bearer {your_admin_token}"
```

## 🛠️ Development

### Running in Development Mode
```bash
npm run dev
```

Uses `node --watch` for hot-reloading

### Build Process
No build needed - Express serves files directly

## 📝 Logging

All administrative actions are logged to `system_logs` table:
- Login/Logout events
- CRUD operations on locations, households, admins
- Payment transactions
- User actions

## 🔒 Security Features

- **Password Hashing**: bcryptjs for secure password storage
- **JWT Tokens**: Secure token-based authentication
- **Role-Based Access**: Different permissions for admins and users
- **Input Validation**: All inputs validated before processing
- **SQL Injection Prevention**: Parameterized queries
- **CORS**: Configured to prevent unauthorized cross-origin requests
- **Body Size Limits**: 10MB limit to prevent abuse

## 🚨 Error Handling

All API responses follow standard format:
```javascript
// Success
{
  "success": true,
  "message": "Operation successful",
  "data": { /* response data */ }
}

// Error
{
  "success": false,
  "message": "Error description",
  "statusCode": 400
}
```

## 📚 API Request Examples

### Create Location
```bash
curl -X POST http://localhost:3000/api/admin/locations \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "New Complex",
    "code": "NC-001",
    "city": "Yaounde",
    "region": "Centre",
    "is_active": true
  }'
```

### Create Household
```bash
curl -X POST http://localhost:3000/api/admin/households \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "location_id": 1,
    "apartment_label": "Apt 101",
    "primary_full_name": "John Doe",
    "phone_msisdn": "+237123456789",
    "email": "john@example.com"
  }'
```

### Initiate Payment
```bash
curl -X POST http://localhost:3000/api/payments/initiate \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "phone_number": "+237123456789",
    "amount": 5000,
    "payment_method": "mtn"
  }'
```

## 🔄 Workflow Examples

### Admin User Workflow
1. Admin logs in → Gets JWT token
2. Dashboard loads location and household data
3. Admin creates/updates/deletes locations and households
4. All actions logged to system_logs
5. Admin can view payment history

### User Workflow
1. User logs in with phone/PIN → Gets JWT token (6-month expiry)
2. If first login, redirected to PIN change page
3. Dashboard shows subscription and billing info
4. User can initiate payment via Fapshi
5. Payment status updates in real-time

## 🐛 Troubleshooting

### Server won't start
- Check `.env` file is created with correct values
- Verify MySQL is running and accessible
- Check if port 3000 is available

### Login fails
- Verify correct credentials in `.env`
- Check MySQL database is accessible
- Review server logs for error details

### API returns 401 (Unauthorized)
- Verify JWT token is valid and not expired
- Check Authorization header format: `Bearer {token}`
- Re-login if token has expired

### Payment fails
- Verify Fapshi API credentials in `.env`
- Check internet connection
- Review payment error message for Fapshi-specific issues

## 📞 Support

For issues or questions:
1. Check server logs
2. Review error messages in browser console
3. Verify database connectivity
4. Check `.env` configuration

## 📄 License

This project is part of the FlexNet GPS Household Management System.

## ✅ Version

**Current Version**: 1.0.0  
**Last Updated**: November 12, 2025
