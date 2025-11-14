import mysql from 'mysql2/promise';
import dotenv from 'dotenv';

dotenv.config();

async function seedTestUser() {
  const connection = await mysql.createConnection({
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASS || 'mysql',
    database: process.env.DB_NAME || 'flexnet',
  });

  try {
    console.log('Creating test user...');

    // Insert test user
    const [result] = await connection.execute(
      `INSERT INTO users (phone_number, pin, full_name, household_id, has_changed_default_pin) 
       VALUES (?, ?, ?, ?, ?) 
       ON DUPLICATE KEY UPDATE pin = VALUES(pin), full_name = VALUES(full_name), has_changed_default_pin = VALUES(has_changed_default_pin)`,
      ['679690703', '1234', 'Test User', 1, 0]
    );

    console.log('✓ Test user created/updated successfully');
    console.log('\nCredentials:');
    console.log('Phone: 679690703');
    console.log('PIN: 1234');
    console.log('\nYou will be prompted to change your PIN on first login.');

  } catch (error) {
    console.error('Error seeding test user:', error.message);
  } finally {
    await connection.end();
  }
}

seedTestUser();
