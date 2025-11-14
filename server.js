/**
 * FlexNet Express API Server
 * Complete Node.js/Express backend for GPS Household Management System
 */

import express from 'express';
import cors from 'cors';
import bodyParser from 'body-parser';
import mysql from 'mysql2/promise';
import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';
import axios from 'axios';
import dotenv from 'dotenv';
import path from 'path';
import { fileURLToPath } from 'url';

dotenv.config();

const app = express();
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// MIDDLEWARE
app.use(cors({ origin: process.env.APP_URL || 'http://localhost:3000', credentials: true }));
app.use(bodyParser.json({ limit: '10mb' }));
app.use(bodyParser.urlencoded({ limit: '10mb', extended: true }));

// Static files - NEW STRUCTURE
app.use(express.static(__dirname));
app.use('/admin', express.static(path.join(__dirname, 'admin')));
app.use('/user', express.static(path.join(__dirname, 'user')));

// DATABASE CONNECTION
const pool = mysql.createPool({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASS,
    database: process.env.DB_NAME,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

const getConnection = async () => await pool.getConnection();

const query = async (sql, params = []) => {
    const conn = await getConnection();
    try {
        const [results] = await conn.execute(sql, params);
        return results;
    } finally {
        conn.release();
    }
};

const queryOne = async (sql, params = []) => {
    const results = await query(sql, params);
    return results[0] || null;
};

// UTILITIES
const generateToken = (payload, expiresIn = process.env.JWT_ADMIN_EXPIRY) => jwt.sign(payload, process.env.JWT_SECRET, { expiresIn });
const hashPassword = async (password) => bcrypt.hash(password, 10);
const verifyPassword = async (password, hash) => bcrypt.compare(password, hash);
const errorResponse = (message, statusCode = 400) => ({ success: false, message, statusCode });
const successResponse = (message, data = null) => ({ success: true, message, data });

const logAction = async (adminId, adminName, action, entityType, entityId) => {
    try {
        await query('INSERT INTO system_logs (actor_type, actor_id, actor_label, action, entity_type, entity_id) VALUES (?, ?, ?, ?, ?, ?)', ['ADMIN', adminId, adminName, action, entityType, entityId]);
    } catch (error) {
        console.error('Logging error:', error.message);
    }
};

// MIDDLEWARE AUTH
const verifyToken = (req, res, next) => {
    const token = req.headers.authorization?.split(' ')[1];
    if (!token) return res.status(401).json(errorResponse('No token', 401));
    try {
        req.user = jwt.verify(token, process.env.JWT_SECRET);
        next();
    } catch (error) {
        res.status(401).json(errorResponse('Invalid token', 401));
    }
};

const verifyAdminToken = (req, res, next) => {
    verifyToken(req, res, () => {
        if (req.user.type !== 'admin') return res.status(403).json(errorResponse('Admin required', 403));
        next();
    });
};

const verifyUserToken = (req, res, next) => {
    verifyToken(req, res, () => {
        if (req.user.type !== 'user') return res.status(403).json(errorResponse('User required', 403));
        next();
    });
};

// Role-based access control middleware
const verifyAdminRole = (requiredRoles) => {
    return (req, res, next) => {
        if (!req.user.roles || req.user.roles.length === 0) {
            return res.status(403).json(errorResponse('No roles assigned', 403));
        }
        
        const hasRole = req.user.roles.some(role => requiredRoles.includes(role));
        if (!hasRole) {
            return res.status(403).json(errorResponse('Insufficient permissions', 403));
        }
        next();
    };
};

// HEALTH
app.get('/api/health', (req, res) => res.json({ status: 'ok', timestamp: new Date().toISOString() }));

// ADMIN AUTH
app.post('/api/admin/login', async (req, res) => {
    try {
        const { email, password } = req.body;
        if (!email || !password) return res.status(400).json(errorResponse('Email and password required'));

        const admin = await queryOne('SELECT id, full_name, email, password_hash, is_active FROM admins WHERE email = ?', [email]);
        if (!admin || !admin.is_active || !(await verifyPassword(password, admin.password_hash))) {
            return res.status(401).json(errorResponse('Invalid credentials'));
        }

        const roles = await query('SELECT r.name FROM roles r INNER JOIN admin_roles ar ON r.id = ar.role_id WHERE ar.admin_id = ?', [admin.id]);
        const token = generateToken({ id: admin.id, email: admin.email, name: admin.full_name, type: 'admin', roles: roles.map(r => r.name) });

        await logAction(admin.id, admin.full_name, 'LOGIN', 'ADMIN', admin.id);

        res.json(successResponse('Login successful', { token, admin: { id: admin.id, name: admin.full_name, email: admin.email, roles: roles.map(r => r.name) } }));
    } catch (error) {
        console.error('Admin login error:', error);
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.post('/api/admin/logout', verifyAdminToken, async (req, res) => {
    try {
        await logAction(req.user.id, req.user.name, 'LOGOUT', 'ADMIN', req.user.id);
        res.json(successResponse('Logged out'));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.post('/api/setup/create-admin', async (req, res) => {
    try {
        const { full_name, email, password } = req.body;
        if (!full_name || !email || !password) return res.status(400).json(errorResponse('Missing fields'));

        const existing = await queryOne('SELECT id FROM admins WHERE email = ?', [email]);
        if (existing) return res.status(400).json(errorResponse('Admin exists'));

        const hashedPassword = await hashPassword(password);
        const result = await query('INSERT INTO admins (full_name, email, password_hash, is_active) VALUES (?, ?, ?, 1)', [full_name, email, hashedPassword]);

        const defaultRole = await queryOne('SELECT id FROM roles WHERE name = "admin" LIMIT 1');
        if (defaultRole) await query('INSERT INTO admin_roles (admin_id, role_id) VALUES (?, ?)', [result.insertId, defaultRole.id]);

        res.status(201).json(successResponse('Admin created', { id: result.insertId, email }));
    } catch (error) {
        console.error('Setup error:', error);
        res.status(500).json(errorResponse('Server error', 500));
    }
});

// USER AUTH
app.post('/api/user/login', async (req, res) => {
    try {
        const { phone_number, pin } = req.body;
        if (!phone_number || !pin) return res.status(400).json(errorResponse('Phone and PIN required'));

        const user = await queryOne('SELECT id, phone_number, pin, full_name, household_id, has_changed_default_pin FROM users WHERE phone_number = ?', [phone_number]);
        if (!user || user.pin !== pin) return res.status(401).json(errorResponse('Invalid credentials'));

        const token = generateToken({ id: user.id, phone: user.phone_number, household_id: user.household_id, type: 'user' }, process.env.JWT_USER_EXPIRY);
        res.json(successResponse('Login successful', { token, user: { id: user.id, name: user.full_name, phone: user.phone_number, household_id: user.household_id, requires_pin_change: !user.has_changed_default_pin } }));
    } catch (error) {
        console.error('User login error:', error);
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.post('/api/user/change-pin', verifyUserToken, async (req, res) => {
    try {
        const { old_pin, new_pin } = req.body;
        const user = await queryOne('SELECT pin FROM users WHERE id = ?', [req.user.id]);
        if (!user || user.pin !== old_pin) return res.status(401).json(errorResponse('Invalid PIN'));

        await query('UPDATE users SET pin = ?, has_changed_default_pin = 1 WHERE id = ?', [new_pin, req.user.id]);
        res.json(successResponse('PIN changed'));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.post('/api/user/logout', verifyUserToken, (req, res) => res.json(successResponse('Logged out')));

// LOCATIONS
app.get('/api/admin/locations', verifyAdminToken, async (req, res) => {
    try {
        const locations = await query('SELECT * FROM locations ORDER BY created_at DESC');
        res.json(successResponse('Locations retrieved', locations));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.get('/api/admin/locations/:id', verifyAdminToken, async (req, res) => {
    try {
        const location = await queryOne('SELECT * FROM locations WHERE id = ?', [req.params.id]);
        if (!location) return res.status(404).json(errorResponse('Not found', 404));
        res.json(successResponse('Location retrieved', location));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.post('/api/admin/locations', verifyAdminToken, async (req, res) => {
    try {
        const { name, code, address_line1, address_line2, city, region, is_active } = req.body;
        if (!name) return res.status(400).json(errorResponse('Name required'));

        const result = await query('INSERT INTO locations (name, code, address_line1, address_line2, city, region, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)', [name, code || null, address_line1 || null, address_line2 || null, city || null, region || null, is_active !== false ? 1 : 0]);
        await logAction(req.user.id, req.user.name, 'CREATE_LOCATION', 'LOCATION', result.insertId);
        res.status(201).json(successResponse('Created', { id: result.insertId }));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.put('/api/admin/locations/:id', verifyAdminToken, async (req, res) => {
    try {
        const { name, code, address_line1, address_line2, city, region, is_active } = req.body;
        const updates = [], values = [];

        if (name !== undefined) { updates.push('name = ?'); values.push(name); }
        if (code !== undefined) { updates.push('code = ?'); values.push(code); }
        if (address_line1 !== undefined) { updates.push('address_line1 = ?'); values.push(address_line1); }
        if (address_line2 !== undefined) { updates.push('address_line2 = ?'); values.push(address_line2); }
        if (city !== undefined) { updates.push('city = ?'); values.push(city); }
        if (region !== undefined) { updates.push('region = ?'); values.push(region); }
        if (is_active !== undefined) { updates.push('is_active = ?'); values.push(is_active ? 1 : 0); }

        if (updates.length === 0) return res.status(400).json(errorResponse('No fields'));

        values.push(req.params.id);
        const result = await query(`UPDATE locations SET ${updates.join(', ')} WHERE id = ?`, values);

        if (result.affectedRows === 0) return res.status(404).json(errorResponse('Not found', 404));

        await logAction(req.user.id, req.user.name, 'UPDATE_LOCATION', 'LOCATION', req.params.id);
        res.json(successResponse('Updated'));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.delete('/api/admin/locations/:id', verifyAdminToken, async (req, res) => {
    try {
        const result = await query('DELETE FROM locations WHERE id = ?', [req.params.id]);
        if (result.affectedRows === 0) return res.status(404).json(errorResponse('Not found', 404));

        await logAction(req.user.id, req.user.name, 'DELETE_LOCATION', 'LOCATION', req.params.id);
        res.json(successResponse('Deleted'));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

// HOUSEHOLDS
app.get('/api/admin/households', verifyAdminToken, async (req, res) => {
    try {
        const locationId = req.query.location_id;
        let sql = 'SELECT * FROM households';
        let params = [];

        if (locationId) { sql += ' WHERE location_id = ?'; params.push(locationId); }

        const households = await query(sql + ' ORDER BY created_at DESC', params);
        res.json(successResponse('Households retrieved', households));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.get('/api/admin/households/:id', verifyAdminToken, async (req, res) => {
    try {
        const household = await queryOne('SELECT * FROM households WHERE id = ?', [req.params.id]);
        if (!household) return res.status(404).json(errorResponse('Not found', 404));
        res.json(successResponse('Household retrieved', household));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.post('/api/admin/households', verifyAdminToken, async (req, res) => {
    try {
        const { location_id, apartment_label, primary_full_name, phone_msisdn, email, default_pin } = req.body;
        if (!location_id || !apartment_label || !primary_full_name || !phone_msisdn) {
            return res.status(400).json(errorResponse('Required fields missing'));
        }

        // Generate login identifier and hash PIN
        const loginIdentifier = `${Date.now()}-${Math.random().toString(36).substr(2, 5)}`;
        const pinToHash = default_pin || '1234';
        const pinHash = await bcrypt.hash(pinToHash, 10);

        const result = await query(
            'INSERT INTO households (location_id, apartment_label, primary_full_name, phone_msisdn, email, login_identifier, pin_hash, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)',
            [location_id, apartment_label, primary_full_name, phone_msisdn, email || null, loginIdentifier, pinHash]
        );

        await logAction(req.user.id, req.user.name, 'CREATE_HOUSEHOLD', 'HOUSEHOLD', result.insertId);
        res.status(201).json(successResponse('Created', { id: result.insertId }));
    } catch (error) {
        console.error('Household creation error:', error);
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.put('/api/admin/households/:id', verifyAdminToken, async (req, res) => {
    try {
        const { apartment_label, primary_full_name, phone_msisdn, email, is_active } = req.body;
        const updates = [], values = [];

        if (apartment_label !== undefined) { updates.push('apartment_label = ?'); values.push(apartment_label); }
        if (primary_full_name !== undefined) { updates.push('primary_full_name = ?'); values.push(primary_full_name); }
        if (phone_msisdn !== undefined) { updates.push('phone_msisdn = ?'); values.push(phone_msisdn); }
        if (email !== undefined) { updates.push('email = ?'); values.push(email); }
        if (is_active !== undefined) { updates.push('is_active = ?'); values.push(is_active ? 1 : 0); }

        if (updates.length === 0) return res.status(400).json(errorResponse('No fields'));

        values.push(req.params.id);
        const result = await query(`UPDATE households SET ${updates.join(', ')} WHERE id = ?`, values);

        if (result.affectedRows === 0) return res.status(404).json(errorResponse('Not found', 404));

        await logAction(req.user.id, req.user.name, 'UPDATE_HOUSEHOLD', 'HOUSEHOLD', req.params.id);
        res.json(successResponse('Updated'));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.delete('/api/admin/households/:id', verifyAdminToken, async (req, res) => {
    try {
        const result = await query('DELETE FROM households WHERE id = ?', [req.params.id]);
        if (result.affectedRows === 0) return res.status(404).json(errorResponse('Not found', 404));

        await logAction(req.user.id, req.user.name, 'DELETE_HOUSEHOLD', 'HOUSEHOLD', req.params.id);
        res.json(successResponse('Deleted'));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

// PAYMENTS
app.get('/api/admin/payments', verifyAdminToken, async (req, res) => {
    try {
        const payments = await query('SELECT * FROM payments ORDER BY created_at DESC');
        res.json(successResponse('Payments retrieved', payments));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.get('/api/admin/payments/:id', verifyAdminToken, async (req, res) => {
    try {
        const payment = await queryOne('SELECT * FROM payments WHERE id = ?', [req.params.id]);
        if (!payment) return res.status(404).json(errorResponse('Not found', 404));
        res.json(successResponse('Payment retrieved', payment));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.post('/api/payments/initiate', verifyUserToken, async (req, res) => {
    try {
        const { phone_number, payment_method } = req.body;
        if (!phone_number || !payment_method) {
            return res.status(400).json(errorResponse('Phone number and payment method required'));
        }

        // Use fixed amount from environment (25000 XAF for Cameroon)
        const fixedAmount = parseInt(process.env.FAPSHI_FIXED_AMOUNT || 25000);
        
        // Validate Cameroon phone numbers (starts with 6)
        if (!/^(6|\\+2376)\d{8}$/.test(phone_number.replace(/\+237/, '').replace(/^0/, ''))) {
            return res.status(400).json(errorResponse('Invalid Cameroon phone number'));
        }

        try {
            // Determine Fapshi URL based on environment (sandbox vs live)
            const isSandbox = process.env.FAPSHI_SANDBOX_MODE === 'true';
            const fapshiBaseUrl = isSandbox 
                ? process.env.FAPSHI_SANDBOX_URL || 'https://sandbox.fapshi.com'
                : process.env.FAPSHI_LIVE_URL || 'https://live.fapshi.com';
            
            // Normalize phone number for Fapshi (remove country code if present)
            const normalizedPhone = phone_number.replace(/^\+237/, '').replace(/^0/, '');
            
            // Call Fapshi API using direct-pay endpoint
            const fapshiResponse = await axios.post(`${fapshiBaseUrl}/direct-pay`, {
                apiuser: process.env.FAPSHI_API_USER,
                apikey: process.env.FAPSHI_API_KEY,
                phone: normalizedPhone,
                amount: fixedAmount,
                medium: payment_method.toUpperCase()
            });

            // Check if payment was initiated successfully
            if (fapshiResponse.status === 200 && fapshiResponse.data.transId) {
                const result = await query(
                    'INSERT INTO payments (household_id, amount, phone_number, status, transaction_id, payment_method) VALUES (?, ?, ?, ?, ?, ?)',
                    [req.user.household_id, fixedAmount, phone_number, 'PENDING', fapshiResponse.data.transId, payment_method]
                );

                await logAction(req.user.id, phone_number, 'INITIATE_PAYMENT', 'PAYMENT', result.insertId);
                
                res.status(201).json(successResponse('Payment initiated', {
                    transaction_id: fapshiResponse.data.transId,
                    amount: fixedAmount,
                    phone: phone_number,
                    status: 'PENDING',
                    sandbox_mode: isSandbox
                }));
            } else {
                res.status(400).json(errorResponse(fapshiResponse.data?.message || 'Payment initiation failed'));
            }
        } catch (fapshiError) {
            console.error('Fapshi error:', fapshiError.response?.data || fapshiError.message);
            res.status(500).json(errorResponse(fapshiError.response?.data?.message || 'Payment service error'));
        }
    } catch (error) {
        console.error('Payment initiation error:', error);
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.post('/api/payments/webhook', async (req, res) => {
    try {
        // Fapshi sends transId, status, and other transaction details
        const { transId, status } = req.body;
        if (!transId || !status) {
            console.error('Webhook missing required fields:', req.body);
            return res.status(400).json(errorResponse('Missing transId or status'));
        }

        // Find payment by transaction ID
        const payment = await query('SELECT id, household_id FROM payments WHERE transaction_id = ?', [transId]);
        if (payment.length === 0) {
            console.warn('Payment not found for transId:', transId);
            return res.status(404).json(errorResponse('Payment not found'));
        }

        // Map Fapshi status to our status
        const statusMap = {
            'SUCCESSFUL': 'COMPLETED',
            'FAILED': 'FAILED',
            'EXPIRED': 'EXPIRED',
            'PENDING': 'PENDING'
        };

        const mappedStatus = statusMap[status] || status;

        // Update payment status
        await query('UPDATE payments SET status = ? WHERE transaction_id = ?', [mappedStatus, transId]);
        
        // Log the webhook update
        await logAction('SYSTEM', 'FAPSHI_WEBHOOK', 'UPDATE_PAYMENT_STATUS', 'PAYMENT', payment[0].id);
        
        console.log(`[WEBHOOK] Payment ${transId} updated to ${mappedStatus}`);
        
        res.json(successResponse('Webhook processed', { 
            transaction_id: transId, 
            status: mappedStatus,
            payment_id: payment[0].id 
        }));
    } catch (error) {
        console.error('Webhook error:', error);
        res.status(500).json(errorResponse('Webhook processing error', 500));
    }
});

app.get('/api/payments/status/:transactionId', verifyUserToken, async (req, res) => {
    try {
        const payment = await queryOne('SELECT * FROM payments WHERE transaction_id = ? AND household_id = ?', [req.params.transactionId, req.user.household_id]);
        if (!payment) return res.status(404).json(errorResponse('Not found', 404));

        res.json(successResponse('Status retrieved', {
            transaction_id: payment.transaction_id,
            status: payment.status,
            amount: payment.amount
        }));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

// ADMINS
app.get('/api/admin/admins', verifyAdminToken, async (req, res) => {
    try {
        const admins = await query('SELECT id, full_name, email, is_active, created_at FROM admins ORDER BY created_at DESC');
        res.json(successResponse('Admins retrieved', admins));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.post('/api/admin/admins', verifyAdminToken, async (req, res) => {
    try {
        const { full_name, email, password, roles } = req.body;
        if (!full_name || !email || !password) return res.status(400).json(errorResponse('Required fields missing'));

        const existing = await queryOne('SELECT id FROM admins WHERE email = ?', [email]);
        if (existing) return res.status(400).json(errorResponse('Email already in use'));

        const hashedPassword = await hashPassword(password);
        const result = await query('INSERT INTO admins (full_name, email, password_hash, is_active) VALUES (?, ?, ?, 1)', [full_name, email, hashedPassword]);

        if (roles && Array.isArray(roles)) {
            for (const roleId of roles) {
                await query('INSERT INTO admin_roles (admin_id, role_id) VALUES (?, ?)', [result.insertId, roleId]);
            }
        }

        await logAction(req.user.id, req.user.name, 'CREATE_ADMIN', 'ADMIN', result.insertId);
        res.status(201).json(successResponse('Created', { id: result.insertId }));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.put('/api/admin/admins/:id', verifyAdminToken, async (req, res) => {
    try {
        const { full_name, email, is_active } = req.body;
        const updates = [], values = [];

        if (full_name !== undefined) { updates.push('full_name = ?'); values.push(full_name); }
        if (email !== undefined) { updates.push('email = ?'); values.push(email); }
        if (is_active !== undefined) { updates.push('is_active = ?'); values.push(is_active ? 1 : 0); }

        if (updates.length === 0) return res.status(400).json(errorResponse('No fields'));

        values.push(req.params.id);
        const result = await query(`UPDATE admins SET ${updates.join(', ')} WHERE id = ?`, values);

        if (result.affectedRows === 0) return res.status(404).json(errorResponse('Not found', 404));

        await logAction(req.user.id, req.user.name, 'UPDATE_ADMIN', 'ADMIN', req.params.id);
        res.json(successResponse('Updated'));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.delete('/api/admin/admins/:id', verifyAdminToken, async (req, res) => {
    try {
        if (req.params.id == req.user.id) return res.status(400).json(errorResponse('Cannot delete yourself'));

        const result = await query('DELETE FROM admins WHERE id = ?', [req.params.id]);
        if (result.affectedRows === 0) return res.status(404).json(errorResponse('Not found', 404));

        await query('DELETE FROM admin_roles WHERE admin_id = ?', [req.params.id]);
        await logAction(req.user.id, req.user.name, 'DELETE_ADMIN', 'ADMIN', req.params.id);
        res.json(successResponse('Deleted'));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

// ADMIN ROLES
app.get('/api/admin/roles', verifyAdminToken, async (req, res) => {
    try {
        const roles = await query('SELECT id, name, description FROM roles WHERE is_system = 1 ORDER BY name');
        res.json(successResponse('Roles retrieved', roles));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.get('/api/admin/admins/:id/roles', verifyAdminToken, async (req, res) => {
    try {
        const roles = await query('SELECT r.id, r.name, r.description FROM roles r INNER JOIN admin_roles ar ON r.id = ar.role_id WHERE ar.admin_id = ?', [req.params.id]);
        res.json(successResponse('Admin roles retrieved', roles));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.post('/api/admin/admins/:id/roles', verifyAdminToken, async (req, res) => {
    try {
        const { role_ids } = req.body;
        if (!Array.isArray(role_ids) || role_ids.length === 0) {
            return res.status(400).json(errorResponse('Role IDs array required'));
        }

        // Clear existing roles
        await query('DELETE FROM admin_roles WHERE admin_id = ?', [req.params.id]);

        // Add new roles
        for (const roleId of role_ids) {
            await query('INSERT INTO admin_roles (admin_id, role_id) VALUES (?, ?)', [req.params.id, roleId]);
        }

        await logAction(req.user.id, req.user.name, 'ASSIGN_ADMIN_ROLES', 'ADMIN', req.params.id);
        res.json(successResponse('Roles assigned'));
    } catch (error) {
        console.error('Role assignment error:', error);
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.delete('/api/admin/admins/:id/roles/:roleId', verifyAdminToken, async (req, res) => {
    try {
        const result = await query('DELETE FROM admin_roles WHERE admin_id = ? AND role_id = ?', [req.params.id, req.params.roleId]);
        if (result.affectedRows === 0) return res.status(404).json(errorResponse('Not found', 404));

        await logAction(req.user.id, req.user.name, 'REMOVE_ADMIN_ROLE', 'ADMIN', req.params.id);
        res.json(successResponse('Role removed'));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

// USER PROFILE
app.get('/api/user/profile', verifyUserToken, async (req, res) => {
    try {
        const user = await queryOne('SELECT id, full_name, phone_number, household_id FROM users WHERE id = ?', [req.user.id]);
        if (!user) return res.status(404).json(errorResponse('Not found', 404));
        res.json(successResponse('Profile retrieved', user));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.put('/api/user/profile', verifyUserToken, async (req, res) => {
    try {
        const { full_name } = req.body;
        if (!full_name) return res.status(400).json(errorResponse('Full name required'));

        const result = await query('UPDATE users SET full_name = ? WHERE id = ?', [full_name, req.user.id]);
        if (result.affectedRows === 0) return res.status(404).json(errorResponse('Not found', 404));

        res.json(successResponse('Profile updated'));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.get('/api/user/subscriptions', verifyUserToken, async (req, res) => {
    try {
        const subscriptions = await query('SELECT * FROM subscriptions WHERE household_id = ? ORDER BY created_at DESC', [req.user.household_id]);
        res.json(successResponse('Subscriptions retrieved', subscriptions));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.get('/api/user/billing', verifyUserToken, async (req, res) => {
    try {
        const payments = await query('SELECT * FROM payments WHERE household_id = ? ORDER BY created_at DESC LIMIT 10', [req.user.household_id]);
        res.json(successResponse('Billing retrieved', payments));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

// LOGS
app.get('/api/admin/logs', verifyAdminToken, async (req, res) => {
    try {
        const limit = req.query.limit || 50;
        const logs = await query('SELECT * FROM system_logs ORDER BY created_at DESC LIMIT ?', [parseInt(limit)]);
        res.json(successResponse('Logs retrieved', logs));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

// STATIC FILES
app.get('/admin/*', (req, res) => {
    const filePath = req.params[0];
    if (!filePath || filePath === '') {
        res.sendFile(path.join(__dirname, 'admin', 'login.html'));
    } else {
        res.sendFile(path.join(__dirname, 'admin', filePath), (err) => {
            if (err) res.status(404).json(errorResponse('Not found', 404));
        });
    }
});

app.get('/user/*', (req, res) => {
    const filePath = req.params[0];
    if (!filePath || filePath === '') {
        res.sendFile(path.join(__dirname, 'user', 'login.html'));
    } else {
        res.sendFile(path.join(__dirname, 'user', filePath), (err) => {
            if (err) res.status(404).json(errorResponse('Not found', 404));
        });
    }
});

app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'index.html'));
});

// ERROR HANDLERS
app.use((req, res) => res.status(404).json(errorResponse('Route not found', 404)));
app.use((err, req, res, next) => {
    console.error('Error:', err);
    res.status(500).json(errorResponse('Internal error', 500));
});

// START
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`✅ FlexNet Server running on http://localhost:${PORT}`);
    console.log(`📋 Admin: http://localhost:${PORT}/admin`);
    console.log(`👤 User: http://localhost:${PORT}/user`);
    console.log(`🔌 API: http://localhost:${PORT}/api`);
});

export default app;
