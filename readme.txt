=== Alesta ===
Contributors: alestaplugin
Tags: seo, meta description, meta tags, open graph, twitter card
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The minimalist SEO plugin for WordPress. Edit SEO title and meta description for every page or post. Nothing else. Nothing unnecessary.

== Description ==

**Alesta** is an ultra minimalist SEO plugin, built for people who want the essentials without the bloat.

One feature, done well:

* **SEO Title** editable per page or post
* **Meta Description** editable per page or post
* **Open Graph** (og:title, og:description, og:url, og:type) generated automatically
* **Twitter Card** (twitter:card) generated automatically

No complex settings, no long onboarding, no premium upsells, no chatty dashboard. Open a post, fill two fields, publish. That is all.

= Why Alesta? =

Existing SEO plugins (Yoast, RankMath, All in One SEO) are excellent, but they also do fifty other things (schema, sitemap, redirects, breadcrumbs, content analysis, notifications, and more). If you just want to edit your title and meta description without installing three megabytes of code, Alesta does that.

= What Alesta does NOT do =

* Keyword analysis
* XML sitemap (WordPress has done that natively since 5.5)
* Schema.org and rich snippets
* Redirects
* Breadcrumbs
* Readability analysis
* Notifications, badges, upsell banners

If you need those features, use Yoast or RankMath. Alesta is for people who want only the strict essentials.

= Compatibility =

* Works with any theme
* Works with the Classic Editor and the Block Editor (Gutenberg)
* No known conflict with other SEO plugins (but of course activate only one at a time to prevent duplicated meta tags)

= Privacy and GDPR =

Alesta sends zero data outside your site. No external connection, no tracking, no telemetry. The code is short, open, and auditable in five minutes.

== Installation ==

1. Upload the `alesta` folder to `/wp-content/plugins/` (or install directly from WordPress admin: Plugins > Add New).
2. Activate the plugin under the Plugins menu.
3. Edit any page or post: an **Alesta - SEO** metabox appears below the editor. Fill in the SEO title and meta description.
4. Done.

== Frequently Asked Questions ==

= Is it compatible with Yoast SEO or RankMath? =

Technically yes, but you should activate only one SEO plugin at a time to avoid duplicated meta tags in the page `<head>`.

= Why are my changes not visible? =

Remember to purge your cache plugin (WP Rocket, W3 Total Cache, and similar). Meta tags are injected into the page HTML, and a cache can freeze them.

= Where is the settings page? =

There is none. Everything happens inside the metabox on each page or post. This is intentional: fewer settings mean fewer bugs.

= Can I edit meta tags for the homepage? =

If your homepage is a WordPress page (Settings > Reading > Static page), yes, like any other page. If it is the post feed, not yet.

== Changelog ==

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

= 1.0.2 =
Metadata cleanup only. Safe to update.

= 1.0.1 =
Metadata update only. Safe to update.

= 1.0.0 =
First public release.
