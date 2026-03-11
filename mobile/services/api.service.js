// RiceGuard API Service Layer
import { ENDPOINTS } from './api.config';

// ── Auth ──────────────────────────────────────────────────
export const authService = {
    async login(contactNumber, password) {
        const res = await fetch(ENDPOINTS.LOGIN, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ contact_number: contactNumber, password }),
        });
        return res.json();
    },

    async register(userData) {
        const res = await fetch(ENDPOINTS.REGISTER, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(userData),
        });
        return res.json();
    },
};

// ── Reports ───────────────────────────────────────────────
export const reportService = {
    async getReports(filters = {}) {
        const params = new URLSearchParams(filters).toString();
        const res = await fetch(`${ENDPOINTS.REPORTS}?${params}`);
        return res.json();
    },

    async getReport(reportId) {
        const res = await fetch(`${ENDPOINTS.REPORTS}?report_id=${reportId}`);
        return res.json();
    },

    async createReport(formData) {
        const res = await fetch(ENDPOINTS.REPORTS, {
            method: 'POST',
            body: formData, // FormData with images
        });
        return res.json();
    },

    async updateStatus(reportId, status) {
        const res = await fetch(ENDPOINTS.REPORTS, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ report_id: reportId, status }),
        });
        return res.json();
    },
};

// ── AI Diagnosis ──────────────────────────────────────────
export const aiService = {
    async getModels() {
        const res = await fetch(ENDPOINTS.AI_MODELS);
        return res.json();
    },

    async getDiseases() {
        const res = await fetch(ENDPOINTS.DISEASES);
        return res.json();
    },

    async predict(imageUri, modelName, reportId = null) {
        const formData = new FormData();
        formData.append('image', {
            uri: imageUri,
            type: 'image/jpeg',
            name: 'leaf_photo.jpg',
        });
        formData.append('model', modelName);
        if (reportId) formData.append('report_id', reportId.toString());

        const res = await fetch(ENDPOINTS.AI_PREDICT, {
            method: 'POST',
            body: formData,
        });
        return res.json();
    },

    async compare(imageUri, reportId = null) {
        const formData = new FormData();
        formData.append('image', {
            uri: imageUri,
            type: 'image/jpeg',
            name: 'leaf_photo.jpg',
        });
        if (reportId) formData.append('report_id', reportId.toString());

        const res = await fetch(ENDPOINTS.AI_COMPARE, {
            method: 'POST',
            body: formData,
        });
        return res.json();
    },

    async getDiagnosis(reportId) {
        const res = await fetch(`${ENDPOINTS.DIAGNOSIS}?report_id=${reportId}`);
        return res.json();
    },
};

// ── Verification ──────────────────────────────────────────
export const verificationService = {
    async getVerifications(reportId = null) {
        const params = reportId ? `?report_id=${reportId}` : '';
        const res = await fetch(`${ENDPOINTS.VERIFICATION}${params}`);
        return res.json();
    },

    async createVerification(data) {
        const res = await fetch(ENDPOINTS.VERIFICATION, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        return res.json();
    },
};

// ── Treatments ────────────────────────────────────────────
export const treatmentService = {
    async getTreatments(diseaseId = null) {
        const params = diseaseId ? `?disease_id=${diseaseId}` : '';
        const res = await fetch(`${ENDPOINTS.TREATMENTS}${params}`);
        return res.json();
    },

    async getRecommendations(reportId) {
        const res = await fetch(`${ENDPOINTS.TREATMENTS}?action=recommendations&report_id=${reportId}`);
        return res.json();
    },

    async createRecommendation(data) {
        const res = await fetch(ENDPOINTS.TREATMENTS, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        return res.json();
    },
};

// ── Insurance Claims ──────────────────────────────────────
export const claimService = {
    async getClaims(filters = {}) {
        const params = new URLSearchParams(filters).toString();
        const res = await fetch(`${ENDPOINTS.CLAIMS}?${params}`);
        return res.json();
    },

    async createClaim(data) {
        const res = await fetch(ENDPOINTS.CLAIMS, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        return res.json();
    },
};

// ── Users ─────────────────────────────────────────────────
export const userService = {
    async getUser(userId) {
        const res = await fetch(`${ENDPOINTS.USERS}?user_id=${userId}`);
        return res.json();
    },

    async updateUser(data) {
        const res = await fetch(ENDPOINTS.USERS, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        return res.json();
    },
};

// ── Stats ─────────────────────────────────────────────────
export const statsService = {
    async getOverview() {
        const res = await fetch(`${ENDPOINTS.STATS}?action=overview`);
        return res.json();
    },

    async getDiseases() {
        const res = await fetch(`${ENDPOINTS.STATS}?action=diseases`);
        return res.json();
    },
};
