# Mephys website update — changes and checks

## Files updated (23)

- `.gitignore`
- `about.css`
- `about.php`
- `contact.php`
- `db.php`
- `footer.php`
- `images/adipometer.jpg`
- `images/elderly.jpg`
- `images/injuredman.jpg`
- `images/treatment.jpg`
- `index.php`
- `location.css`
- `location.php`
- `mission.php`
- `script.js`
- `services.css`
- `services.php`
- `shop/_mpesa.php`
- `shop/_public-footer.php`
- `shop/_public-header.php`
- `smtp-config.php`
- `staff-security-config.php`
- `style.css`

## New files (5)

- `CONFIGURATION.md`
- `CHANGES_AND_TESTS.md`
- `nav.css`
- `nav.js`
- `nav.php`

## Removed from the cleaned package

- Obsolete duplicate project: `Mephys/Mephys/` (nested folder inside the original project).
- Embedded Git repository and historical objects: `Mephys/.git/` (the supplied archive included it).

## What changed

- Standardized the public navigation across Home, About, Services, Location, and Shop.
- Implemented a touch-friendly sidebar with an independently scrolling Services submenu, backdrop tap / close / Escape, keyboard focus, and desktop dropdown.
- Fixed hidden or misleading links, outdated mission link, and inconsistent WhatsApp placeholder numbers.
- Fixed a location-page JavaScript error, improved service card and booking modal accessibility, and added fallback content when a detailed service description is missing.
- Optimized the four unusually large JPEG files without renaming them.
- Moved SMTP, staff security and M-Pesa keys to server environment variables and fixed M-Pesa environment variable names.
- Kept contact enquiries in the database even when email notifications are unavailable; check your staff messages dashboard until SMTP is configured.

## Validation completed

- Automated navigation interaction checks: 21/21 passing at 320px, 390px, 768px, 991px, 1024px and 1366px, across all four public clinic pages and shop header.
- Service detail modal and its Book Appointment action: passed at 390px.
- PHP syntax: all 61 first-party PHP files passed `php -l`.
- JavaScript syntax: the shared navigation and service JavaScript passed `node --check`; public pages’ inline JavaScript also passed syntax checks.
- Public pages’ internal links, anchor IDs and directly referenced assets: 0 broken in static checks.
- These are browser-emulation/static checks; actual phones, third-party CDNs, production hosting, database submissions, staff logins, customer checkout, and M-Pesa transactions still need on-server tests.

## Required actions before deployment

1. Revoke and rotate the exposed credentials from the original archive. Do not reuse them.
2. Set the server environment variables listed in `CONFIGURATION.md`. **Without them, email notifications, staff sign-in, and payments may not work.**
3. Test staff password verification after rotating `MEPHYS_STAFF_SECURITY_KEY`; existing staff credentials may require resets/migration.
4. Test the website on a physical Android and iPhone, including click-through, forms, and live checkout.
5. Ensure the document root uses the updated main `Mephys/` folder and not the obsolete nested copy.


## Mobile-width overflow follow-up — 10 November 2026

### Reason for update
Two Chrome DevTools phone screenshots showed that the menu button could be outside the visible screen and the Services cards could be shifted horizontally. This release prevents page-wide horizontal scrolling and keeps the shared mobile navigation attached to the visible viewport.

### Files updated in this follow-up
- `nav.css`: clip document-level horizontal overflow; keep fixed/sticky navigation bounded to the viewport; give the mobile hamburger a nonshrinking width and a right-aligned position; constrain the slide-out panel; override older page-specific link margins; allow narrow content and media to wrap.
- `services.css`: cap service cards, service detail modal, and appointment form within small phone viewports, including 240–359px widths.
- `index.php`, `about.php`, `services.php`, `location.php`: version local stylesheet/navigation asset references so browsers obtain the new CSS and JS rather than an older cached copy.
- `mission.php`: add the same mobile navigation as other clinic pages and a responsive page container.
- `shop/_public-header.php`: version shared navigation assets for the shop pages.

### Tests performed
- Chromium browser-emulation interaction tests on Home, About, Services, Location, and Mission at 240, 280, 320, 354, 390, 430, 768, 991, 1024, and 1366 CSS pixels: **50/50 passed**.
- Tested the Shop public navigation separately using a database-independent render of its header at the same 10 widths: **10/10 passed**.
- Tests included visible hamburger placement, document and body widths, opening/closing the mobile sidebar, expanding Services and scrolling to the last item, desktop hamburger absence, opening the service detail modal, and showing its appointment form.
- Explicitly introduced an oversized 1300px-wide test element on mobile views: horizontal page scroll stayed at 0 and the menu remained reachable.
- Screenshots checked visually at 320px and 354px. Tests used a local copy of Bootstrap 5.3.6 as a near-version equivalent of the site's Bootstrap 5.3.7 CDN, because external CDNs cannot be fetched inside this test container.

### Not tested here
- Physical phone browsers and deployed hosting/cache; actual shop/database transactions, SMTP, booking submission, staff login, or M-Pesa payments. The provided production server requires real credentials and access for those tests. Refresh/redeploy all changed PHP/CSS assets together.


## 10 November 2026 — Reception hero and one-time animated branding

- `index.php`: replaced elderly landing image / old Feel Stronger text with reception picture and live, once-only animated Mephys wordmark, clinic descriptor, and tagline. CTA links retained and accessible.
- `style.css`: removed `elderly.jpg` homepage background reference, added responsive hero composition and animations, reduced-motion fallback, kept mobile navigation unchanged.
- `images/mephys-reception-wide.webp` (new): wide image variant from the clean reception scene for desktop/tablet.
- `images/mephys-reception-mobile.webp` (new): cropped clean reception photograph for small screens.
- `images/elderly.jpg` has been removed from this release because no website code references it. It remains available in the earlier project backup ZIP.
- The logo and wording on the reception desk are in the image and are not animated/changed.
