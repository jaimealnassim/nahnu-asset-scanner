=== Nahnu Asset Scanner ===
Contributors: ja1me4
Tags: performance, cache, cookies, gdpr, assets
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Scan your site's front end to inventory every JS file, CSS file, cookie, and external domain — the foundation for accurate cache configuration and cookie consent.

== Description ==

**Nahnu Asset Scanner** manually scans the pages you choose and builds a complete inventory of everything loaded on your site's front end. Instead of guessing which scripts to defer or which cookies need a consent banner, you get a precise, plugin-attributed list to work from.

=== What it scans ===

* **JavaScript** — every script, inline or external, with file path, size, whether it's first- or third-party, and which plugin or theme registered it
* **CSS** — every stylesheet with the same attribution detail
* **Cookies** — every cookie set during page load, categorized as Necessary / Preferences / Analytics / Marketing, with the plugin that sets it identified from a bundled database of 600+ known cookie patterns covering 400+ plugins
* **Domains** — every third-party domain contacted, identified by category using the bundled vendor map

=== Why it matters ===

**Cache configuration** — cache plugins need to know which cookies bypass the cache (WooCommerce cart, WPML language, login cookies) and which JS/CSS files to exclude from minification or combination. This plugin gives you those exact file paths and cookie names from a real scan of your site, not a generic list.

**Cookie consent** — before configuring Complianz, CookieYes, or Real Cookie Banner, run a scan to get an accurate, categorized cookie inventory. The bundled database covers WooCommerce, membership plugins, LMS platforms, builders, security plugins, analytics services, ad networks, chat widgets, and more.

**Performance auditing** — identify render-blocking scripts, bloated third-party embeds, and unnecessary tracking pixels. The JS and CSS tabs show inline vs. external, first-party vs. third-party, and file size at a glance.

**Third-party auditing** — the Domains tab reveals every external service your site contacts. Use it as the starting point for a Content Security Policy or to assess your GDPR third-party processor list.

=== Cookie database ===

The plugin ships with a bundled cookie database covering 600+ patterns from 400+ WordPress plugins and services. No external requests are made — everything is included in the plugin:

* **WooCommerce ecosystem** — WooCommerce, YITH plugins, CartFlows, FunnelKit, Dokan, and payment gateways (Stripe, PayPal, Klarna, Mollie, Braintree)
* **Membership & LMS** — MemberPress, Paid Memberships Pro, Restrict Content Pro, s2Member, WishList Member, LearnDash, LifterLMS, Tutor LMS, Sensei, Masteriyo, LearnPress
* **Community** — BuddyPress, BuddyBoss, bbPress, PeepSo, wpForo
* **Page builders** — Elementor, Divi, Beaver Builder, Brizy, Bricks, Oxygen, WPBakery
* **Security** — Wordfence, Solid Security (iThemes), Sucuri, Shield Security, WP Cerber, CleanTalk, MalCare
* **Caching** — WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Fastest Cache, WP Optimize, SiteGround Optimizer, Hummingbird, Swift Performance
* **Analytics** — Google Analytics (UA + GA4), Hotjar, Matomo, Heap, Lucky Orange, FullStory, Mouseflow, Inspectlet, VWO, Optimizely, Microsoft Clarity
* **Advertising** — Meta Pixel, Google Ads, Microsoft Ads, TikTok, LinkedIn, Pinterest, Google DoubleClick, Taboola, Outbrain, Criteo, The Trade Desk, Marketo, Oracle Eloqua, Salesforce Pardot
* **Live chat** — Tawk.to, LiveChat, Tidio, Crisp, Zendesk, Freshchat, Intercom, Drift, Olark, HubSpot
* **Consent platforms** — CookieYes, Complianz, Borlabs Cookie, Real Cookie Banner, Cookiebot, OneTrust, Osano, Cookie Notice
* **Email marketing** — Klaviyo, Brevo, Omnisend, ActiveCampaign, ConvertKit, Drip, MailPoet, Mailster, MC4WP
* **Accessibility** — accessiBe, UserWay, EqualWeb, AudioEye

=== Key features ===

* Manual scan — you choose which pages to scan (up to 10 per run)
* No external requests — everything runs on your server with bundled data
* Plugin attribution — JS, CSS, and cookies are matched to the plugin that registered/sets them
* CSV export on every tab
* Fully self-contained — no account, no API key, no internet connection required

= Privacy =

Nahnu Asset Scanner makes no external HTTP requests of any kind. All scans run entirely on your server. All cookie identification data is bundled within the plugin. No data about your site or visitors is ever sent anywhere.

== Installation ==

1. Upload the `nahnu-asset-scanner` folder to `/wp-content/plugins/`, or install via **Plugins → Add New → Upload Plugin**
2. Activate the plugin
3. Go to **Tools → Asset Scanner**
4. Select the pages you want to scan on the Pages tab
5. Click **Run Scan**

== Frequently Asked Questions ==

= Does this plugin slow down my site? =

No. The scanner only runs when you manually click "Run Scan" in the admin. It makes no changes to your front end and loads no assets for visitors.

= How many pages can I scan at once? =

Up to 10 pages per scan. You can run multiple scans to cover different sections of your site.

= How is this different from a cookie consent plugin? =

This is an audit/inventory tool, not a consent banner plugin. It helps you understand what your site is doing so you can configure consent plugins, cache rules, and performance tools accurately. It works well alongside Complianz, CookieYes, or Real Cookie Banner.

= How do I use the results with a cache plugin? =

* **Cookies tab** — copy cookie names into your cache plugin's "bypass cache for these cookies" list (WooCommerce cart, login, language cookies must bypass the cache)
* **JS tab** — copy file paths into your cache plugin's "exclude from defer/delay" list for critical scripts
* **CSS tab** — copy file paths into your cache plugin's "exclude from combine/minify" list if you experience layout issues

= Does the plugin make any external requests? =

No. The plugin makes zero external HTTP requests. All cookie identification uses data bundled within the plugin itself.

= Can I use this on a multisite? =

The plugin is currently designed for single-site installations. Multisite support is planned for a future release.

= Where is scan data stored? =

All data is stored in your own database in custom tables (`nahnuas_scans`, `nahnuas_pages`, `nahnuas_assets`, `nahnuas_cookies`, `nahnuas_domains`). Everything is removed cleanly when you uninstall the plugin.

== Screenshots ==

1. **Pages tab** — select which pages to include in the scan
2. **JS tab** — every script with plugin attribution and third-party badges
3. **CSS tab** — every stylesheet with source identification
4. **Cookies tab** — complete cookie inventory with category, plugin source, and expiry
5. **Domains tab** — all external domains detected during the scan
6. **Settings tab** — scan configuration
7. **About tab** — use-case guides for cache and performance configuration

== Changelog ==

= 1.0.0 =
* Initial release.
* Scans JS files, CSS files, cookies, and external domains across selected pages.
* 600+ bundled cookie patterns covering 400+ WordPress plugins and services.
* No external HTTP requests — fully self-contained.
* Four tabs: JS, CSS, Cookies, Domains — each with CSV export.
* Fully translatable.
* No license required — completely free.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
