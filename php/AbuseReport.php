<?php

/**
 * AbuseReport API SDK (PHP, single file)
 *
 * A tiny, dependency-free client for the AbuseReport API.
 * Requires the cURL extension (bundled with most PHP installs).
 *
 * Docs: https://abusereport.com/api
 *
 * Usage:
 *   require_once 'AbuseReport.php';
 *   $client = new AbuseReport('YOUR_API_KEY');
 *   $info   = $client->account();
 *   $result = $client->check('mail@example.com');
 */

/**
 * Thrown for any API or transport error.
 * The API error code is in ->error, the HTTP status in ->getCode().
 */
class AbuseReportException extends Exception
{
    /** @var string|null Machine-readable error code returned by the API (e.g. "invalid_check_value"). */
    public $error;

    /** @var array|null The full decoded response body, if any. */
    public $response;

    public function __construct($message, $httpStatus = 0, $error = null, $response = null)
    {
        parent::__construct($message, $httpStatus);
        $this->error = $error;
        $this->response = $response;
    }
}

class AbuseReport
{
    /** @var string */
    private $apiKey;

    /** @var string */
    private $baseUrl;

    /** @var int Request timeout in seconds. */
    private $timeout;

    /**
     * @param string $apiKey  Your API key (from https://abusereport.com/account).
     * @param array  $options Optional: ['base_url' => '...', 'timeout' => 30].
     */
    public function __construct($apiKey, array $options = [])
    {
        if (empty($apiKey)) {
            throw new AbuseReportException('An API key is required');
        }

        $this->apiKey  = $apiKey;
        $this->baseUrl = rtrim($options['base_url'] ?? 'https://api.abusereport.com', '/');
        $this->timeout = $options['timeout'] ?? 30;
    }

    /**
     * Get your account information and current API usage.
     * Does not count against your quota.
     *
     * @return array
     */
    public function account()
    {
        return $this->request('GET', '/');
    }

    /**
     * Check an e-mail address or IP address for abuse insight data.
     *
     * @param string      $value The e-mail or IP address to check.
     * @param string|null $type  Optional: force "email_address" or "ip_address".
     * @return array
     */
    public function check($value, $type = null)
    {
        if (empty($value)) {
            throw new AbuseReportException('A value to check is required');
        }

        // The API expects the raw value in the path; only URL-breaking
        // characters may be escaped ("@", ":" and "+" must stay literal).
        $path = '/check/' . strtr(rawurlencode($value), [
            '%40' => '@',
            '%3A' => ':',
            '%2B' => '+',
        ]);
        $query = $type ? ['type' => $type] : [];

        return $this->request('GET', $path, null, $query);
    }

    /**
     * Get a list of all your submitted abuse reports.
     *
     * @return array
     */
    public function reports()
    {
        return $this->request('GET', '/reports');
    }

    /**
     * Submit a new abuse report.
     *
     * @param string      $value     The reported value (e-mail or IP address).
     * @param string      $type      "email_address" or "ip_address".
     * @param string      $abuseType The abuse category (e.g. "ddos", "spam").
     * @param string|null $comment   Optional comment (max 1024 chars).
     * @return array
     */
    public function submitReport($value, $type, $abuseType, $comment = null)
    {
        $body = [
            'report_value'      => $value,
            'report_type'       => $type,
            'report_abuse_type' => $abuseType,
        ];

        if ($comment !== null) {
            $body['report_comment'] = $comment;
        }

        return $this->request('POST', '/reports', $body);
    }

    /**
     * Delete one of your abuse reports. This cannot be undone.
     *
     * @param string $id The report ID.
     * @return array
     */
    public function deleteReport($id)
    {
        if (empty($id)) {
            throw new AbuseReportException('A report id is required');
        }

        return $this->request('DELETE', '/reports', ['id' => $id]);
    }

    /**
     * Perform an HTTP request and decode the JSON response.
     *
     * @param string     $method
     * @param string     $path
     * @param array|null $body
     * @param array      $query
     * @return array
     */
    private function request($method, $path, $body = null, array $query = [])
    {
        $url = $this->baseUrl . $path;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errNo  = curl_errno($ch);
        $errMsg = curl_error($ch);
        curl_close($ch);

        if ($errNo) {
            throw new AbuseReportException('Request failed: ' . $errMsg, 0);
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            throw new AbuseReportException('Invalid JSON response from API', $status, null, null);
        }

        if ($status >= 400 || (isset($data['success']) && $data['success'] === false)) {
            $message = $data['error_message'] ?? 'Request failed';
            $error   = $data['error'] ?? null;
            throw new AbuseReportException($message, $status, $error, $data);
        }

        return $data;
    }
}
