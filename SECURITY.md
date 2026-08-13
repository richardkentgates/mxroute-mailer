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

- **API key storage**: MXRoute API credentials are stored in `wp_options` with WordPress encryption. Ensure your database is secured.
- **No SMTP ports**: The plugin uses HTTPS exclusively — no outbound SMTP ports are required
- **HTTPS only**: All API communication uses TLS 1.2+
- **No email content logging**: Email content is not logged or stored beyond what WordPress core does
- **Rate limiting**: Respects MXRoute API rate limits

## Best Practices

1. Keep the plugin updated to the latest version
2. Use a dedicated MXRoute API key (not your main account password)
3. Restrict WordPress admin access to trusted users
4. Monitor MXRoute dashboard for unusual sending patterns
5. Enable MXRoute's IP restriction if available
