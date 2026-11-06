# Security Policy — Light Auth OTP

## Reporting a vulnerability

Please **do not** publish working exploits, OTPs, API credentials, phone numbers, personal data, or reproduction details that could endanger live websites in a public GitHub issue.

Before promoting a repository to public use, the maintainer should enable **GitHub Private Vulnerability Reporting** in the repository security settings. Please use the repository's **Security → Report a vulnerability** option (when enabled). If that option is not configured, contact the repository maintainer via an agreed private channel before sharing sensitive technical details.

No private security inbox or response-time guarantee has been configured in this template.

## Security considerations

- Use HTTPS and configure the server/proxy to pass trusted client addresses.
- Protect the Melipayamak account credentials; never commit them to Git.
- Set WAF and platform rate-limits appropriate for your traffic and SMS budget.
- OTP is not suitable as the only login factor for privileged WordPress roles; Light Auth blocks those roles.
- Keep WordPress, PHP, WooCommerce, and your plugins updated.
- Verify behavior on a staging site before deploying any update.

## Scope

This repository contains the Light Auth PHP and JavaScript plugin code, not the Melipayamak service or WordPress core. The included test suite is smoke-level and does not constitute an independent security assessment.
