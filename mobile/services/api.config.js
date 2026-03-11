import { Platform } from 'react-native';

const DEVICE_IP = '192.168.43.21';

// Web uses localhost (same machine), phone uses the real IP
const LOCAL_IP = Platform.OS === 'web' ? 'localhost' : DEVICE_IP;

export const API_BASE_URL = `http://${LOCAL_IP}/rice_guard`;

export const API_CONFIG = {
    PHP_BASE_URL: `${API_BASE_URL}/app/api`,
    FLASK_BASE_URL: `http://${LOCAL_IP}:5000`,
};

export const ENDPOINTS = {
    // Auth
    LOGIN: `${API_CONFIG.PHP_BASE_URL}/auth.php?action=login`,
    REGISTER: `${API_CONFIG.PHP_BASE_URL}/auth.php?action=register`,

    // Reports
    REPORTS: `${API_CONFIG.PHP_BASE_URL}/reports.php`,

    // Diagnosis
    DIAGNOSIS: `${API_CONFIG.PHP_BASE_URL}/diagnosis.php`,
    DISEASES: `${API_CONFIG.PHP_BASE_URL}/diseases.php`,

    // Verification
    VERIFICATION: `${API_CONFIG.PHP_BASE_URL}/verification.php`,

    // Treatments
    TREATMENTS: `${API_CONFIG.PHP_BASE_URL}/treatments.php`,

    // Claims
    CLAIMS: `${API_CONFIG.PHP_BASE_URL}/claims.php`,

    // Users
    USERS: `${API_CONFIG.PHP_BASE_URL}/users.php`,

    // Stats
    STATS: `${API_CONFIG.PHP_BASE_URL}/stats.php`,

    // Flask AI
    AI_PREDICT: `${API_CONFIG.FLASK_BASE_URL}/predict`,
    AI_COMPARE: `${API_CONFIG.FLASK_BASE_URL}/predict/compare`,
    AI_MODELS: `${API_CONFIG.FLASK_BASE_URL}/models`,
};
