# AbuseReport SDK

Lightweight, dependency-free SDKs for the [AbuseReport API](https://abusereport.com/api) —
one single file per programming language. Check e-mail addresses and IPs for abuse insight data,
submit abuse reports and read your account usage.

```
abusereport-sdk/
├── php/      AbuseReport.php
├── js/       abusereport.js  (+ package.json)
└── python/   abusereport.py
```

## Authentication

Every request is authenticated with your API key as a **Bearer** token. Grab your key
from the [account page](https://abusereport.com/account). All SDKs send it automatically.

The base URL is `https://api.abusereport.com`.

## API surface

All three SDKs expose the same methods:

| Method                                   | HTTP                       | Description                                   |
| ---------------------------------------- | -------------------------- | --------------------------------------------- |
| `account()`                              | `GET /`                    | Account info & current usage (no quota cost). |
| `check(value, type?)`                    | `GET /check/{value}`       | Abuse insight for an e-mail or IP address.    |
| `reports()`                              | `GET /reports`             | List your submitted abuse reports.            |
| `submitReport(value, type, abuseType, comment?)` | `POST /reports`    | Submit a new abuse report.                    |
| `deleteReport(id)`                       | `DELETE /reports`          | Delete one of your reports (irreversible).    |

- `type` is optional. The check endpoint auto-detects e-mail vs. IP; pass
  `"email_address"` or `"ip_address"` to force it.
- `report_type` for `submitReport` must be `"email_address"` or `"ip_address"`.
- Errors raise a typed exception carrying the API error code, message and HTTP status.

---

## PHP

Requires PHP 7.1+ with the cURL extension.

```php
<?php
require_once 'php/AbuseReport.php';

$client = new AbuseReport('YOUR_API_KEY');

try {
    // Account info & usage
    $account = $client->account();
    echo $account['requests_remaining'] . " requests left\n";

    // Check an e-mail or IP
    $result = $client->check('mail@example.com');
    echo "Risk score: " . $result['risk_score'] . "\n";

    // Force a type
    $ip = $client->check('8.8.8.8', 'ip_address');

    // List reports
    $reports = $client->reports();

    // Submit a report
    $report = $client->submitReport(
        'support@abusereport.com', // value
        'email_address',           // type
        'ddos',                    // abuse type
        'optional comment'         // comment (optional)
    );
    echo "Created report: " . $report['report_id'] . "\n";

    // Delete a report
    $client->deleteReport($report['report_id']);

} catch (AbuseReportException $e) {
    // $e->error  -> machine code, e.g. "invalid_check_value"
    // $e->getCode() -> HTTP status
    echo "Error ({$e->error}): " . $e->getMessage() . "\n";
}
```

Optional config: `new AbuseReport('KEY', ['base_url' => '...', 'timeout' => 30]);`

---

## JavaScript

ESM module. Works in Node.js 18+ (native `fetch`) and modern browsers
(`<script type="module">`). The bundled `package.json` sets `"type": "module"`.

```js
import AbuseReport, { AbuseReportError } from './abusereport.js';

const client = new AbuseReport('YOUR_API_KEY');

try {
    // Account info & usage
    const account = await client.account();
    console.log(`${account.requests_remaining} requests left`);

    // Check an e-mail or IP
    const result = await client.check('mail@example.com');
    console.log('Risk score:', result.risk_score);

    // Force a type
    const ip = await client.check('8.8.8.8', 'ip_address');

    // List reports
    const reports = await client.reports();

    // Submit a report
    const report = await client.submitReport({
        value: 'support@abusereport.com',
        type: 'email_address',
        abuseType: 'ddos',
        comment: 'optional comment', // optional
    });
    console.log('Created report:', report.report_id);

    // Delete a report
    await client.deleteReport(report.report_id);

} catch (err) {
    if (err instanceof AbuseReportError) {
        // err.error -> machine code, err.status -> HTTP status
        console.error(`Error (${err.error}):`, err.message);
    } else {
        throw err;
    }
}
```

Optional config: `new AbuseReport('KEY', { baseUrl: '...', timeout: 30000 });`

---

## Python

Built on [`requests`](https://pypi.org/project/requests/). Python 3.6+.

```bash
pip install -r python/requirements.txt   # or: pip install requests
```

```python
from abusereport import AbuseReport, AbuseReportError

client = AbuseReport("YOUR_API_KEY")

try:
    # Account info & usage
    account = client.account()
    print(account["requests_remaining"], "requests left")

    # Check an e-mail or IP
    result = client.check("mail@example.com")
    print("Risk score:", result["risk_score"])

    # Force a type
    ip = client.check("8.8.8.8", type="ip_address")

    # List reports
    reports = client.reports()

    # Submit a report
    report = client.submit_report(
        value="support@abusereport.com",
        type="email_address",
        abuse_type="ddos",
        comment="optional comment",  # optional
    )
    print("Created report:", report["report_id"])

    # Delete a report
    client.delete_report(report["report_id"])

except AbuseReportError as e:
    # e.error -> machine code, e.status -> HTTP status
    print(f"Error ({e.error}): {e}")
```

Optional config: `AbuseReport("KEY", base_url="...", timeout=30)`

---

## Response examples

**Account info**

```json
{
    "success": true,
    "username": "AbuseReport",
    "email_address": "support@abusereport.com",
    "plan": "Business",
    "requests_usage_limit": 50000,
    "requests_usage": 431,
    "requests_remaining": 49569
}
```

**E-mail check** returns `risk_score`, `inbox`, `domain`, `tld`, `reachable`, `disposable`,
`mx`, `spf`, `dmarc` and a `reports` array. **IP check** returns `risk_score`, geo-location
(`country`, `state`, `city`, `zipcode`, `timezone`), `isp`, `asn`, an `abuse` contact object,
`proxy`, `hosting` and `reports`.

## HTTP status codes

| Code  | Meaning                                                       |
| ----- | ------------------------------------------------------------- |
| `200` | OK                                                            |
| `400` | Bad request / missing parameters                             |
| `403` | Invalid API key or insufficient permissions                  |
| `404` | Endpoint not found                                           |
| `429` | Monthly request limit reached — upgrade your plan            |
| `500` | Server error                                                  |

Your monthly request limit depends on your plan and resets on the 1st of each month.
Account/report metadata calls do **not** count against your quota.

## License

MIT
