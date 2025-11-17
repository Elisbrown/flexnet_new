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
import { directPay, paymentStatus } from './fapshi-sdk.js';

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

const buildPagination = (req, allowedSort = ['created_at'], defaultSort = 'created_at') => {
    const page = Math.max(parseInt(req.query.page, 10) || 1, 1);
    const limit = Math.max(Math.min(parseInt(req.query.limit, 10) || 10, 100), 1);
    const offset = (page - 1) * limit;
    const sortBy = allowedSort.includes(req.query.sort_by) ? req.query.sort_by : defaultSort;
    const sortOrder = (req.query.order || 'desc').toLowerCase() === 'asc' ? 'ASC' : 'DESC';
    return { page, limit, offset, sortBy, sortOrder };
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

        const normalizedPhone = phone_number.replace(/\D/g, '').replace(/^237/, '');
        const household = await queryOne(
            'SELECT id, primary_full_name, phone_msisdn, pin_hash, has_changed_default_pin, subscription_status, subscription_end_date FROM households WHERE phone_msisdn = ? AND is_active = 1',
            [normalizedPhone]
        );

        if (!household || !(await verifyPassword(pin, household.pin_hash))) {
            return res.status(401).json(errorResponse('Invalid credentials'));
        }

        const token = generateToken({ id: household.id, phone: household.phone_msisdn, household_id: household.id, type: 'user' }, process.env.JWT_USER_EXPIRY || '7d');
        res.json(successResponse('Login successful', {
            token,
            user: {
                id: household.id,
                name: household.primary_full_name,
                phone: household.phone_msisdn,
                household_id: household.id,
                subscription_status: household.subscription_status,
                subscription_end_date: household.subscription_end_date,
                requires_pin_change: !household.has_changed_default_pin
            }
        }));
    } catch (error) {
        console.error('User login error:', error);
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.post('/api/user/change-pin', verifyUserToken, async (req, res) => {
    try {
        const { old_pin, new_pin } = req.body;
        const household = await queryOne('SELECT pin_hash FROM households WHERE id = ?', [req.user.id]);
        if (!household || !(await verifyPassword(old_pin, household.pin_hash))) return res.status(401).json(errorResponse('Invalid PIN'));

        const hashedNewPin = await hashPassword(new_pin);
        await query('UPDATE households SET pin_hash = ?, has_changed_default_pin = 1 WHERE id = ?', [hashedNewPin, req.user.id]);
        res.json(successResponse('PIN changed'));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.post('/api/user/logout', verifyUserToken, (req, res) => res.json(successResponse('Logged out')));

// LOCATIONS
app.get('/api/admin/locations', verifyAdminToken, async (req, res) => {
    try {
        const { page, limit, offset, sortBy, sortOrder } = buildPagination(req, ['name', 'created_at', 'region']);
        const filters = [];
        const params = [];

        if (req.query.region) {
            filters.push('region LIKE ?');
            params.push(`%${req.query.region}%`);
        }

        if (req.query.status) {
            if (req.query.status === 'active') {
                filters.push('is_active = 1');
            } else if (req.query.status === 'inactive') {
                filters.push('is_active = 0');
            }
        }

        if (req.query.search) {
            filters.push('(name LIKE ? OR code LIKE ? OR city LIKE ? OR region LIKE ?)');
            const term = `%${req.query.search}%`;
            params.push(term, term, term, term);
        }

        const where = filters.length ? `WHERE ${filters.join(' AND ')}` : '';
        const totalRow = await queryOne(`SELECT COUNT(*) as total FROM locations ${where}`, params);
        const locations = await query(
            `SELECT l.*, (SELECT COUNT(*) FROM households h WHERE h.location_id = l.id) AS household_count
             FROM locations l ${where} ORDER BY ${sortBy} ${sortOrder} LIMIT ? OFFSET ?`,
            [...params, limit, offset]
        );

        res.json(successResponse('Locations retrieved', {
            items: locations,
            pagination: { page, limit, total: totalRow.total, pages: Math.ceil(totalRow.total / limit) }
        }));
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
        const { page, limit, offset, sortBy, sortOrder } = buildPagination(req, ['primary_full_name', 'created_at', 'subscription_status'], 'created_at');
        const filters = [];
        const params = [];

        if (req.query.location_id) {
            filters.push('location_id = ?');
            params.push(req.query.location_id);
        }

        if (req.query.status) {
            filters.push('subscription_status = ?');
            params.push(req.query.status);
        }

        if (req.query.search) {
            filters.push('(primary_full_name LIKE ? OR phone_msisdn LIKE ? OR email LIKE ? OR apartment_label LIKE ?)');
            const term = `%${req.query.search}%`;
            params.push(term, term, term, term);
        }

        const where = filters.length ? `WHERE ${filters.join(' AND ')}` : '';
        const totalRow = await queryOne(`SELECT COUNT(*) as total FROM households ${where}`, params);
        const households = await query(
            `SELECT * FROM households ${where} ORDER BY ${sortBy} ${sortOrder} LIMIT ? OFFSET ?`,
            [...params, limit, offset]
        );

        res.json(successResponse('Households retrieved', {
            items: households,
            pagination: { page, limit, total: totalRow.total, pages: Math.ceil(totalRow.total / limit) }
        }));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.get('/api/admin/households/:id', verifyAdminToken, async (req, res) => {
    try {
        const household = await queryOne('SELECT * FROM households WHERE id = ?', [req.params.id]);
        if (!household) return res.status(404).json(errorResponse('Not found', 404));

        const location = await queryOne('SELECT * FROM locations WHERE id = ?', [household.location_id]);
        const latestSubscription = await queryOne(
            'SELECT * FROM subscriptions WHERE household_id = ? ORDER BY created_at DESC LIMIT 1',
            [household.id]
        );
        const recentPayments = await query(
            'SELECT * FROM payments WHERE household_id = ? ORDER BY created_at DESC LIMIT 10',
            [household.id]
        );

        res.json(successResponse('Household retrieved', {
            household,
            location,
            subscription: latestSubscription,
            payments: recentPayments
        }));
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

app.post('/api/admin/households/:id/reset-pin', verifyAdminToken, async (req, res) => {
    try {
        const household = await queryOne('SELECT * FROM households WHERE id = ?', [req.params.id]);
        if (!household) return res.status(404).json(errorResponse('Not found', 404));

        const hashedDefault = await bcrypt.hash('1234', 10);
        await query(
            'UPDATE households SET pin_hash = ?, has_changed_default_pin = 0, updated_at = NOW() WHERE id = ?',
            [hashedDefault, household.id]
        );

        await logAction(req.user.id, req.user.name, 'RESET_PIN', 'HOUSEHOLD', household.id);
        res.json(successResponse('PIN reset to default'));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.post('/api/admin/households/:id/subscription-action', verifyAdminToken, async (req, res) => {
    try {
        const household = await queryOne('SELECT * FROM households WHERE id = ?', [req.params.id]);
        if (!household) return res.status(404).json(errorResponse('Not found', 404));

        const { action, start_date, end_date, pause_reason, pause_note } = req.body;
        const plan = await getDefaultPlan(process.env.DEFAULT_PLAN_ID);
        if (!plan) return res.status(400).json(errorResponse('No active plan configured'));

        let subscription = await queryOne(
            'SELECT * FROM subscriptions WHERE household_id = ? ORDER BY created_at DESC LIMIT 1',
            [household.id]
        );

        if (!subscription) {
            const insert = await query(
                'INSERT INTO subscriptions (household_id, plan_id, status, start_date, end_date, last_action, created_by_admin) VALUES (?, ?, "PENDING", ?, ?, NULL, ?)',
                [household.id, plan.id, start_date || null, end_date || null, req.user.id]
            );
            subscription = await queryOne('SELECT * FROM subscriptions WHERE id = ?', [insert.insertId]);
        }

        let newStatus = subscription.status;
        let newEndDate = end_date || subscription.end_date;
        let newStartDate = start_date || subscription.start_date;

        switch ((action || '').toUpperCase()) {
            case 'ACTIVATE':
            case 'RENEW':
            case 'EXTEND':
                newStatus = 'ACTIVE';
                break;
            case 'PAUSE':
                newStatus = 'PAUSED';
                break;
            default:
                return res.status(400).json(errorResponse('Unsupported action'));
        }

        await query(
            'UPDATE subscriptions SET status = ?, start_date = ?, end_date = ?, pause_reason = ?, last_action = ?, updated_at = NOW(), created_by_admin = ? WHERE id = ?',
            [newStatus, newStartDate, newEndDate, pause_reason || null, action.toUpperCase(), req.user.id, subscription.id]
        );

        await query(
            'INSERT INTO subscription_events (subscription_id, household_id, event_type, description, actor_type, actor_admin_id, meta_json) VALUES (?, ?, ?, ?, "ADMIN", ?, ?)',
            [subscription.id, household.id, action.toUpperCase(), pause_note || null, req.user.id, JSON.stringify(req.body || {})]
        );

        await query(
            'UPDATE households SET subscription_status = ?, subscription_end_date = ?, updated_at = NOW() WHERE id = ?',
            [newStatus === 'ACTIVE' ? 'ACTIVE' : newStatus, newEndDate || null, household.id]
        );

        const updatedSub = await queryOne('SELECT * FROM subscriptions WHERE id = ?', [subscription.id]);
        await logAction(req.user.id, req.user.name, `${action.toUpperCase()}_SUBSCRIPTION`, 'HOUSEHOLD', household.id);

        res.json(successResponse('Subscription updated', { subscription: updatedSub }));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

// PAYMENTS
const getDefaultPlan = async (planId = null) => {
    if (planId) {
        const plan = await queryOne('SELECT * FROM plans WHERE id = ? AND is_active = 1', [planId]);
        if (plan) return plan;
    }

    return queryOne('SELECT * FROM plans WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
};

const activateSubscriptionFromPayment = async (paymentRow, providerStatus) => {
    const successStatuses = ['SUCCESS', 'SUCCESSFUL'];
    if (!successStatuses.includes(providerStatus)) return;

    let subscriptionId = paymentRow.subscription_id;
    const plan = await getDefaultPlan(paymentRow.plan_id);
    if (!plan) return;

    const startDate = new Date();
    const endDate = new Date();
    endDate.setDate(startDate.getDate() + plan.duration_days);
    const startDateStr = startDate.toISOString().slice(0, 10);
    const endDateStr = endDate.toISOString().slice(0, 10);

    if (!subscriptionId) {
        const subResult = await query(
            'INSERT INTO subscriptions (household_id, plan_id, status, start_date, end_date, last_action) VALUES (?, ?, "ACTIVE", ?, ?, "ACTIVATE")',
            [paymentRow.household_id, plan.id, startDateStr, endDateStr]
        );
        subscriptionId = subResult.insertId;
    } else {
        await query(
            'UPDATE subscriptions SET status = "ACTIVE", start_date = ?, end_date = ?, last_action = "RENEW" WHERE id = ?',
            [startDateStr, endDateStr, subscriptionId]
        );
    }

    await query(
        'UPDATE households SET current_subscription_id = ?, subscription_status = "ACTIVE", subscription_end_date = ? WHERE id = ?',
        [subscriptionId, endDateStr, paymentRow.household_id]
    );

    return { subscriptionId, endDate: endDateStr };
};

app.get('/api/admin/payments', verifyAdminToken, async (req, res) => {
    try {
        const { page, limit, offset, sortBy, sortOrder } = buildPagination(req, ['created_at', 'amount_xaf'], 'created_at');
        const filters = [];
        const params = [];

        if (req.query.status) {
            filters.push('p.status = ?');
            params.push(req.query.status.toUpperCase());
        }

        if (req.query.channel) {
            filters.push('p.channel = ?');
            params.push(req.query.channel);
        }

        if (req.query.household_id) {
            filters.push('p.household_id = ?');
            params.push(req.query.household_id);
        }

        if (req.query.search) {
            filters.push('(p.external_id LIKE ? OR p.provider_txn_id LIKE ? OR h.primary_full_name LIKE ? OR h.phone_msisdn LIKE ?)');
            const term = `%${req.query.search}%`;
            params.push(term, term, term, term);
        }

        const where = filters.length ? `WHERE ${filters.join(' AND ')}` : '';
        const totalRow = await queryOne(`SELECT COUNT(*) as total FROM payments p LEFT JOIN households h ON h.id = p.household_id ${where}`, params);
        const payments = await query(
            `SELECT p.*, h.primary_full_name, h.phone_msisdn
             FROM payments p
             LEFT JOIN households h ON h.id = p.household_id
             ${where}
             ORDER BY ${sortBy.startsWith('amount') ? 'p.' + sortBy : 'p.' + sortBy} ${sortOrder}
             LIMIT ? OFFSET ?`,
            [...params, limit, offset]
        );

        res.json(successResponse('Payments retrieved', {
            items: payments,
            pagination: { page, limit, total: totalRow.total, pages: Math.ceil(totalRow.total / limit) }
        }));
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

app.post('/api/admin/payments/:id/decision', verifyAdminToken, async (req, res) => {
    try {
        const { decision, note } = req.body;
        const payment = await queryOne('SELECT * FROM payments WHERE id = ?', [req.params.id]);
        if (!payment) return res.status(404).json(errorResponse('Not found', 404));

        const resolvedStatus = decision === 'reject' ? 'FAILED' : 'SUCCESS';
        await query(
            'UPDATE payments SET status = ?, provider_status = ?, message = ?, updated_at = NOW() WHERE id = ?',
            [resolvedStatus, resolvedStatus, note || null, payment.id]
        );

        const refreshed = await queryOne('SELECT * FROM payments WHERE id = ?', [payment.id]);
        await activateSubscriptionFromPayment(refreshed, refreshed.provider_status);
        await logAction(req.user.id, req.user.name, 'PAYMENT_DECISION', 'PAYMENT', payment.id);

        res.json(successResponse('Payment updated', refreshed));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.get('/api/user/plan', verifyUserToken, async (req, res) => {
    try {
        const plan = await getDefaultPlan(process.env.DEFAULT_PLAN_ID);
        if (!plan) return res.status(404).json(errorResponse('No plan configured', 404));
        res.json(successResponse('Plan retrieved', plan));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.post('/api/payments/initiate', verifyUserToken, async (req, res) => {
    try {
        const { phone_number, payment_method, plan_id } = req.body;
        if (!phone_number || !payment_method) {
            return res.status(400).json(errorResponse('Phone number and payment method required'));
        }

        const plan = await getDefaultPlan(plan_id || process.env.DEFAULT_PLAN_ID);
        if (!plan) {
            return res.status(400).json(errorResponse('No active plan available'));
        }

        const normalizedPhone = phone_number.replace(/^\+237/, '').replace(/^0/, '');
        if (!/^6\d{8}$/.test(normalizedPhone)) {
            return res.status(400).json(errorResponse('Invalid Cameroon phone number'));
        }

        const channel = payment_method.toUpperCase().includes('ORANGE') ? 'ORANGE_MONEY' : 'MTN_MOMO';

        try {
            const fapshiResponse = await directPay({
                amount: plan.price_xaf,
                phone: normalizedPhone,
                medium: channel,
                message: `FlexNet ${plan.name}`
            });

            if (!fapshiResponse?.transId) {
                return res.status(400).json(errorResponse(fapshiResponse?.message || 'Payment initiation failed'));
            }

            const subscriptionResult = await query(
                'INSERT INTO subscriptions (household_id, plan_id, status, created_by_admin) VALUES (?, ?, "PENDING", NULL)',
                [req.user.household_id, plan.id]
            );

            const insertResult = await query(
                'INSERT INTO payments (household_id, subscription_id, plan_id, provider, channel, currency_code, amount_xaf, external_id, provider_user_id, provider_txn_id, provider_status, status, message, raw_request_json, raw_response_json) VALUES (?, ?, ?, "FAPSHI", ?, "XAF", ?, ?, ?, ?, ?, "PENDING", ?, ?, ?)',
                [
                    req.user.household_id,
                    subscriptionResult.insertId,
                    plan.id,
                    channel,
                    plan.price_xaf,
                    fapshiResponse.transId,
                    normalizedPhone,
                    fapshiResponse.transId,
                    fapshiResponse.status || 'PENDING',
                    fapshiResponse.message || 'Pending payment',
                    JSON.stringify({ phone_number, payment_method, amount: plan.price_xaf, plan_id: plan.id }),
                    JSON.stringify(fapshiResponse)
                ]
            );

            await logAction(req.user.id, phone_number, 'INITIATE_PAYMENT', 'PAYMENT', insertResult.insertId);

            res.status(201).json(successResponse('Payment initiated', {
                transaction_id: fapshiResponse.transId,
                amount: plan.price_xaf,
                phone: phone_number,
                status: 'PENDING',
                channel,
                plan_id: plan.id
            }));
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
        const { transId, status } = req.body;
        if (!transId || !status) {
            console.error('Webhook missing required fields:', req.body);
            return res.status(400).json(errorResponse('Missing transId or status'));
        }

        const payment = await queryOne('SELECT id FROM payments WHERE provider_txn_id = ?', [transId]);
        if (!payment) {
            console.warn('Payment not found for transId:', transId);
            return res.status(404).json(errorResponse('Payment not found'));
        }

        const statusMap = {
            SUCCESSFUL: 'SUCCESS',
            FAILED: 'FAILED',
            EXPIRED: 'EXPIRED',
            PENDING: 'PENDING'
        };
        const mappedStatus = statusMap[status] || 'PENDING';

        await query(
            'UPDATE payments SET provider_status = ?, status = ?, completed_at = IF(?, NOW(), completed_at), last_webhook_at = NOW(), last_webhook_json = ? WHERE provider_txn_id = ?',
            [status, mappedStatus, mappedStatus === 'SUCCESS', JSON.stringify(req.body), transId]
        );

        await query('INSERT INTO payment_webhooks (provider, external_id, provider_txn_id, event_status, payload_json, http_status, processed_ok) VALUES ("FAPSHI", ?, ?, ?, ?, ?, 1)', [transId, transId, status, JSON.stringify(req.body), 200]);

        if (mappedStatus === 'SUCCESS') {
            const paymentRow = await queryOne('SELECT * FROM payments WHERE provider_txn_id = ?', [transId]);
            await activateSubscriptionFromPayment(paymentRow, status);
        }

        await logAction('SYSTEM', 'FAPSHI_WEBHOOK', 'UPDATE_PAYMENT_STATUS', 'PAYMENT', payment.id);

        res.json(successResponse('Webhook processed', {
            transaction_id: transId,
            status: mappedStatus,
            payment_id: payment.id
        }));
    } catch (error) {
        console.error('Webhook error:', error);
        res.status(500).json(errorResponse('Webhook processing error', 500));
    }
});

app.get('/api/payments/status/:transactionId', verifyUserToken, async (req, res) => {
    try {
        const payment = await queryOne('SELECT * FROM payments WHERE provider_txn_id = ? AND household_id = ?', [req.params.transactionId, req.user.household_id]);
        if (!payment) return res.status(404).json(errorResponse('Not found', 404));

        if (payment.status === 'PENDING') {
            try {
                const statusResponse = await paymentStatus(payment.provider_txn_id);
                if (statusResponse?.status) {
                    const statusMap = {
                        SUCCESSFUL: 'SUCCESS',
                        FAILED: 'FAILED',
                        EXPIRED: 'EXPIRED',
                        PENDING: 'PENDING'
                    };
                    const mappedStatus = statusMap[statusResponse.status] || payment.status;
                    await query(
                        'UPDATE payments SET provider_status = ?, status = ?, completed_at = IF(?, NOW(), completed_at), raw_response_json = ? WHERE provider_txn_id = ?',
                        [statusResponse.status, mappedStatus, mappedStatus === 'SUCCESS', JSON.stringify(statusResponse), payment.provider_txn_id]
                    );
                    payment.status = mappedStatus;

                    if (mappedStatus === 'SUCCESS') {
                        await activateSubscriptionFromPayment(payment, statusResponse.status);
                    }
                }
            } catch (statusError) {
                console.error('Status sync error:', statusError.message);
            }
        }

        res.json(successResponse('Status retrieved', {
            transaction_id: payment.provider_txn_id,
            status: payment.status,
            amount: payment.amount_xaf
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
        const household = await queryOne('SELECT id, primary_full_name, phone_msisdn, subscription_status, subscription_end_date FROM households WHERE id = ?', [req.user.id]);
        if (!household) return res.status(404).json(errorResponse('Not found', 404));
        res.json(successResponse('Profile retrieved', household));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.put('/api/user/profile', verifyUserToken, async (req, res) => {
    try {
        const { primary_full_name } = req.body;
        if (!primary_full_name) return res.status(400).json(errorResponse('Full name required'));

        const result = await query('UPDATE households SET primary_full_name = ? WHERE id = ?', [primary_full_name, req.user.id]);
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

// FAQS
app.get('/api/faqs', async (_req, res) => {
    try {
        const faqs = await query('SELECT id, slug, question_en, answer_en, question_fr, answer_fr FROM faqs WHERE is_published = 1 ORDER BY sort_order ASC, created_at DESC');
        res.json(successResponse('FAQs retrieved', faqs));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.get('/api/admin/faqs', verifyAdminToken, async (_req, res) => {
    try {
        const faqs = await query('SELECT * FROM faqs ORDER BY sort_order ASC, created_at DESC');
        res.json(successResponse('FAQs retrieved', faqs));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.post('/api/admin/faqs', verifyAdminToken, async (req, res) => {
    try {
        const { slug, question_en, answer_en, question_fr, answer_fr, is_published, sort_order } = req.body;
        if (!slug || !question_en || !answer_en) return res.status(400).json(errorResponse('Slug, English question and answer are required'));

        const result = await query(
            'INSERT INTO faqs (slug, question_en, answer_en, question_fr, answer_fr, is_published, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [slug, question_en, answer_en, question_fr || null, answer_fr || null, is_published ? 1 : 0, sort_order || 0]
        );

        await logAction(req.user.id, req.user.name, 'CREATE_FAQ', 'FAQ', result.insertId);
        res.status(201).json(successResponse('FAQ created', { id: result.insertId }));
    } catch (error) {
        console.error('FAQ create error:', error);
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.put('/api/admin/faqs/:id', verifyAdminToken, async (req, res) => {
    try {
        const { slug, question_en, answer_en, question_fr, answer_fr, is_published, sort_order } = req.body;
        const updates = [], values = [];

        if (slug !== undefined) { updates.push('slug = ?'); values.push(slug); }
        if (question_en !== undefined) { updates.push('question_en = ?'); values.push(question_en); }
        if (answer_en !== undefined) { updates.push('answer_en = ?'); values.push(answer_en); }
        if (question_fr !== undefined) { updates.push('question_fr = ?'); values.push(question_fr); }
        if (answer_fr !== undefined) { updates.push('answer_fr = ?'); values.push(answer_fr); }
        if (is_published !== undefined) { updates.push('is_published = ?'); values.push(is_published ? 1 : 0); }
        if (sort_order !== undefined) { updates.push('sort_order = ?'); values.push(sort_order); }

        if (updates.length === 0) return res.status(400).json(errorResponse('No fields'));

        values.push(req.params.id);
        const result = await query(`UPDATE faqs SET ${updates.join(', ')} WHERE id = ?`, values);

        if (result.affectedRows === 0) return res.status(404).json(errorResponse('Not found', 404));

        await logAction(req.user.id, req.user.name, 'UPDATE_FAQ', 'FAQ', req.params.id);
        res.json(successResponse('FAQ updated'));
    } catch (error) {
        console.error('FAQ update error:', error);
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.delete('/api/admin/faqs/:id', verifyAdminToken, async (req, res) => {
    try {
        const result = await query('DELETE FROM faqs WHERE id = ?', [req.params.id]);
        if (result.affectedRows === 0) return res.status(404).json(errorResponse('Not found', 404));

        await logAction(req.user.id, req.user.name, 'DELETE_FAQ', 'FAQ', req.params.id);
        res.json(successResponse('FAQ deleted'));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

// SUPPORT TICKETS
app.get('/api/admin/support-tickets', verifyAdminToken, async (req, res) => {
    try {
        const { status, priority, assignee, search, from, to } = req.query;
        const conditions = [], values = [];

        if (status) { conditions.push('t.status = ?'); values.push(status); }
        if (priority) { conditions.push('t.priority = ?'); values.push(priority); }
        if (assignee) { conditions.push('t.assigned_admin_id = ?'); values.push(assignee); }
        if (from) { conditions.push('t.created_at >= ?'); values.push(from); }
        if (to) { conditions.push('t.created_at <= ?'); values.push(to); }
        if (search) {
            conditions.push('(t.subject LIKE ? OR h.primary_full_name LIKE ? OR h.phone_msisdn LIKE ?)');
            values.push(`%${search}%`, `%${search}%`, `%${search}%`);
        }

        const whereClause = conditions.length ? `WHERE ${conditions.join(' AND ')}` : '';
        const tickets = await query(
            `SELECT t.*, h.primary_full_name AS requester_name, h.phone_msisdn AS requester_phone FROM support_tickets t
             LEFT JOIN households h ON h.id = t.household_id
             ${whereClause}
             ORDER BY t.updated_at DESC`
        , values);

        res.json(successResponse('Tickets retrieved', tickets));
    } catch (error) {
        console.error('Load tickets error:', error);
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.post('/api/admin/support-tickets', verifyAdminToken, async (req, res) => {
    try {
        const { household_id, phone, subject, priority, message, category } = req.body;
        if (!subject) return res.status(400).json(errorResponse('Subject required'));

        let resolvedHouseholdId = household_id;
        if (!resolvedHouseholdId && phone) {
            const household = await queryOne('SELECT id FROM households WHERE phone_msisdn = ?', [phone]);
            if (household) resolvedHouseholdId = household.id;
        }

        if (!resolvedHouseholdId) return res.status(400).json(errorResponse('Household not found'));

        const result = await query(
            'INSERT INTO support_tickets (household_id, subject, category, status, priority, created_by_type, created_by_admin_id, assigned_admin_id, last_message_at) VALUES (?, ?, ?, "OPEN", ?, "ADMIN", ?, NULL, NOW())',
            [resolvedHouseholdId, subject, category || null, priority || 'NORMAL', req.user.id]
        );

        if (message) {
            await query(
                'INSERT INTO support_messages (ticket_id, sender_type, sender_admin_id, sender_household_id, body) VALUES (?, "ADMIN", ?, NULL, ?)',
                [result.insertId, req.user.id, message]
            );
        }

        await logAction(req.user.id, req.user.name, 'CREATE_TICKET', 'SUPPORT_TICKET', result.insertId);
        res.status(201).json(successResponse('Ticket created', { id: result.insertId }));
    } catch (error) {
        console.error('Ticket create error:', error);
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.put('/api/admin/support-tickets/:id', verifyAdminToken, async (req, res) => {
    try {
        const { status, priority, assigned_admin_id, category } = req.body;
        const updates = [], values = [];

        if (status) { updates.push('status = ?'); values.push(status); }
        if (priority) { updates.push('priority = ?'); values.push(priority); }
        if (assigned_admin_id !== undefined) { updates.push('assigned_admin_id = ?'); values.push(assigned_admin_id || null); }
        if (category !== undefined) { updates.push('category = ?'); values.push(category || null); }

        if (!updates.length) return res.status(400).json(errorResponse('No fields'));

        updates.push('updated_at = NOW()');

        values.push(req.params.id);
        const result = await query(`UPDATE support_tickets SET ${updates.join(', ')} WHERE id = ?`, values);
        if (result.affectedRows === 0) return res.status(404).json(errorResponse('Not found', 404));

        await logAction(req.user.id, req.user.name, 'UPDATE_TICKET', 'SUPPORT_TICKET', req.params.id);
        res.json(successResponse('Ticket updated'));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.get('/api/admin/support-tickets/:id/messages', verifyAdminToken, async (req, res) => {
    try {
        const messages = await query('SELECT * FROM support_messages WHERE ticket_id = ? ORDER BY created_at ASC', [req.params.id]);
        res.json(successResponse('Messages retrieved', messages));
    } catch (error) {
        res.status(500).json(errorResponse('Server error', 500));
    }
});

app.post('/api/admin/support-tickets/:id/replies', verifyAdminToken, async (req, res) => {
    try {
        const { message } = req.body;
        if (!message) return res.status(400).json(errorResponse('Message required'));

        await query('INSERT INTO support_messages (ticket_id, sender_type, sender_admin_id, sender_household_id, body) VALUES (?, "ADMIN", ?, NULL, ?)', [req.params.id, req.user.id, message]);
        await query('UPDATE support_tickets SET last_message_at = NOW() WHERE id = ?', [req.params.id]);

        await logAction(req.user.id, req.user.name, 'ADD_TICKET_REPLY', 'SUPPORT_TICKET', req.params.id);
        res.json(successResponse('Reply added'));
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
