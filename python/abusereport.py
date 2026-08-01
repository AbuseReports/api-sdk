"""
AbuseReport API SDK (Python, single file)

A tiny client for the AbuseReport API, built on the `requests` library.

Install the dependency:
    pip install requests

Docs: https://abusereport.com/api

Usage:
    from abusereport import AbuseReport

    client = AbuseReport("YOUR_API_KEY")
    info = client.account()
    result = client.check("mail@example.com")
"""

import requests


class AbuseReportError(Exception):
    """Raised for any API or transport error.

    Attributes:
        status:   HTTP status code (0 for transport errors).
        error:    Machine-readable error code returned by the API.
        response: The full decoded response body, if any.
    """

    def __init__(self, message, status=0, error=None, response=None):
        super().__init__(message)
        self.status = status
        self.error = error
        self.response = response


class AbuseReport:
    def __init__(self, api_key, base_url="https://api.abusereport.com", timeout=30):
        """
        Args:
            api_key:  Your API key (from https://abusereport.com/account).
            base_url: API base URL.
            timeout:  Request timeout in seconds.
        """
        if not api_key:
            raise AbuseReportError("An API key is required")
        self.api_key = api_key
        self.base_url = base_url.rstrip("/")
        self.timeout = timeout
        self.session = requests.Session()
        self.session.headers.update({
            "Authorization": "Bearer " + api_key,
            "Accept": "application/json",
        })

    def account(self):
        """Get your account information and current API usage.

        Does not count against your quota.
        """
        return self._request("GET", "/")

    def check(self, value, type=None):
        """Check an e-mail address or IP address for abuse insight data.

        Args:
            value: The e-mail or IP address to check.
            type:  Optional, force "email_address" or "ip_address".
        """
        if not value:
            raise AbuseReportError("A value to check is required")
        params = {"type": type} if type else None
        # The API expects the raw value in the path; only URL-breaking
        # characters may be escaped ("@", ":" and "+" must stay literal).
        path = "/check/" + requests.utils.quote(value, safe="@:+")
        return self._request("GET", path, params=params)

    def reports(self):
        """Get a list of all your submitted abuse reports."""
        return self._request("GET", "/reports")

    def submit_report(self, value, type, abuse_type, comment=None):
        """Submit a new abuse report.

        Args:
            value:      The reported value (e-mail or IP address).
            type:       "email_address" or "ip_address".
            abuse_type: The abuse category (e.g. "ddos", "spam").
            comment:    Optional comment (max 1024 chars).
        """
        body = {
            "report_value": value,
            "report_type": type,
            "report_abuse_type": abuse_type,
        }
        if comment is not None:
            body["report_comment"] = comment
        return self._request("POST", "/reports", body=body)

    def delete_report(self, report_id):
        """Delete one of your abuse reports. This cannot be undone."""
        if not report_id:
            raise AbuseReportError("A report id is required")
        return self._request("DELETE", "/reports", body={"id": report_id})

    def _request(self, method, path, body=None, params=None):
        url = self.base_url + path

        try:
            resp = self.session.request(
                method,
                url,
                params=params,
                json=body,
                timeout=self.timeout,
            )
        except requests.RequestException as e:
            raise AbuseReportError("Request failed: {}".format(e)) from e

        try:
            decoded = resp.json()
        except ValueError as e:
            raise AbuseReportError(
                "Invalid JSON response from API", status=resp.status_code
            ) from e

        if resp.status_code >= 400 or decoded.get("success") is False:
            raise AbuseReportError(
                decoded.get("error_message", "Request failed"),
                status=resp.status_code,
                error=decoded.get("error"),
                response=decoded,
            )

        return decoded
