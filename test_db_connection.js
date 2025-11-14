
import mysql from 'mysql2/promise';
import dotenv from 'dotenv';

dotenv.config();

const testConnection = async () => {
    try {
        const connection = await mysql.createConnection({
            host: process.env.DB_HOST || 'localhost',
            user: process.env.DB_USER || 'root',
            password: process.env.DB_PASS || 'mysql',
            database: process.env.DB_NAME || 'flexnet',
        });
        console.log('Successfully connected to the database!');
        await connection.end();
    } catch (error) {
        console.error('Database connection failed:', error.message);
    }
};

testConnection();
