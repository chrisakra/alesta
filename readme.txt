=== Alesta ===
Contributors: alestaplugin
Tags: seo, sitemap, htaccess, robots, broken links
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SEO and technical toolkit for WordPress: XML sitemap, .htaccess optimization (Gzip/cache/HTTPS), robots.txt editor, broken links scanner.

== Description ==

**Alesta** is a minimalist WordPress toolkit focused on SEO and technical performance. Every module does one thing and does it well.

Modules shipped in this version:

* **XML Sitemap** — Generates a sitemap.xml, automatically pings Google and Bing when content is updated, and lets you exclude specific post types or individual posts.
* **Gzip, Cache, HTTPS optimization** — Clean, safe manipulation of the .htaccess file: enable Gzip compression, browser cache headers for static assets, and HTTP to HTTPS redirection. One-click backup and restore included.
* **Robots.txt editor** — Edit, backup, restore and check the accessibility of your robots.txt directly from the WordPress admin.
* **Broken links scanner (4xx / 5xx)** — Scheduled scan of your internal links to detect 404, 500 and other HTTP errors, with a sortable results table.

Alesta is built in the same product family as the Alesta AI suite. More Free modules will be added block by block over the next releases (maintenance mode, health check, GDPR banner, and more).

= Privacy and GDPR =

Alesta does not send any data outside your site, except the public pings sent to Google and Bing when you regenerate your XML sitemap (standard sitemap behavior). No tracking, no telemetry, no connection to third-party servers.

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

Technically yes, but if you already use Yoast or RankMath for your XML sitemap, disable their sitemap module to avoid duplicates.

== Changelog ==

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
