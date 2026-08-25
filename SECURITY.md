# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in MXRoute Mailer, please report it responsibly.

**Do not open a public GitHub issue for security vulnerabilities.**

Instead, email: **security@richardkentgates.com**

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

## Response Timeline

- **Acknowledgment**: Within 48 hours
- **Initial assessment**: Within 1 week
- **Fix or mitigation**: Depends on severity, typically within 2 weeks for critical issues

## Scope

This policy covers the MXRoute Mailer WordPress plugin distributed via:
- GitHub Releases (github.com/richardkentgates/mxroute-mailer)
- Gap Creek Media apt server

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.4.x   | Yes       |

## Security Considerations

MXRoute Mailer routes WordPress email through MXRoute's HTTPS API instead of SMTP. Key security notes:

- **Credential storage**: MXRoute credentials are stored in `wp_options`. The password is encrypted with AES-256-GCM (via OpenSSL, key derived from site salts) before storage. Ensure your database is secured.
- **Account password, not API key**: The plugin authenticates with your MXRoute account username and password — the same credentials used for SMTP. There is no separate API key.
- **No SMTP ports required**: The plugin uses HTTPS exclusively for plain emails. Emails with attachments fall back to outbound SMTP (port 465/587/2525) because the API cannot carry attachments.
- **HTTPS only**: All API communication uses TLS 1.2+
- **Log retention**: Sent emails are stored in a dedicated logs table (subject, body, headers, attachments metadata) for review/re-queue; the logging toggle discards delivered-email records when disabled. Failures are always retained. Uninstall removes all data.
- **Batch throttling**: Queue processing sends in configurable small batches per cron cycle; no additional API rate limiting is implemented

## Best Practices

1. Keep the plugin updated to the latest version
2. Use a dedicated MXRoute mailbox for sending rather than a personal account
3. Restrict WordPress admin access to trusted users
4. Monitor MXRoute dashboard for unusual sending patterns
5. Enable MXRoute's IP restriction if available
