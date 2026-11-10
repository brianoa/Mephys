# Mephys deployment settings

Before publishing the corrected website, configure these variables **on the server**, not in PHP source or source control:

- Database: `MEPHYS_DB_HOST`, `MEPHYS_DB_NAME`, `MEPHYS_DB_USER`, `MEPHYS_DB_PASSWORD`.
- Emails: `MEPHYS_SMTP_HOST`, `MEPHYS_SMTP_PORT`, `MEPHYS_SMTP_USERNAME`, `MEPHYS_SMTP_PASSWORD`, `MEPHYS_SMTP_ENCRYPTION`, `MEPHYS_ADMIN_EMAIL`.
- Staff authentication: `MEPHYS_STAFF_SECURITY_KEY` — generate a strong new value of at least 64 characters. Changing the key invalidates old password-derived checks and may require staff credential migration or resets; test the admin login carefully before rollout.
- M-Pesa: `MEPHYS_MPESA_ENV`, `MEPHYS_MPESA_CONSUMER_KEY`, `MEPHYS_MPESA_CONSUMER_SECRET`, `MEPHYS_MPESA_SHORTCODE`, `MEPHYS_MPESA_PASSKEY`, `MEPHYS_MPESA_CALLBACK_URL`, `MEPHYS_MPESA_ACCOUNT_REFERENCE` (optional), `MEPHYS_MPESA_TRANSACTION_TYPE` (optional).

**Important:** Old SMTP credentials, a staff security key, and M-Pesa values were present in the original ZIP. Treat them as exposed; rotate/revoke credentials with the providers and avoid deploying the original ZIP or its `.git` history. Do not reuse those values. After setting new keys, test customer emails, staff access and sandbox M-Pesa transactions before going live.

The updated project omits the redundant nested old `Mephys/` copy and Git history to avoid deploying stale files and secrets. Keep `vendor/` and the current root `Mephys/` project directory. Upload its **contents** to your intended web root (or configure the web root to point to it).

## Navigation checks
1. On a phone-width screen, tap the three-line icon. A scrollable side menu should open.
2. Tap **Services**. The submenu should expand and scroll; service links should land on the appropriate service card.
3. Check **Products**, **About Us**, **Location**, and **Home**; menu closes on selection.
4. Close with the X, backdrop tap, or Escape. On desktop the horizontal menu and services dropdown remain available.

## Further deployment checks
- The PHP forms and shop require a configured MySQL database and proper tables/data; they cannot be fully tested with the source ZIP alone.
- Bootstrap, icons, AOS, and SweetAlert2 are loaded via internet CDNs and may not appear offline.
- Update privacy/contact details, store policies, customer sign-in flow, and payment callback domain with the actual production details before launch.
