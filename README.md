# Nahnu Asset Scanner

**Version:** 1.0.2
**Author:** [Nahnu Plugins](https://www.nahnuplugins.com)
**License:** GPL-2.0-or-later
**Requires:** WordPress 7.1+, PHP 8.3+

---

Scan your WordPress site front end to inventory every JS file, CSS file, cookie, and external domain. Fully self-contained — no external requests, no API keys, no account required.

## What it does

| Tab | What you get |
|---|---|
| **Scripts** | Every script — path, size, inline/external, first/third-party, owning plugin, Test column |
| **Styles** | Every stylesheet with the same attribution detail, Test column |
| **Cookies** | Every cookie set, categorized (Necessary/Preferences/Analytics/Marketing), matched to its plugin |
| **Domains** | Every third-party domain contacted during the scan |

All results are exportable as CSV.

## Test Lab

Open any front-end URL with `?nahnuas_test=1` as an administrator (or click **Test assets** in the admin bar). A floating panel (Shadow DOM) lists every script and stylesheet on the page; set each to **On / Defer / Delay / Off**, apply, and see the JavaScript errors it causes. `?nahnuas_test=pause` shows the page without rules for comparison.

- Rules are stored per user in the `nahnuas_test_rules` user meta and only apply to that user's requests carrying the parameter.
- Pages are rewritten in an output buffer with `WP_HTML_Tag_Processor`; scripts are held back by changing `type`, stylesheets via `rel` / `media`. `public/test-runtime.js` runs delayed and deferred code.
- Test pages send no-cache headers and define `DONOTCACHEPAGE`.

## Abilities (MCP)

Registered on `wp_abilities_api_init` in category `nahnu-asset-scanner`; all `public` (REST + MCP), all require `manage_options`.

| Ability | Read-only | What it does |
|---|---|---|
| `nahnu-asset-scanner/get-scan-summary` | yes | Latest scan totals and scanned URLs |
| `nahnu-asset-scanner/run-scan` | no | Scan up to 10 URLs / post IDs synchronously |
| `nahnu-asset-scanner/get-results` | yes | Paged js / css / cookies / domains, with `rule_key` |
| `nahnu-asset-scanner/get-page-assets` | yes | Every script/style on a page with rule keys |
| `nahnu-asset-scanner/get-test-rules` | yes | Current user's rules |
| `nahnu-asset-scanner/set-test-rules` | no | Merge or replace rules |
| `nahnu-asset-scanner/run-test` | no | Server render + analysis, returns `token` and one-time `browser_url` |
| `nahnu-asset-scanner/get-test-report` | yes | Server analysis + browser error report |

Test runs are transients (`nahnuas_run_{token}`, 30 min). The token URL renders logged out; `public/test-runtime.js` simulates the first interaction, then posts errors to `admin-ajax.php?action=nahnuas_test_report` (token-authenticated) and shows a "Nahnu test report sent" badge.

---

## Installation

1. Download the latest ZIP
2. **Plugins → Add New → Upload Plugin** → select the ZIP
3. Activate
4. Click **Asset Scanner** in the admin menu
5. Select pages on the **Pages** tab → click **Run Scan**
6. Open the **Test Lab** tab to test changes

---

## Cookie database

The plugin ships with **600+ bundled cookie patterns covering 400+ plugins and services** — no remote requests needed.

| Category | Examples |
|---|---|
| E-commerce | WooCommerce, YITH, Easy Digital Downloads, SureCart, CartFlows, Stripe, PayPal, Klarna |
| Membership & LMS | MemberPress, PMPro, Restrict Content Pro, s2Member, LearnDash, LifterLMS, Tutor LMS |
| Page builders | Elementor, Divi, Beaver Builder, Bricks, Brizy, Oxygen, WPBakery |
| Security | Wordfence, Solid Security, Sucuri, Shield Security, WP Cerber, MalCare |
| Caching | WP Rocket, LiteSpeed Cache, W3 Total Cache, SiteGround Optimizer, Hummingbird |
| Analytics | Google Analytics (UA + GA4), Hotjar, Matomo, Microsoft Clarity, Heap, Lucky Orange |
| Advertising | Meta Pixel, Google Ads, TikTok, LinkedIn, Pinterest, DoubleClick, Criteo, Marketo |
| Chat / CRM | Tawk.to, LiveChat, Tidio, Crisp, Zendesk, Intercom, Drift, HubSpot |
| Consent | CookieYes, Complianz, Borlabs Cookie, Real Cookie Banner, Cookiebot, OneTrust |
| Email marketing | Klaviyo, Brevo, Omnisend, ActiveCampaign, ConvertKit, MailPoet |

---

## Privacy

Nahnu Asset Scanner makes **zero external HTTP requests**. All scans run entirely on your server. All cookie identification data is bundled within the plugin. Nothing is sent to any third-party service.

---

## File structure

```
nahnu-asset-scanner/
├── nahnu-asset-scanner.php     Main plugin file / bootstrap
├── uninstall.php               Drops all tables, options, and transients
├── includes/
│   ├── class-db.php            5-table schema, CRUD, browse_posts()
│   ├── class-scanner.php       wp_safe_remote_get fetcher, DOM parser, cookie extractor
│   ├── class-admin.php         Admin UI, all AJAX handlers
│   ├── class-plugin-cookies.php 600+ bundled WordPress cookie patterns
│   └── class-plugin-map.php    130+ plugin slug → name map
└── admin/assets/
    ├── admin.js
    └── admin.css
```

---

## Dev notes

- **Namespace:** `NahnuAS`
- **Prefix:** `nahnuas_`
- **Version constant:** `NAHNUAS_VERSION`
- **Text domain:** `nahnu-asset-scanner`
- **Capability:** `manage_options` on all AJAX endpoints

Always bump **both** the `Version:` plugin header **and** `NAHNUAS_VERSION` constant when making any changes.

---

## Changelog

### 1.0.2
New Test Lab (front-end `?nahnuas_test` panel, Test column, Test Lab tab, admin bar link, dependency warnings), 8 MCP abilities, and redesigned admin screen. Requires WP 7.1 / PHP 8.3. Fixes page fetches that always failed (scans found nothing), the Domains tab crash, Count column, source filters, ONLY_FULL_GROUP_BY queries, fake cookies from element IDs and 46 unreachable cookie-database entries. Fetching moved to wp_safe_remote_get with SSL verification; CSV export hardened for PHP 8.4; DB upgrade routine; multisite uninstall.

### 1.0.0
Initial release — JS/CSS/Cookie/Domain scanning, 600+ bundled cookie patterns, fully self-contained, zero external requests, WP.org compliant.
