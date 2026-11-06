# Contributing to Light Auth OTP

This project is Persian-first; interface text may be Persian and code comments may be bilingual.

1. Open an issue for general changes (not security vulnerabilities).
2. Keep frontend and admin dependencies minimal; do not add external analytics or third-party assets.
3. Sanitize incoming input, escape output and validate capabilities on privileged actions.
4. Verify backward compatibility for `[light_auth]`, `[light_auth_btn]`, and current option names.
5. Run `php tests/smoke.php`, `php -l light-auth.php`, and `node --check script.js && node --check admin.js` before submitting changes.
6. Add documentation or tests for behavior changes. Test with a real WordPress staging site before release.

For sensitive vulnerability reports, read [SECURITY.md](SECURITY.md).
