# Mephys: M-Pesa and delivery setup (2026-11-10)

## Why checkout was failing
`shop/_mpesa.php` mistakenly used credential *values* and an ngrok URL as the names passed to `getenv()`. PHP therefore returned missing configuration; checkout saved an unpaid order and showed `callback_url is missing`.
The corrected code reads standard server environment variables and checks them **before saving a new order**. A failed existing order now has a **Retry M-Pesa payment for this order** action on its order-status page.

## Important security action
The uploaded original project contained Daraja credential-like strings (and Git history). **Rotate the old consumer secret, key, and passkey if they were active.** Never paste credentials into PHP source, Git, screenshots, or a public URL. This updated ZIP excludes `.git` and the nested obsolete Mephys copy.

## PowerShell: sandbox setup for a local PHP development server
Open **one PowerShell window** for the PHP server, and set these environment variables using your *new* Daraja sandbox credentials (values shown here are placeholders):

```powershell
$env:MEPHYS_MPESA_ENV = 'sandbox'
$env:MEPHYS_MPESA_CONSUMER_KEY = 'YOUR_NEW_CONSUMER_KEY'
$env:MEPHYS_MPESA_CONSUMER_SECRET = 'YOUR_NEW_CONSUMER_SECRET'
$env:MEPHYS_MPESA_SHORTCODE = '174379'
$env:MEPHYS_MPESA_PASSKEY = 'YOUR_NEW_SANDBOX_PASSKEY'
$env:MEPHYS_MPESA_CALLBACK_URL = 'https://YOUR-ACTIVE-TUNNEL.ngrok-free.app/shop/mpesa-callback.php'
$env:MEPHYS_MPESA_TRANSACTION_TYPE = 'CustomerPayBillOnline'
php check-mpesa.php
php -S localhost:8000
```

In a **second PowerShell window** start the public HTTPS tunnel:

```powershell
ngrok http 8000
```

Copy the *current* HTTPS ngrok URL into the callback variable in the **first** window and restart `php -S localhost:8000`. Free ngrok URLs may change on restart. If your project is served from a subfolder, include that prefix in the callback path. The callback must be internet-reachable; `localhost` is not a valid Daraja callback.

For deployments you can set `MEPHYS_PUBLIC_BASE_URL=https://your-domain.example` instead of `MEPHYS_MPESA_CALLBACK_URL` and the app will append `/shop/mpesa-callback.php`. Use **production** credentials, your own production shortcode and passkey, and a durable public HTTPS host for real payments.

To verify the callback is reachable, open the URL publicly. A `GET` request returning an invalid callback JSON error is expected because Daraja delivers a `POST`; it does **not** confirm that Safaricom will send a valid callback.

A `READY` output from `php check-mpesa.php` confirms that the *fields* are populated, not that the keys are valid or that STK push succeeded. Complete a real **sandbox test** and check the callback and order-status records before going live. We cannot test real Safaricom requests without your private keys and networked services.

## Checkout: Use my current location
On `http://localhost:8000` or HTTPS, allow location permission. The button sets latitude and longitude, places a map pin if the map loads, and adds a Google Maps link to **How can our delivery person find you?**. A single address lookup suggests county, town, neighbourhood and street, then appends them to the directions. If lookup fails, the map link is still populated. The customer should **review and correct** all suggested details, then add gate, apartment, house number, floor and landmark manually. Geolocation and reverse geocoding cannot determine these reliably.

Location names come from the OpenStreetMap Nominatim public service, which has usage limits and may be unavailable. For a high-volume production checkout, switch to a provider with an appropriate commercial SLA or deploy a cached server-side service. Coordinates are shared with that geocoding service when users choose a location.

## Testing and safeguards
- Do not accidentally pay twice: only retry after a failed attempt and when no M-Pesa receipt was received.
- Payment confirmation still depends on a public callback and valid Daraja credentials.
- No changes to mobile navigation sizing, sidebar transitions, or the reception imagery.
- Brand teal: `#087B88`, dark teal `#07505B`, complementary `#087D8B`.
