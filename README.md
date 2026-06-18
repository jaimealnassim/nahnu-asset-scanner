# Nahnu Asset Scanner

**Version:** 1.0.0
**Author:** [Nahnu Plugins](https://www.nahnuplugins.com)
**License:** GPL-2.0-or-later
**Requires:** WordPress 6.0+, PHP 7.4+

---

Scan your WordPress site front end to inventory every JS file, CSS file, cookie, and external domain. Fully self-contained — no external requests, no API keys, no account required.

## What it does

| Tab | What you get |
|---|---|
| **JS** | Every script — path, size, inline/external, first/third-party, owning plugin |
| **CSS** | Every stylesheet with the same attribution detail |
| **Cookies** | Every cookie set, categorized (Necessary/Preferences/Analytics/Marketing), matched to its plugin |
| **Domains** | Every third-party domain contacted during the scan |

All results are exportable as CSV.

---

## Installation

1. Download the latest ZIP
2. **Plugins → Add New → Upload Plugin** → select the ZIP
3. Activate
4. Go to **Tools → Asset Scanner**
5. Select pages on the **Pages** tab → click **Run Scan**

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
│   ├── class-scanner.php       WP HTTP API fetcher, DOM parser, cookie extractor
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

### 1.0.0
Initial release — JS/CSS/Cookie/Domain scanning, 600+ bundled cookie patterns, fully self-contained, zero external requests, WP.org compliant.
