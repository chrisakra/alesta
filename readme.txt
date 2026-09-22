=== Alesta ===
Contributors: alestaplugin
Tags: seo, sitemap, meta description, faq schema, brute force
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SEO + technical WordPress toolkit: AI title & meta (your own Anthropic or OpenAI key), FAQ schema, sitemap, .htaccess, brute-force protection & more.

== Description ==

**Alesta** is a minimalist WordPress toolkit focused on SEO and technical performance. Every module does one thing and does it well.

Modules shipped in this version (16 free modules):

* **Title & Meta AI + SEO Audit** — Generates SEO titles and meta descriptions for your posts, pages (and products) with Claude, one by one or in batch, then applies them to your site (or mirrors them into Yoast SEO, Rank Math or All in One SEO when one of them is active). Includes an SEO audit tab with a score per page (title, description, headings, images, links). Also adds an editable SEO meta box (title, description, Open Graph, Twitter Card, robots, canonical) on every post and page. Requires your own Anthropic or OpenAI API key (see "External services" below).
* **FAQ Schema** — Generates FAQ questions and answers from the content of a page with the AI provider you chose, lets you edit them, and outputs the FAQPage JSON-LD structured data eligible for Google rich results. Requires your own Anthropic or OpenAI API key.
* **Brute-force protection** — Limits failed login attempts per IP (configurable attempts, time window and lockout duration), blocks the login form and XML-RPC for banned IPs, IP whitelist, lockout history, manual unban and optional e-mail notification. No external service.
* **XML Sitemap** — Generates a sitemap.xml, automatically pings Google and Bing when content is updated, and lets you exclude specific post types or individual posts.
* **Gzip, Cache, HTTPS optimization** — Clean, safe manipulation of the .htaccess file: enable Gzip compression, browser cache headers for static assets, and HTTP to HTTPS redirection. One-click backup and restore included.
* **Robots.txt editor** — Edit, backup, restore and check the accessibility of your robots.txt directly from the WordPress admin.
* **Broken links scanner (4xx / 5xx)** — Scheduled scan of your internal links to detect 404, 500 and other HTTP errors, with a sortable results table.
* **Scheduled database cleaner** — Removes revisions, auto-drafts, orphan meta, expired transients, spam and trash comments on a WP Cron schedule. One-click manual cleanup and detailed report.
* **Google Fonts GDPR self-hosting** — Detects Google Fonts loaded by your theme/plugins, downloads them locally, and rewrites the URLs so no requests hit Google servers (GDPR compliance).
* **Maintenance mode** — One-click maintenance / coming-soon page with configurable logo, headline, message, background, countdown and whitelist for admins. Sends a 503 status to search engines.
* **GDPR cookies banner** — Customizable consent banner (Accept / Refuse / Configure) rendered site-wide, with per-category storage of the visitor's choice.
* **Talk to Me — floating contact widget** — Multi-channel contact button (WhatsApp, Messenger, phone, email, SMS, Telegram, Instagram DM, custom link) with configurable opening hours per day. No AI, no external API.
* **Health Check** — Site health dashboard: PHP version, SSL, disk usage, active plugins count, MySQL version, uploads folder size, key file/permission checks.
* **Debug Manager** — One-click toggle for WP_DEBUG (writes to wp-config.php with automatic backup), live viewer for debug.log with size/rotation info, one-click clear.
* **Configuration** — Lets you choose your AI provider (Anthropic Claude or OpenAI), stores its API key (encrypted with your site's AUTH_KEY salt) and lets you pick the model used by the AI modules. Both keys can be stored side by side so you can switch provider without retyping them. Test and delete a key at any time.
* **Budget tracker** — Monthly / daily token consumption and estimated cost of the AI modules, with an optional monthly limit, alert threshold and e-mail notification.

Alesta is built in the same product family as the Alesta AI suite. The AI modules are "bring your own key": you create an API key in your own Anthropic or OpenAI account, you are billed by that provider for what you use, and the plugin never proxies your requests through a third-party server.

= External services =

The **Title & Meta AI + SEO Audit** and **FAQ Schema** modules, the "Generate with AI" button of the SEO meta box and the optional debug.log analysis use **one** third-party AI API, the one you select in Alesta AI &rarr; Configuration: either the Anthropic Claude API (operated by Anthropic PBC) or the OpenAI API (operated by OpenAI, L.L.C.). No AI request is ever sent to the provider you did not select, and no request at all is sent until you enter a key.

* **What is sent and when**: only when an administrator explicitly clicks a generate / test / refresh button in the WordPress admin, the plugin sends the text content of the selected post(s) or page(s) (title, excerpt, body text, post type, site language) — or, for the Debug Manager, the last 100 lines of your debug.log — together with your API key to the selected provider over HTTPS. Nothing is sent automatically, on a schedule, or when visitors browse your site. The "Test the key" button sends a short fixed prompt, and "Refresh the model list" only asks the provider which models your key can use.
* **Endpoints used**: `https://api.anthropic.com/v1/messages` and `https://api.anthropic.com/v1/models` when the Anthropic provider is selected; `https://api.openai.com/v1/chat/completions` and `https://api.openai.com/v1/models` when the OpenAI provider is selected.
* **Why**: to obtain the generated SEO title, meta description, FAQ questions and answers, or log analysis from the model you selected, and to list the models available to your key.
* **Requirement**: an API key that you create in your own account with the chosen provider and enter in Alesta AI &rarr; Configuration. Without a key the AI features are simply disabled; every other module works without any external service.
* Anthropic terms of service: https://www.anthropic.com/legal/consumer-terms
* Anthropic privacy policy: https://www.anthropic.com/legal/privacy
* OpenAI terms of use: https://openai.com/policies/row-terms-of-use
* OpenAI privacy policy: https://openai.com/policies/row-privacy-policy

= Privacy and GDPR =

Apart from the AI provider API calls described above (triggered only by an administrator), Alesta does not send any data outside your site, except the public pings sent to Google and Bing when you regenerate your XML sitemap (standard sitemap behavior). No tracking, no telemetry.

= Compatibility =

* Works with any theme
* Compatible with the Classic Editor and the Block Editor (Gutenberg)
* Compatible with WooCommerce (products are included in the sitemap if the CPT is public)

= Pro version =

A separate **Alesta AI Pro** extension provides additional modules (AI, security, reporting, chatbot, and more). It is distributed outside the WordPress.org repository at alesta-ai.com and is fully independent and optional.

== Installation ==

1. Upload the `alesta` folder to `/wp-content/plugins/` (or install it directly from the WordPress admin: Plugins &gt; Add New).
2. Activate the plugin from the Plugins menu.
3. Open the **Alesta AI** menu in the admin sidebar.
4. Configure each module from its own submenu.

== Frequently Asked Questions ==

= How do I enable Gzip compression? =

Go to **Alesta AI &rarr; Gzip, Cache, HTTPS optimization**, open the Gzip tab, and click Apply. A backup of the .htaccess is created automatically.

= Does the HTTPS module replace a real SSL certificate? =

No. The HTTPS redirection only works if your host already has a valid SSL certificate on your domain. The module simply adds the .htaccess rewrite rule that forces `http://` to `https://`.

= Is the XML sitemap detected by Google? =

Yes, the sitemap is available at `/sitemap.xml`. Alesta automatically pings Google and Bing when content is updated.

= Is it compatible with Yoast SEO or RankMath? =

Technically yes, but if you already use Yoast or RankMath for your XML sitemap, disable their sitemap module to avoid duplicates. When Yoast SEO, Rank Math or All in One SEO is active, the Title & Meta module writes the generated titles and descriptions into their fields and lets them output the tags, so nothing is duplicated.

= Do I need an Anthropic or OpenAI account to use Alesta? =

Only for the AI features (Title & Meta AI, SEO Audit suggestions, FAQ Schema, debug.log analysis). Create an API key at console.anthropic.com or at platform.openai.com, select the matching provider in Alesta AI &rarr; Configuration, paste the key there, and you are billed by that provider according to your usage. All other modules work without any account or external service.

= Can I use ChatGPT (OpenAI) instead of Claude? =

Yes. Pick "OpenAI (ChatGPT)" as the AI provider in Alesta AI &rarr; Configuration, paste your OpenAI key and choose a model (the model list is fetched from your account and cached for 24 hours). Both keys are kept side by side, so you can switch back to Anthropic at any time without retyping anything. Note that cost estimates in the Budget page are only computed for models whose pricing the plugin knows; for the others the tokens are still counted but the cost stays at 0 ("not estimated"), and the `alesta_ai_model_pricing` filter lets you supply your own rates.

= Is my API key stored securely? =

The key is encrypted (AES-256-GCM) with a key derived from your site's `AUTH_KEY` salt before being stored in the database, and it is never displayed again in full in the admin. If your server lacks OpenSSL, the plugin warns you and stores the key as a regular option.

== Changelog ==

= 1.9.0 =
* New: AI errors are now actionable — when no API key is configured, the error shown by every AI module names the exact path (Alesta AI &rarr; Configuration) and offers a "Configure the API key" button, and the AI pages warn you before you click "Generate".
* New: choice of AI provider — Anthropic Claude (default, unchanged) or OpenAI (ChatGPT). Both keys are stored encrypted side by side, each with its own model, the model lists are fetched from the provider and cached 24 h, and switching provider changes nothing else in the plugin.
* New: a one-time, dismissible invitation to review the plugin on WordPress.org, shown on the Alesta AI dashboard only after 10 days of use and at least two modules actually used (never blocking, never in other plugins' screens).
* Dashboard: the green badge on unlocked premium modules now names the active plan (“Actif Solo”, “Actif Pro”…) instead of always reading “Actif Pro”.
* New: **Title & Meta AI + SEO Audit** module (previously Pro-only) — generate SEO titles and meta descriptions with Claude, one by one or in batch, apply / revert / export CSV, per-page SEO audit score, mirroring into Yoast SEO, Rank Math and All in One SEO when active.
* New: per-post **SEO meta box** (title, description, Open Graph, Twitter Card, robots, canonical) with a "Generate with AI" button; Alesta outputs the tags itself when no other SEO plugin is active.
* New: **FAQ Schema** module (previously Pro-only) — generate FAQ questions / answers with Claude, edit them, and output FAQPage JSON-LD.
* New: **Brute-force protection** module (previously Pro-only) — failed-login rate limiting per IP, lockout, whitelist, XML-RPC block, history, manual unban and optional e-mail notification. Enabled by default with 5 attempts / 5 minutes / 15-minute lockout; adjustable or disabled from its page.
* New: **Configuration** page (Alesta AI &rarr; Configuration) to store your own Anthropic API key (encrypted) and choose the Claude model. Bring your own key: no proxy, you are billed directly by Anthropic.
* Budget tracker now records the token usage and estimated cost of the Free AI modules (monthly limit, alert threshold and e-mail alert are enforced).
* Compatibility with the Alesta AI Pro addon: when the Pro is active it keeps providing these pages under the same menu entries, and the Free does not register duplicates. Settings, API key, generated meta and lockouts are stored under the same keys, so upgrading Free to Pro keeps everything.
* Dashboard: "Title & Meta IA", "FAQ Schema" and "Brute Force" cards are now active modules; new "Configuration" card.
* readme: "External services" section describing the Anthropic and OpenAI API usage.

= 1.8.3 =
* Fix: lock icon on locked dashboard cards was displayed as garbled characters (encoding).

= 1.8.2 =
* Dashboard: when Alesta AI Pro is active, module cards now reflect the actual licence plan — modules not covered by the current plan (e.g. Pro modules on a Solo licence) show a locked "Débloquer" card instead of "Actif Pro".
* Dashboard: "Données structurées", "Traduction IA" and "Brute Force" are now flagged Solo (aligned with alesta-ai.com/tarifs).
* Dashboard: removed the "Email transactionnel" and "Scan fichiers sensibles" teaser cards (no matching Pro module).
* Fix: "Ouvrir" buttons for Audit sécurité, Brute Force, Avis Google and Avis Trustpilot now open the correct Pro page.
* Sidebar: section headers are tagged with a CSS class (more robust styling when the Pro addon reorders the menu).

= 1.8.1 =
* Cleanup: removed orphan file includes/class-alesta-meta.php (never loaded).

= 1.8.0 =
* New functional module ported from Alesta AI Free v1.2.7 (completes the Free blueprint 12/12):
  * **Minify HTML/CSS/JS + Preload hints** — Minifies CSS/JS files enqueued by WordPress and the HTML output. Includes automatic bypass for major page-builders (Elementor, Divi, Beaver, Bricks, Oxygen, WPBakery, Brizy, Thrive) and the Customizer preview. Cache stored in `wp-content/cache/alesta-minify/`. Warning banner reminds users to test each toggle before enabling in production.

= 1.7.1 =
* Compatibility confirmed with WordPress 7.1.
* No code changes — bump metadata only ("Tested up to").

= 1.7.0 =
* New functional modules ported from Alesta AI Free v1.2.7 (completes the Free blueprint):
  * **Health Check** — Site health dashboard (PHP, SSL, disk, plugins, MySQL, uploads).
  * **Debug Manager** — Toggle WP_DEBUG + view / analyze debug.log with automatic wp-config.php backup.
  * **Budget tracker** — Monthly / daily token usage dashboard (empty in Free, populated by the optional Pro plugin).
* New admin dashboard section: "07 Réglages & Diagnostic".
* Plugin now covers 12/12 Free modules of the Alesta AI Free blueprint.

= 1.6.0 =
* New functional modules ported from Alesta AI Free v1.2.7:
  * **Maintenance mode** — 503 maintenance / coming-soon page with logo, headline, message, background, optional countdown and admin whitelist.
  * **GDPR cookies banner** — Site-wide consent banner (Accept / Refuse / Configure) with per-category preference storage.
  * **Talk to Me** — Floating multi-channel contact widget (WhatsApp, Messenger, phone, email, SMS, Telegram, Instagram, custom) with opening hours per day. Zero AI, zero external API.
* Two new admin dashboard sections: "05 Sécurité & RGPD" and "06 Communication & Contact".
* Plugin description updated to reflect the three new modules.

= 1.5.0 =
* New functional modules ported from Alesta AI Free v1.2.7:
  * **Scheduled DB Cleaner** — WP Cron cleanup for revisions, transients, spam.
  * **Google Fonts GDPR** — self-hosts Google Fonts detected on your site.
* Section "04 Performance & Optimisation" of the dashboard now lists 5 active modules.
* Plugin description updated to include the two new modules.

= 1.4.0 =
* New functional modules ported from Alesta AI Free v1.2.7:
  * **Robots.txt editor** — edit, backup, restore, accessibility check.
  * **Broken links scanner (4xx / 5xx)** — scheduled scan of internal links with results table.
* Section "04 Performance & Optimisation" of the dashboard now lists 3 active modules.
* Plugin description updated to reflect the new modules.

= 1.3.0 =
* New functional modules ported from Alesta AI Free v1.2.7:
  * **XML Sitemap** — generation, Google/Bing ping, per-type or per-post exclusions.
  * **Gzip, Cache, HTTPS optimization** — .htaccess helper with backup/restore.
* The SEO Meta Tags module (per-post title/description metabox) is removed: it is not part of the Alesta AI Free blueprint (that feature is in the Pro extension). Users who need it can stay on version 1.2.0.
* Plugin description updated to reflect the new "SEO + technical toolkit" scope.

= 1.2.0 =
* Plugin admin UI switched to French to match the Alesta AI product family (still translation-ready via text-domain).
* Dashboard simplified: single "01 SEO" section with two cards.
* Robots.txt module removed from this release — will come back in a future version as a properly finished block.

= 1.1.1 =
* Alesta AI menu now uses the Greek letter phi as sidebar icon, matching the Alesta AI product family.
* Dashboard redesigned: cockpit header, key figures, and a full catalogue of module sections. Modules included in the Pro extension are shown as informational cards linking to alesta-ai.com.
* Loads a small admin.css / admin-menu.css / pro-promo.css on Alesta AI screens only.

= 1.1.0 =
* New: Robots.txt module. Edit, backup, restore and check accessibility of your robots.txt directly from the WordPress admin.
* Alesta AI menu now includes a "Robots.txt" submenu.

= 1.0.3 =
* New: Alesta AI admin menu with dashboard page listing active modules.
* SEO Meta Tags module remains fully functional and accessible via the same metabox.
* Foundation added for future modules.

= 1.0.2 =
* Remove Plugin URI header (was identical to Author URI, which is not allowed by WordPress.org).
* No functional changes.

= 1.0.1 =
* Update plugin metadata (Author, Author URI, Plugin URI, Contributors) for WordPress.org submission compliance.
* No functional changes.

= 1.0.0 =
* First public release.
* Editable SEO title and meta description per page and post.
* Automatic Open Graph and Twitter Card.

== Upgrade Notice ==

= 1.9.0 =
Three modules become free: Title & Meta AI + SEO Audit, FAQ Schema and Brute-force protection. AI features need your own Anthropic API key (Alesta AI > Configuration). Brute-force protection is on by default (5 failed attempts in 5 min = 15-min lockout). Safe to update.

= 1.8.3 =
Cosmetic fix on locked dashboard cards. Safe to update.

= 1.8.2 =
Dashboard now shows the real licence coverage when Alesta AI Pro is active. Safe to update.

= 1.8.1 =
Maintenance release, no functional change.

= 1.8.0 =
Adds the Minify HTML/CSS/JS module (with automatic page-builder bypass), completing the Free blueprint 12/12. Safe to update — new module is OFF by default; enable each toggle one by one and test.

= 1.7.1 =
Compatibility bump for WordPress 7.1. No code change, safe to update.

= 1.7.0 =
Adds three new modules to complete the Free blueprint: Health Check, Debug Manager, Budget tracker. Safe to update.

= 1.6.0 =
Adds three new modules: maintenance mode (503 page), GDPR cookies banner, and floating multi-channel contact widget (Talk to Me). Safe to update.

= 1.5.0 =
Adds two new modules: scheduled DB cleaner and GDPR-safe Google Fonts self-hosting. Safe to update.

= 1.4.0 =
Adds two new functional modules: Robots.txt editor and Broken links scanner (4xx / 5xx). Safe to update — no removals from 1.3.0.

= 1.3.0 =
Major pivot: the plugin now ships XML Sitemap and .htaccess optimization (Gzip / cache / HTTPS), aligning with the Alesta AI Free blueprint. The previous per-post SEO metabox is removed. If you rely on it, stay on 1.2.0 or export your data first.

= 1.2.0 =
Admin UI translated to French. Dashboard reduced to one SEO section. Safe to update.

= 1.1.1 =
Full dashboard redesign matching the Alesta AI product family. Safe to update.

= 1.1.0 =
Adds a Robots.txt editor module. Safe to update.

= 1.0.3 =
Adds admin menu and dashboard. No breaking changes.

= 1.0.2 =
Metadata cleanup only. Safe to update.

= 1.0.1 =
Metadata update only. Safe to update.

= 1.0.0 =
First public release.
