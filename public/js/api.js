/**
 * PrintGO Kiosk & Admin API Client
 * Interfaces with Laravel REST API endpoints under /api/v1/
 */
class PrintGoAPI {
  static baseUrl = '/api/v1';

  static getAuthToken() {
    return localStorage.getItem('printgo_admin_token') || null;
  }

  static setAuthToken(token) {
    if (token) {
      localStorage.setItem('printgo_admin_token', token);
    } else {
      localStorage.removeItem('printgo_admin_token');
    }
  }

  static async request(endpoint, options = {}) {
    const url = `${this.baseUrl}${endpoint}`;
    const headers = {
      'Accept': 'application/json',
      ...options.headers,
    };

    const token = this.getAuthToken();
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    if (options.body && !(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }

    try {
      const response = await fetch(url, { ...options, headers });
      const data = await response.json().catch(() => null);

      if (!response.ok) {
        const error = (data && data.error) ? data.error : {
          code: 'HTTP_' + response.status,
          message: response.statusText || 'An unexpected error occurred.'
        };
        throw error;
      }

      return data;
    } catch (err) {
      console.error(`API Error on [${options.method || 'GET'} ${endpoint}]:`, err);
      throw err;
    }
  }

  // --- Public / Kiosk Endpoints ---

  static async getSystemStatus() {
    return this.request('/system/status');
  }

  static async getPrinterStatus() {
    return this.request('/printer/status');
  }

  static async getAnnouncements() {
    return this.request('/announcements');
  }

  static async uploadFile(file, sessionId = null) {
    const formData = new FormData();
    formData.append('file', file);
    if (sessionId) {
      formData.append('session_id', sessionId);
    }

    return this.request('/files', {
      method: 'POST',
      body: formData,
    });
  }

  static async getFileStatus(fileId) {
    return this.request(`/files/${fileId}`);
  }

  static async getFilePreview(fileId) {
    return this.request(`/files/${fileId}/preview`);
  }

  static async calculatePrice(options) {
    return this.request('/print/price', {
      method: 'POST',
      body: options,
    });
  }

  static async submitPrintJob(jobData) {
    return this.request('/print/jobs', {
      method: 'POST',
      body: jobData,
    });
  }

  static async getJobStatus(jobId) {
    return this.request(`/print/jobs/${jobId}`);
  }

  static async getPaymentStatus(sessionId) {
    return this.request(`/payment/${sessionId}`);
  }

  // --- Hardware Simulation / Agent Webhook ---

  static async insertCoin(sessionId, amount, source = 'coin') {
    return this.request('/agent/payment/confirm', {
      method: 'POST',
      body: {
        session_id: sessionId,
        amount: Number(amount),
        source: source,
      },
    });
  }

  static async getPendingJobs() {
    return this.request('/agent/jobs/pending');
  }

  static async updateJobStatus(jobId, status, pagesCompleted = null, errorMessage = null) {
    return this.request(`/agent/jobs/${jobId}/status`, {
      method: 'PUT',
      body: {
        status,
        pages_completed: pagesCompleted,
        error_message: errorMessage,
      },
    });
  }

  // --- Admin Endpoints ---

  static async adminLogin(login, password) {
    const res = await this.request('/admin/login', {
      method: 'POST',
      body: { login, password },
    });

    if (res && res.data && res.data.token) {
      this.setAuthToken(res.data.token);
    }

    return res;
  }

  static async adminLogout() {
    try {
      await this.request('/admin/logout', { method: 'POST' });
    } finally {
      this.setAuthToken(null);
    }
  }

  static async getDashboardMetrics() {
    return this.request('/admin/dashboard');
  }

  static async getTransactions(params = {}) {
    const qs = new URLSearchParams(params).toString();
    return this.request(`/admin/transactions${qs ? '?' + qs : ''}`);
  }

  static async getPricingMatrix() {
    return this.request('/admin/pricing');
  }

  static async updatePricingMatrix(pricing) {
    return this.request('/admin/pricing', {
      method: 'PUT',
      body: { pricing },
    });
  }

  static async getMaintenanceStatus() {
    return this.request('/admin/maintenance');
  }

  static async updateMaintenance(enabled, message = null) {
    return this.request('/admin/maintenance', {
      method: 'PUT',
      body: { enabled, message },
    });
  }

  static async getAdminAnnouncements() {
    return this.request('/admin/announcements');
  }

  static async createAnnouncement(data) {
    return this.request('/admin/announcements', {
      method: 'POST',
      body: data,
    });
  }

  static async deleteAnnouncement(id) {
    return this.request(`/admin/announcements/${id}`, {
      method: 'DELETE',
    });
  }
}

window.PrintGoAPI = PrintGoAPI;
