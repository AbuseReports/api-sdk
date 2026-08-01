/**
 * AbuseReport API SDK (JavaScript, single file)
 *
 * A tiny, dependency-free client for the AbuseReport API.
 * Works in Node.js (>=18, native fetch) and the browser.
 *
 * Docs: https://abusereport.com/api
 *
 * Usage (ESM / Node 18+ or browser with type="module"):
 *   import AbuseReport from './abusereport.js';
 *   const client = new AbuseReport('YOUR_API_KEY');
 *   const info = await client.account();
 */

class AbuseReportError extends Error {
    /**
     * @param {string} message
     * @param {object} [opts]
     * @param {number} [opts.status]   HTTP status code.
     * @param {string} [opts.error]    Machine-readable API error code.
     * @param {object} [opts.response] Full decoded response body.
     */
    constructor(message, { status = 0, error = null, response = null } = {}) {
        super(message);
        this.name = 'AbuseReportError';
        this.status = status;
        this.error = error;
        this.response = response;
    }
}

class AbuseReport {
    /**
     * @param {string} apiKey  Your API key (from https://abusereport.com/account).
     * @param {object} [options]
     * @param {string} [options.baseUrl='https://api.abusereport.com']
     * @param {number} [options.timeout=30000] Timeout in milliseconds.
     */
    constructor(apiKey, options = {}) {
        if (!apiKey) {
            throw new AbuseReportError('An API key is required');
        }
        this.apiKey = apiKey;
        this.baseUrl = (options.baseUrl || 'https://api.abusereport.com').replace(/\/+$/, '');
        this.timeout = options.timeout ?? 30000;
    }

    /**
     * Get your account information and current API usage.
     * Does not count against your quota.
     * @returns {Promise<object>}
     */
    account() {
        return this._request('GET', '/');
    }

    /**
     * Check an e-mail address or IP address for abuse insight data.
     * @param {string} value         The e-mail or IP address to check.
     * @param {string|null} [type]   Optional: force "email_address" or "ip_address".
     * @returns {Promise<object>}
     */
    check(value, type = null) {
        if (!value) {
            throw new AbuseReportError('A value to check is required');
        }
        const query = type ? { type } : {};
        // The API expects the raw value in the path; only URL-breaking
        // characters may be escaped ("@", ":" and "+" must stay literal).
        const encoded = encodeURIComponent(value)
            .replace(/%40/g, '@')
            .replace(/%3A/gi, ':')
            .replace(/%2B/gi, '+');
        return this._request('GET', `/check/${encoded}`, null, query);
    }

    /**
     * Get a list of all your submitted abuse reports.
     * @returns {Promise<object>}
     */
    reports() {
        return this._request('GET', '/reports');
    }

    /**
     * Submit a new abuse report.
     * @param {object} report
     * @param {string} report.value      The reported value (e-mail or IP address).
     * @param {string} report.type       "email_address" or "ip_address".
     * @param {string} report.abuseType  The abuse category (e.g. "ddos", "spam").
     * @param {string} [report.comment]  Optional comment (max 1024 chars).
     * @returns {Promise<object>}
     */
    submitReport({ value, type, abuseType, comment } = {}) {
        const body = {
            report_value: value,
            report_type: type,
            report_abuse_type: abuseType,
        };
        if (comment != null) {
            body.report_comment = comment;
        }
        return this._request('POST', '/reports', body);
    }

    /**
     * Delete one of your abuse reports. This cannot be undone.
     * @param {string} id The report ID.
     * @returns {Promise<object>}
     */
    deleteReport(id) {
        if (!id) {
            throw new AbuseReportError('A report id is required');
        }
        return this._request('DELETE', '/reports', { id });
    }

    /**
     * @private
     */
    async _request(method, path, body = null, query = {}) {
        let url = this.baseUrl + path;
        const qs = new URLSearchParams(query).toString();
        if (qs) url += `?${qs}`;

        const headers = {
            Authorization: `Bearer ${this.apiKey}`,
            Accept: 'application/json',
        };

        const init = { method, headers };

        if (body != null) {
            headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(body);
        }

        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), this.timeout);
        init.signal = controller.signal;

        let res;
        try {
            res = await fetch(url, init);
        } catch (err) {
            if (err.name === 'AbortError') {
                throw new AbuseReportError(`Request timed out after ${this.timeout}ms`);
            }
            throw new AbuseReportError(`Request failed: ${err.message}`);
        } finally {
            clearTimeout(timer);
        }

        let data;
        try {
            data = await res.json();
        } catch {
            throw new AbuseReportError('Invalid JSON response from API', { status: res.status });
        }

        if (!res.ok || data.success === false) {
            throw new AbuseReportError(data.error_message || 'Request failed', {
                status: res.status,
                error: data.error || null,
                response: data,
            });
        }

        return data;
    }
}

AbuseReport.AbuseReportError = AbuseReportError;

export default AbuseReport;
export { AbuseReportError };
