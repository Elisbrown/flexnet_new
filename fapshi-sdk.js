import axios from 'axios';

const validateIntegerAmount = (amount) => {
    if (amount === undefined || amount === null) throw new Error('amount required');
    if (!Number.isInteger(amount)) throw new Error('amount must be integer');
    if (amount < 100) throw new Error('amount cannot be less than 100 XAF');
};

const validatePhone = (phone) => {
    if (!phone) throw new Error('phone number required');
    if (typeof phone !== 'string') throw new Error('phone must be string');
    if (!/^6\d{8}$/.test(phone)) throw new Error('invalid phone number');
};

const getBaseUrl = () => {
    const useSandbox = process.env.FAPSHI_SANDBOX_MODE === 'true';
    return useSandbox
        ? process.env.FAPSHI_SANDBOX_URL || 'https://sandbox.fapshi.com'
        : process.env.FAPSHI_LIVE_URL || 'https://live.fapshi.com';
};

const buildHeaders = () => {
    if (!process.env.FAPSHI_API_USER || !process.env.FAPSHI_API_KEY) {
        throw new Error('FAPSHI_API_USER and FAPSHI_API_KEY are required');
    }

    return {
        apiuser: process.env.FAPSHI_API_USER,
        apikey: process.env.FAPSHI_API_KEY
    };
};

export const directPay = async (data) => {
    validateIntegerAmount(data.amount);
    validatePhone(data.phone);

    const config = {
        method: 'post',
        url: `${getBaseUrl()}/direct-pay`,
        headers: buildHeaders(),
        data
    };

    const response = await axios(config);
    return response.data;
};

export const paymentStatus = async (transId) => {
    if (!transId || typeof transId !== 'string') throw new Error('invalid transaction id');
    if (!/^[\w-]{6,64}$/.test(transId)) throw new Error('invalid transaction id');

    const config = {
        method: 'get',
        url: `${getBaseUrl()}/payment-status/${transId}`,
        headers: buildHeaders()
    };

    const response = await axios(config);
    return response.data;
};

