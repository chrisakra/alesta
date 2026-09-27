=== Alesta ===
Contributors: alestaplugin
Tags: seo, sitemap, meta description, faq schema, brute force
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SEO + technical toolkit: AI title & meta with your own Anthropic or OpenAI key, FAQ schema, XML sitemap, .htaccess, brute-force protection and more.

== Description ==

**Alesta** is a minimalist WordPress toolkit focused on SEO and technical performance. Every module does one thing and does it well.

Modules shipped in this version (16 free modules):

* **Title & Meta AI + SEO Audit** — Generates SEO titles and meta descriptions for your posts, pages (and products) with your AI provider (Anthropic Claude or OpenAI), one by one or in batch, then applies them to your site (or mirrors them into Yoast SEO, Rank Math or All in One SEO when one of them is active). Includes an SEO audit tab with a score per page (title, description, headings, images, links). Also adds an editable SEO meta box (title, description, Open Graph, Twitter Card, robots, canonical) on every post and page, whose AI button ("Générer avec Claude") is available to editors and administrators by default. Password-protected content is never sent to the AI. Requires your own Anthropic or OpenAI API key (see "External services" below).
* **FAQ Schema** — Generates FAQ questions and answers from the content of a page with the AI provider you chose, lets you edit them, and outputs the FAQPage JSON-LD structured data eligible for Google rich results. Requires your own Anthropic or OpenAI API key.
* **Brute-force protection** — Limits failed login attempts per IP (configurable attempts, time window and lockout duration), blocks the login form and XML-RPC for banned IPs, IP whitelist, lockout history, manual unban and optional e-mail notification. Off by default: you turn it on from its page after checking the IP it detects. If your site sits behind an undeclared proxy or CDN, lockouts are suspended with a warning instead of blocking every visitor (see the FAQ). No external service.
* **XML Sitemap** — Generates a sitemap.xml (with image and video entries), can regenerate it automatically when content changes, lets you exclude post types or individual posts and disable the native WordPress sitemaps. It checks that the file is publicly reachable and links to Google Search Console and Bing Webmaster Tools so you can submit it there.
* **Gzip, Cache, HTTPS optimization** — Clean, safe manipulation of the .htaccess file: enable Gzip compression, browser cache headers for static assets, and HTTP to HTTPS redirection. One-click backup and restore included.
* **Robots.txt editor** — Edit, backup, restore and check the accessibility of your robots.txt directly from the WordPress admin.
* **Broken links scanner (4xx / 5xx)** — On-demand scan of the links found in your published posts, pages and products (Elementor content included) to detect 404, 500 and other HTTP errors, with a sortable results table and a single-URL tester.
* **Scheduled database cleaner** — Removes revisions, auto-drafts, orphan meta, expired transients, spam and trash comments on a WP Cron schedule. One-click manual cleanup and detailed report.
* **Google Fonts GDPR self-hosting** — Detects Google Fonts loaded by your theme/plugins, downloads them locally, and rewrites the URLs so no requests hit Google servers (GDPR compliance).
* **Maintenance mode** — One-click maintenance / coming-soon page with configurable logo, headline, message, background, countdown and whitelist for admins. Sends a 503 status to search engines.
* **GDPR cookies banner** — Customizable consent banner (Accept / Refuse / Configure) rendered site-wide, with per-category storage of the visitor's choice.
* **Talk to Me — floating contact widget** — Multi-channel contact button (WhatsApp, Messenger, phone, email, SMS, Telegram, Instagram DM, custom link) with configurable opening hours per day. No AI, no external API. The "Powered by Alesta" credit link is optional and off by default.
* **Minify HTML/CSS** — Minifies the CSS files enqueued by WordPress and the HTML output, with an automatic bypass for the major page builders (Elementor, Divi, Beaver, Bricks, Oxygen, WPBakery, Brizy, Thrive) and the Customizer preview. Cache stored in `wp-content/cache/alesta-minify/`. An optional "Preload CSS" section adds `<link rel="preload" as="style">` hints to the page head for the enqueued stylesheets (all of them, or only the handles you list, with exclusions). Every option is off by default. JavaScript minification is still in development and currently unavailable.
* **Health Check** — Site health dashboard: PHP version, SSL, disk usage, active plugins count, MySQL version, uploads folder size, key file/permission checks.
* **Debug Manager** — One-click toggle for WP_DEBUG (writes the WP_DEBUG constants to wp-config.php after checking that the result is valid PHP, and keeps a restorable copy of the previous file), live viewer for debug.log with size/rotation info, one-click clear and optional AI analysis of the log.
* **Budget tracker** — Monthly / daily token consumption and estimated cost of the AI modules, with an optional monthly limit, alert threshold and e-mail notification.

The modules come with a **Configuration** page where you choose your AI provider (Anthropic Claude or OpenAI), store its API key (encrypted with your site's AUTH_KEY salt) and pick the model used by the AI modules. Both keys can be stored side by side so you can switch provider without retyping them. Test and delete a key at any time.

Alesta is built in the same product family as the Alesta AI suite. The AI modules are "bring your own key": you create an API key in your own Anthropic or OpenAI account, you are billed by that provider for what you use, and the plugin never proxies your requests through a third-party server.

= External services =

The **Title & Meta AI + SEO Audit** and **FAQ Schema** modules, the AI button of the SEO meta box and the optional debug.log analysis use **one** third-party AI API, the one you select in Alesta AI → Configuration: either the Anthropic Claude API (operated by Anthropic PBC) or the OpenAI API (operated by OpenAI, L.L.C.). No AI request is ever sent to the provider you did not select, and no request at all is sent until you enter a key.

* **Who triggers a request**: only a logged-in user who clicks a generate / analyze / test / refresh button in the WordPress admin. The Title & Meta, FAQ Schema, Debug Manager and Configuration screens are reserved to administrators. The AI button of the SEO meta box is available to editors and administrators by default (`alesta_meta_ai_capability` filter) and limited to 30 generations per user per hour (`alesta_meta_ai_rate_limit` filter).
* **What is sent and when**: at that moment, the plugin sends the text content of the selected post(s) or page(s) (title, excerpt, body text, post type, site language) — or, for the Debug Manager, the last 100 lines of your debug.log — together with your API key to the selected provider over HTTPS. Password-protected content is never sent. Nothing is sent automatically, on a schedule, or when visitors browse your site. The "Test the key" button sends a short fixed prompt, and "Refresh the model list" only asks the provider which models your key can use.
* **Endpoints used**: `https://api.anthropic.com/v1/messages` and `https://api.anthropic.com/v1/models` when the Anthropic provider is selected; `https://api.openai.com/v1/chat/completions` and `https://api.openai.com/v1/models` when the OpenAI provider is selected.
* **Why**: to obtain the generated SEO title, meta description, FAQ questions and answers, or log analysis from the model you selected, and to list the models available to your key.
* **Requirement**: an API key that you create in your own account with the chosen provider and enter in Alesta AI → Configuration. Without a key the AI features are simply disabled; every other module works without any external service.
* Anthropic terms of service: https://www.anthropic.com/legal/consumer-terms
* Anthropic privacy policy: https://www.anthropic.com/legal/privacy
* OpenAI terms of use: https://openai.com/policies/row-terms-of-use
* OpenAI privacy policy: https://openai.com/policies/row-privacy-policy

Some optional modules contact the following additional services, always as a result of an action in the WordPress admin and never because a visitor browses your site:

* **Google Fonts** (Google LLC): the "Google Fonts GDPR" module downloads font stylesheets and font files from `https://fonts.googleapis.com` and `https://fonts.gstatic.com` so they can be served from your own server. It runs only when an administrator scans the site or localises a font, and sends only the font request — no visitor data. Terms: https://policies.google.com/terms — Privacy: https://policies.google.com/privacy
* **Vimeo oEmbed** (Vimeo.com, Inc.): when the XML sitemap module builds a video sitemap, it queries `https://vimeo.com/api/oembed.json` for the public metadata (thumbnail) of the Vimeo videos embedded in your content. Triggered only when the sitemap is generated: by an administrator, or after a content change if you enabled automatic regeneration. Terms: https://vimeo.com/terms — Privacy: https://vimeo.com/privacy
* **Link checker**: the SEO Audit and the broken-links scanner send an HTTP HEAD request to the URLs found in your own published content, to verify they still resolve (User-Agent `Alesta-LinkChecker` or `Alesta-Scanner`). Internal and private network addresses are refused. Triggered only when an administrator runs the audit or the scan; the destinations are the sites you yourself linked to.

= Privacy and GDPR =

Apart from the external services described above — each triggered from the WordPress admin, never by visitor traffic — Alesta does not send any data outside your site. It does not ping search engines: you submit your sitemap yourself in Google Search Console or Bing Webmaster Tools. No tracking, no telemetry, and no link to our site is added to your pages unless you enable the optional Talk to Me credit.

= Compatibility =

* Works with any theme
* Compatible with the Classic Editor and the Block Editor (Gutenberg)
* Compatible with WooCommerce (products are included in the sitemap if the CPT is public)
* Does not duplicate the meta description, Open Graph or Twitter tags of Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, Slim SEO, Squirrly SEO, SmartCrawl or Jetpack (see the FAQ)

= Pro version =

A separate **Alesta AI Pro** extension provides additional modules (AI, security, reporting, chatbot, and more). It is distributed outside the WordPress.org repository at alesta-ai.com and is fully independent and optional. When it is active, it can take over the modules both plugins share (Configuration, Title & Meta, SEO Audit, SEO meta box, FAQ Schema, brute-force protection) with its own pages and defaults; settings, keys, generated meta and lockouts are shared. From version 2.0.8, its SEO meta box follows the same rules as this plugin (AI button for editors and administrators by default, same filters, same hourly limit), and its SEO meta box, Title & Meta generation and FAQ Schema never send password-protected content to the AI.

== Installation ==

1. Upload the `alesta` folder to `/wp-content/plugins/` (or install it directly from the WordPress admin: Plugins → Add New).
2. Activate the plugin from the Plugins menu.
3. Open the **Alesta AI** menu in the admin sidebar.
4. Configure each module from its own submenu. Brute-force protection stays off until you enable it on its page.

== Frequently Asked Questions ==

= How do I enable Gzip compression? =

Go to **Alesta AI → Optimisation Gzip, Cache, HTTPS**, open the Gzip tab, and click Apply. A backup of the .htaccess is created automatically.

= Does the HTTPS module replace a real SSL certificate? =

No. The HTTPS redirection only works if your host already has a valid SSL certificate on your domain. The module simply adds the .htaccess rewrite rule that forces `http://` to `https://`.

= Is the XML sitemap detected by Google? =

Once generated, the sitemap is available at `/sitemap.xml`. Alesta does not ping search engines: submit the sitemap once in Google Search Console and Bing Webmaster Tools (the XML Sitemap page links to both), or add a `Sitemap:` line with the Robots.txt editor. With automatic regeneration on, the file is kept up to date when your content changes.

= Is it compatible with Yoast SEO, Rank Math or other SEO plugins? =

Yes. If you already use another plugin's XML sitemap, disable one of the two sitemaps to avoid duplicates. For the head tags:

* With Yoast SEO, Rank Math or All in One SEO, the Title & Meta module writes the generated titles and descriptions into their fields and lets them output the tags.
* With SEOPress, The SEO Framework, Slim SEO, Squirrly SEO or SmartCrawl, Alesta outputs no meta description, Open Graph or Twitter tags of its own.
* With Jetpack's Open Graph tags on, Alesta outputs only the meta description.

The `alesta_output_social_meta` filter (arguments: `$enabled`, `$post_id`) lets you switch Alesta's Open Graph and Twitter tags off, or back on.

= Why is brute-force protection off after installing? =

A lockout is based on the visitor's IP address. If your site sits behind a CDN or reverse proxy that is not declared, every visitor may appear with the same IP, and locking it would lock everyone out, you included. The protection therefore starts off: open **Alesta AI → Protection Brute Force**, check the IP it detects, tick "Activer la protection" and save.

= My site is behind Cloudflare or a reverse proxy. What should I configure? =

Alesta trusts only the connection IP (`REMOTE_ADDR`). When it detects forwarding headers from an undeclared proxy, it suspends IP lockouts and shows a red warning on the Brute Force page instead of blocking everyone, and it refuses to whitelist private addresses or the proxy's IP. To restore the protection, declare the proxy in wp-config.php, above the "That's all, stop editing!" line. Example for Cloudflare:

    define( 'ALESTA_TRUSTED_PROXIES', '173.245.48.0/20,103.21.244.0/22' );
    define( 'ALESTA_TRUSTED_PROXY_HEADER', 'HTTP_CF_CONNECTING_IP' );

`ALESTA_TRUSTED_PROXIES` is a comma-separated list of proxy IPs or CIDR ranges (IPv4 and IPv6). With Cloudflare, list every range published at https://www.cloudflare.com/ips/ (when it detects a proxy, the Brute Force page shows a ready-to-copy snippet). `ALESTA_TRUSTED_PROXY_HEADER` is the server variable that holds the real visitor IP: `HTTP_CF_CONNECTING_IP` for Cloudflare, `HTTP_X_FORWARDED_FOR` (the default) for most other proxies. For a proxy running on the same server (Varnish, nginx), declare `127.0.0.1` with `HTTP_X_FORWARDED_FOR`. The header is only trusted for requests that really come from a declared proxy, so it cannot be spoofed by visitors.

If the warning appears although your site uses no CDN (for example because you browse through a company proxy), declaring your server's own proxy address, such as `127.0.0.1`, is enough: once `ALESTA_TRUSTED_PROXIES` is defined, Alesta stops guessing.

= Is debug.log protected on nginx? =

No. When you turn debug mode on, the Debug Manager adds a rule that blocks web access to `wp-content/debug.log`, but only servers that read `.htaccess` files (Apache, LiteSpeed) apply it. On nginx, turn debug mode off and clear the log when you are done, or deny access to that file in your nginx configuration.

= Do I need an Anthropic or OpenAI account to use Alesta? =

Only for the AI features (Title & Meta AI, SEO Audit suggestions, FAQ Schema, debug.log analysis). Create an API key at console.anthropic.com or at platform.openai.com, select the matching provider in Alesta AI → Configuration, paste the key there, and you are billed by that provider according to your usage. All other modules work without any account or external service.

= Can I use ChatGPT (OpenAI) instead of Claude? =

Yes. Pick "OpenAI (ChatGPT)" as the AI provider in Alesta AI → Configuration, paste your OpenAI key and choose a model (the model list is fetched from your account and cached for 24 hours). Both keys are kept side by side, so you can switch back to Anthropic at any time without retyping anything. The Budget page knows the prices of the current Claude models; a model it has no price for (OpenAI models, newer Claude models) is counted at the highest rate in its table, a deliberately high estimate so that your monthly limit keeps working. The `alesta_ai_model_pricing` filter lets you supply the exact rates.

= Who can use the AI button of the SEO meta box? =

Editors and administrators (capability `edit_others_posts`), on the posts they are allowed to edit, with a limit of 30 generations per user per hour. Use the `alesta_meta_ai_capability` filter to change the required capability, and the `alesta_meta_ai_rate_limit` filter to change the hourly limit (0 removes it). The Title & Meta and FAQ Schema screens remain reserved to administrators.

= Does Alesta add a link to my site's pages? =

No. The "Powered by Alesta" credit of the Talk to Me widget is off by default; you can enable it in Talk to Me → Affichage → Crédit if you wish.

= Is my API key stored securely? =

The key is encrypted (AES-256-GCM) with a key derived from your site's `AUTH_KEY` salt before being stored in the database, and it is never displayed again in full in the admin. If your server lacks OpenSSL, the plugin warns you and stores the key as a regular option.

== Changelog ==

= 1.9.0 =
* New: Title & Meta AI + SEO Audit (previously in Alesta AI Pro) — single or batch generation with your AI provider, apply / revert / CSV export, per-page SEO score, mirrored into Yoast SEO, Rank Math or All in One SEO when active.
* New: per-post SEO meta box (title, description, Open Graph, Twitter Card, robots, canonical) with an AI button ("Générer avec Claude"), available to editors and administrators by default (`alesta_meta_ai_capability` filter) and limited to 30 generations per user per hour (`alesta_meta_ai_rate_limit` filter, 0 = no limit).
* New: FAQ Schema (previously in Alesta AI Pro) — generate, edit and publish FAQPage JSON-LD.
* New: Brute-force protection (previously in Alesta AI Pro) — failed-login limit per IP (5 failures in 5 min = 15-min lockout by default), whitelist, XML-RPC block, history, manual unban, e-mail alert. Off by default: enable it from its page after checking the detected IP.
* New: brute-force protection detects an undeclared proxy or CDN: lockouts are then suspended with a warning instead of blocking every visitor, and private addresses or the proxy's IP cannot be whitelisted. Declare your proxy with `ALESTA_TRUSTED_PROXIES` and `ALESTA_TRUSTED_PROXY_HEADER` (see the FAQ).
* New: Configuration page — choose Anthropic (Claude, default) or OpenAI (ChatGPT); each key encrypted, side by side, with its own model; model lists fetched from the provider and cached 24 h. Bring your own key, billed directly by the provider.
* New: actionable AI errors — every AI screen says where to set the API key and offers a "Configure the API key" button.
* New: the Budget tracker records the usage and estimated cost of the free AI modules (optional monthly limit, alert threshold and e-mail alert). Prices of the current Claude models are included; a model without a known price (OpenAI models, newer Claude models) is counted at the highest rate in the table ("estimation haute" on the Budget and Configuration pages) so the limit stays effective; `alesta_ai_model_pricing` filter to supply exact rates.
* New: Alesta outputs no meta description, Open Graph or Twitter tags when SEOPress, The SEO Framework, Slim SEO, Squirrly SEO or SmartCrawl is active (in addition to Yoast SEO, Rank Math and All in One SEO), and no Open Graph / Twitter tags when Jetpack's Open Graph tags are on. New `alesta_output_social_meta` filter.
* Privacy: password-protected content is excluded from AI generation (title, meta description, FAQ, SEO Audit suggestions) and from the SEO Audit list, and no description, Open Graph, Twitter or FAQ markup is output for it until it is unlocked.
* Changed: the "Powered by Alesta" link of the Talk to Me widget is now optional and off by default, existing sites included (Talk to Me → Affichage → Crédit).
* Security: the visitor IP is taken from REMOTE_ADDR unless trusted proxies are declared (brute-force protection, maintenance-mode whitelist).
* Security: hardened Google Fonts self-hosting (TLS verification, allowed font types only, content checks, no script execution in the fonts folder on Apache).
* Security: the broken-links scanner, the URL tester and the SEO audit link checker refuse internal/private addresses and verify TLS.
* Security: the Debug Manager checks that wp-config.php remains valid PHP before saving it and keeps a restorable copy (wp-config-alesta-backup.php); on Apache it also blocks web access to debug.log (not on nginx, see the FAQ).
* Security: TLS verification on the Vimeo oEmbed request of the video sitemap.
* Fix: SEO Audit "Broken links" check never ran (invalid regular expression, PHP warning per page). It now works and is bounded per audit (60 links, 25 seconds) so large sites do not time out; links beyond the limit are reported as "not checked".
* Fix: Minify HTML removed the inline style and script blocks when "Remove HTML comments" was on (broke themes such as Astra).
* Fix: Minify CSS broke calc() (spaces around "+"). Minified CSS files are regenerated under new names after the update: clear your page cache / CDN so cached pages use them. "Vider le cache" removes the old files.
* Changed: JavaScript minification is paused ("in development") while its engine is reworked. If it was enabled, it is switched off on update and its cached files are deleted. HTML and CSS minification are unaffected.
* Fix: Debug Manager "Analyze" button did nothing; accents on the XML Sitemap page; clearer "API key missing" messages; debug messages removed from the browser console.
* Changed: the dashboard subtitles now list only features included in the free plugin.
* Compatibility with Alesta AI Pro: when the add-on is active it provides these pages and the free plugin registers no duplicates; settings, keys, generated meta and lockouts are shared. From Alesta AI Pro 2.0.8, its SEO meta box uses the same access rule, filters and hourly limit, and its SEO meta box, Title & Meta and FAQ Schema exclude password-protected content (see "Pro version").
* readme: "External services" section (Anthropic, OpenAI, Google Fonts, Vimeo oEmbed, link checker); corrected the XML Sitemap description (no automatic ping to Google or Bing: the page links to Google Search Console and Bing Webmaster Tools); documented the "Preload CSS" section of the Minify module.

= 1.8.5 =
* New: one-time, dismissible invitation to review the plugin on WordPress.org (dashboard only, after 10 days and at least two modules used).

= 1.8.4 =
* Dashboard: the green badge on unlocked premium modules names the active plan ("Actif Solo", "Actif Pro"…).

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
  * **Debug Manager** — Toggle WP_DEBUG + view / analyze debug.log.
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
Title & Meta AI, FAQ Schema and brute-force protection (off until you enable it) become free; bring your own Anthropic or OpenAI key. Security and Minify fixes, JS minification paused. Using Minify? Clear your page cache/CDN after updating. Behind a proxy or CDN? See the FAQ.

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
