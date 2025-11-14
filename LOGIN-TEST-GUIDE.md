# FlexNet User Login - Test Credentials & Setup

## ✅ Test User Created Successfully

### Credentials
- **Phone Number:** `679690703`
- **Initial PIN:** `1234`
- **Status:** Ready to login

### Session Management

#### localStorage Keys
- `user_token`: JWT token (180-day expiry for users)
- `user_data`: User profile object (id, name, phone, household_id, requires_pin_change)

#### JWT Token Details
- **Expiry:** 180 days
- **Payload:** { id, phone, household_id, type: 'user' }
- **Headers:** Authorization: Bearer {token}

### Login Flow

1. **Enter Credentials**
   - Phone: 679690703
   - PIN: 1234

2. **On First Login (requires_pin_change=true)**
   - Redirects to `/user/change-pin.html`
   - Must set new PIN (4-6 digits)
   - Sets flag: `has_changed_default_pin = 1`

3. **After PIN Change or Subsequent Logins**
   - Redirects to `/user/dashboard.html`
   - Session persists across all user pages
   - Auth check automatically redirects unauthenticated users to login

### Session Persistence

#### Auto-Protected Pages
All user pages automatically check authentication on load:
- `/user/dashboard.html`
- `/user/billing.html`
- `/user/subscriptions.html`
- `/user/settings.html`
- `/user/change-pin.html`
- `/user/pin-change-success.html`

#### Public Pages (No Auth Check)
- `/user/login.html`
- `/user/onboarding.html`

#### Session Storage Method
- **Token Storage:** `localStorage.setItem('user_token', token)`
- **User Data Storage:** `localStorage.setItem('user_data', JSON.stringify(user))`
- **Token Retrieval:** `api.isAuthenticated()` checks for token existence
- **Session Validation:** Automatic redirect on page load if token missing

### Database Schema

```sql
-- Users Table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    phone_number VARCHAR(20) UNIQUE NOT NULL,
    pin VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    household_id INT,
    has_changed_default_pin BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Test user created
INSERT INTO users (phone_number, pin, full_name, household_id, has_changed_default_pin) 
VALUES ('679390703', '1234', 'Test User', 1, 0);
```

### API Endpoints

#### User Login
```
POST /api/user/login
Content-Type: application/json

{
  "phone_number": "679690703",
  "pin": "1234"
}

Response:
{
  "success": true,
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "user": {
      "id": 1,
      "name": "Test User",
      "phone": "679690703",
      "household_id": 1,
      "requires_pin_change": true
    }
  }
}
```

#### Change PIN (Protected - Requires Token)
```
POST /api/user/change-pin
Authorization: Bearer {token}
Content-Type: application/json

{
  "current_pin": "1234",
  "new_pin": "5678"
}
```

#### Get User Profile (Protected)
```
GET /api/user/profile
Authorization: Bearer {token}
```

#### User Logout (Protected)
```
POST /api/user/logout
Authorization: Bearer {token}
```

### Testing Steps

1. **Open browser:** http://localhost:3000/user/login.html
2. **Enter credentials:**
   - Phone: 679690703
   - PIN: 1234
3. **First login:** Will redirect to PIN change page
4. **Change PIN:** Set a new PIN (4-6 digits)
5. **Confirm:** Will redirect to dashboard with session active
6. **Test Navigation:** Click nav buttons to test session persistence
7. **Logout:** Red X button in dashboard header clears session
8. **Try access:** Go to dashboard → redirects to login (session cleared)

### Session Verification

#### Check localStorage in DevTools Console
```javascript
// View token
localStorage.getItem('user_token')

// View user data
JSON.parse(localStorage.getItem('user_data'))

// Check authentication
api.isAuthenticated()

// Get user data programmatically
api.getUserData()
```

### Troubleshooting

**Problem:** "Invalid credentials" on login
- **Solution:** Verify test user exists: `SELECT * FROM users WHERE phone_number='679690703';`

**Problem:** Session lost after page refresh
- **Solution:** Tokens are persisted in localStorage. Check if localStorage is enabled.
- **Clear session:** `localStorage.clear()` then refresh

**Problem:** Auth check redirects to login immediately
- **Solution:** Token might be expired (180 days). Re-run seed script to create new test user.

**Problem:** CORS errors
- **Solution:** Verify server running on http://localhost:3000
- Check `baseURL` in api-client.js matches server address

### Backend Verification

```bash
# Seed test user
node seed-test-user.js

# Check database
mysql -u root -pmysql flexnet -e "SELECT id, phone_number, full_name, has_changed_default_pin FROM users LIMIT 5;"

# Check server health
curl http://localhost:3000/api/health

# Test login endpoint
curl -X POST http://localhost:3000/api/user/login \
  -H "Content-Type: application/json" \
  -d '{"phone_number":"679690703","pin":"1234"}'
```

### Files Modified

1. **seed-test-user.js** - Creates test user in database
2. **user/login.html** - Stores token as `user_token` (consistent naming)
3. **user/api-client.js** - Reads token from `user_token`, auto-checks auth on protected pages
4. **user/dashboard.html** - Uses global `api` client with logout handler
5. **user/billing.html** - Uses global `api` client with nav routing
6. **user/subscriptions.html** - Uses global `api` client with nav routing
7. **user/settings.html** - Uses global `api` client with nav routing and logout
8. **user/change-pin.html** - Uses global `api` client with auth check
9. **.gitignore** - Added (already created earlier)

