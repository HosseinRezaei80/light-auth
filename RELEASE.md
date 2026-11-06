# Light Auth OTP v3.6.1 — GitHub release notes

Light Auth OTP is a lightweight, Persian-first WordPress plugin for mobile OTP login and registration with Melipayamak.

## Highlights

- Refreshed, responsive RTL WordPress admin screen with brand-consistent rose/plum colors and cleaner typography.
- Cache-safe AJAX nonce bootstrap; no expiring nonce embedded in page cache.
- Dedicated admin CSS and JavaScript loaded only on the settings screen.
- WordPress plugin metadata, GPL-2.0-or-later license, security policy and privacy disclosure.
- Dependency-free PHP smoke tests and GitHub Actions CI.
- Preserves existing shortcodes, option keys and frontend login UI.

## Updating

Back up your WordPress database and files, install `light-auth-3.6.1.zip` using Plugins → Add New → Upload Plugin, replace the current version, and check your saved Melipayamak settings. Test a real SMS, registration, login, and the site's cache behavior on staging before updating production.

## Security

No public security audit or full WordPress integration test is implied by this release. Keep SMS credentials private, use HTTPS, and configure site-level abuse protection. Refer to [SECURITY.md](SECURITY.md) for private reporting instructions.

## For repository maintainers

1. Create a public GitHub repository (suggested name `light-auth`).
2. Upload **the contents of the source ZIP's `light-auth/` folder** to the repository root, including hidden `.github/` and `.gitignore`.
3. Change the `Author` plugin-header field and README ownership details if you prefer personal attribution.
4. Enable GitHub's **Private Vulnerability Reporting** before inviting security reports.
5. Verify all GitHub Actions checks pass, then create a tag `v3.6.1` and attach the install ZIP to the release.
6. Do **not** commit real API credentials or distribute font files without an appropriate redistribution license.
