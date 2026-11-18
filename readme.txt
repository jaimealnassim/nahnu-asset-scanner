=== Nahnu Asset Scanner ===
Contributors: ja1me4
Tags: performance, cache, cookies, gdpr, assets
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.0.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Scan every JS file, CSS file, cookie and domain on your site, then test turning scripts off, deferring or delaying them before you optimize.

== Description ==

**Nahnu Asset Scanner** manually scans the pages you choose and builds a complete inventory of everything loaded on your site's front end. Instead of guessing which scripts to defer or which cookies need a consent banner, you get a precise, plugin-attributed list to work from.

=== New: Test Lab ===

Cache and performance plugins break sites when the wrong script is deferred or delayed. Test it first:

* Open any page with **?nahnuas_test=1** while logged in as an administrator (or click **Test assets** in the admin bar)
* A floating panel lists every script and stylesheet on that page, with the plugin or theme behind it
* Set each file to **On**, **Defer**, **Delay** (until the first scroll, tap, click or key press) or **Off**, or use quick actions such as "Delay 3rd-party JS" and "All CSS off"
* The panel shows any JavaScript errors your changes cause, and **Rules paused** lets you compare with the normal page
* Links you click keep test mode on, so you can check the whole site
* Only you see the test. Visitors always get the normal page, and test pages are never cached

You can also set test rules from the Scripts and Styles tabs and manage them in the **Test Lab** tab.

=== AI agents and MCP ===

The plugin registers WordPress abilities (Abilities API), so an AI assistant connected through MCP, for example with the WordPress MCP Adapter, can scan your site and run Test Lab tests for you:

* **run-scan**, **get-scan-summary**, **get-results** — scan pages and read the scripts, styles, cookies and domains found
* **get-page-assets** — every script and stylesheet on a page, with the rule key to use in a test
* **get-test-rules**, **set-test-rules** — read and change your Test Lab rules
* **run-test** — renders a page with test rules and reports what changed, render-blocking files before and after, and scripts that will break because a file they need was turned off, deferred or delayed. It also returns a one-time link: when the assistant opens it in a browser, the page runs the scripts (including delayed ones) and sends the JavaScript errors back
* **get-test-report** — the result of a test run, including the browser's JavaScript errors

Every ability requires an administrator account. Test runs never change the live site, and their links expire after 30 minutes.

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
* Test Lab — preview pages with scripts and styles off, deferred or delayed, only for you
* AI ready — WordPress abilities so AI assistants can scan and run tests over MCP
* No external requests — everything runs on your server with bundled data
* Plugin attribution — JS, CSS, and cookies are matched to the plugin that registered/sets them
* CSV export on every tab
* Fully self-contained — no account, no API key, no internet connection required

= Privacy =

Nahnu Asset Scanner makes no external HTTP requests of any kind. All scans run entirely on your server. All cookie identification data is bundled within the plugin. No data about your site or visitors is ever sent anywhere.

== Installation ==

1. Upload the `nahnu-asset-scanner` folder to `/wp-content/plugins/`, or install via **Plugins → Add New → Upload Plugin**
2. Activate the plugin
3. Click **Asset Scanner** in the WordPress admin menu (or **Open Scanner** on the Plugins screen)
4. Select the pages you want to scan on the Pages tab
5. Click **Run Scan**
6. Open the **Test Lab** tab, or add `?nahnuas_test=1` to any page address, to test changes

== Frequently Asked Questions ==

= Does this plugin slow down my site? =

No. The scanner only runs when you click "Run Scan" in the admin. Visitors never get any of its code: the Test Lab panel only loads for a logged-in administrator on a page opened with `?nahnuas_test`.

= Does Test Lab change my live site? =

No. Test rules are saved for your user only and are applied only to pages you open with `?nahnuas_test` while logged in as an administrator. Those pages are sent with no-cache headers and `DONOTCACHEPAGE`, so cache plugins do not store them. When a page works with a rule, make the same change in your cache or performance plugin.

= What do Off, Defer and Delay do in Test Lab? =

* **Off** — the file is not loaded at all
* **Defer** — a script runs after the HTML has been read; a stylesheet loads without blocking the first paint
* **Delay** — a script waits for the first scroll, tap, click or key press, like the "delay JavaScript" option in WP Rocket, LiteSpeed Cache, Perfmatters or FlyingPress

Inline code that WordPress prints after a script (for example `jquery-core-js-after`) follows that script automatically.

= Can an AI assistant run the tests? =

Yes. Connect your site to an AI assistant through MCP (for example with the WordPress MCP Adapter) as an administrator. The assistant can call **run-test** to render a page with test rules and get a report of what will break. An assistant that can use a browser can also open the one-time link it returns: the page runs the scripts and reports real JavaScript errors back, which **get-test-report** returns. The link shows the page as a logged-out visitor sees it and expires after 30 minutes.

= Test mode looks different from my cache plugin's result. Why? =

Most cache plugins turn their optimizations off for logged-in users, so Test Lab shows only your test rules. If yours optimizes for logged-in users too, turn that off while testing.

= How many pages can I scan at once? =

Up to 10 pages per scan. You can run multiple scans to cover different sections of your site.

= How is this different from a cookie consent plugin? =

This is an audit/inventory tool, not a consent banner plugin. It helps you understand what your site is doing so you can configure consent plugins, cache rules, and performance tools accurately. It works well alongside Complianz, CookieYes, or Real Cookie Banner.

= How do I use the results with a cache plugin? =

* **Cookies tab** — copy cookie names into your cache plugin's "bypass cache for these cookies" list (WooCommerce cart, login, language cookies must bypass the cache)
* **JS tab** — copy file paths into your cache plugin's "exclude from defer/delay" list for critical scripts
* **CSS tab** — copy file paths into your cache plugin's "exclude from combine/minify" list if you experience layout issues

= Does the plugin make any external requests? =

No. The plugin makes no requests to other servers. Scans and AI test runs only request pages from your own site. All cookie identification uses data bundled within the plugin itself.

= Can I use this on a multisite? =

Yes, per site. Each site in a network has its own scans, settings and tables, and uninstalling the plugin removes the data from every site.

= Where is scan data stored? =

All data is stored in your own database in custom tables (`nahnuas_scans`, `nahnuas_pages`, `nahnuas_assets`, `nahnuas_cookies`, `nahnuas_domains`). Everything is removed cleanly when you uninstall the plugin.

== Screenshots ==

1. **Test mode panel** — turn any script or stylesheet off, defer or delay it on the live page, only for you
2. **Test Lab** — open any page in test mode and manage your test rules
3. **Pages** — a three-step guide and the pages to include in the scan
4. **Scripts** — every script with plugin attribution, defer/async status and a Test column
5. **Cookies** — cookie inventory with category, plugin source, and expiry
6. **Domains** — all external domains detected during the scan

== Changelog ==

= 1.0.2 =
* New: Test Lab. Open any page with ?nahnuas_test=1 as an administrator to turn scripts and stylesheets off, defer or delay them, see the JavaScript errors that causes and compare with the normal page. Only you see it, and test pages are never cached.
* New: Test column in the Scripts and Styles tabs, a Test Lab tab and a "Test assets" link in the admin bar.
* New: dependency check. Test mode warns when a script will break because a file it needs is off, deferred or delayed.
* New: abilities for AI agents and MCP (WordPress Abilities API): run scans, read results, list page assets, manage test rules, and run tests with server-side analysis and browser error reports.
* New: redesigned screen with a three-step guide, result counts on every tab and clearer tab names (Scripts, Styles).
* Requires WordPress 7.1 and PHP 8.3 or newer.
* Fixed: every page fetch failed ("Missing header/body separator"), so scans found no scripts, styles, cookies or domains.
* Fixed: the Domains tab failed to load because of a JavaScript error.
* Fixed: the Domains tab Count column always showed 1.
* Fixed: the "Third-party only" and "First-party only" filters had no effect.
* Fixed: JS, CSS and Cookies tabs could come back empty on hosts that force the MySQL ONLY_FULL_GROUP_BY mode.
* Fixed: script and style element IDs such as "wp-a11y-js" were reported as cookies.
* Fixed: 46 cookies in the bundled database (Google consent, Cookiebot, Tawk.to, Microsoft and others) were never recognised, and WP Consent API / WP Statistics cookies were labelled as WordPress.
* Fixed: the "Open Scanner" link on the Plugins screen led to a permission error.
* Improved: pages are fetched with wp_safe_remote_get(), which checks every redirect, and all Set-Cookie headers are read without transport hooks. SSL certificates are verified for external URLs.
* Improved: relative asset URLs are resolved with WordPress core, and third-party detection understands domains such as example.co.uk.
* Improved: the Cookies tab and CSV export name the plugin or service behind each cookie.
* Improved: CSV export follows the current search and filter, protects every cell against spreadsheet formulas, and is compatible with PHP 8.4.
* Improved: database tables update automatically after plugin updates, and uninstall cleans every site on multisite.
* Improved: passes the WordPress.org Plugin Check with no errors or warnings. The page picker uses WP_Query, table names are bound with the %i placeholder, and the vendor list moved to data/vendors.json.
* Security: all post queries use prepared statements, and results tabs are limited to known values.

= 1.0.1 =
* Updated Tested up to WordPress 7.1.
* Fixed short description length.

= 1.0.0 =
* Initial release.
* Scans JS files, CSS files, cookies, and external domains across selected pages.
* 600+ bundled cookie patterns covering 400+ WordPress plugins and services.
* No external HTTP requests — fully self-contained.
* Four tabs: JS, CSS, Cookies, Domains — each with CSV export.
* Fully translatable.
* No license required — completely free.

== Upgrade Notice ==

= 1.0.2 =
New Test Lab and MCP abilities so you or an AI assistant can safely test turning off, deferring or delaying scripts and styles. Fixes scans that found nothing. Requires WordPress 7.1 and PHP 8.3.

= 1.0.0 =
Initial release.
