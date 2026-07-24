=== Alesta ===
Contributors: alestacomputer
Tags: seo, meta description, meta tags, open graph, referencement
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Le SEO minimaliste pour WordPress. Editez le titre SEO et la meta description de chaque page. Rien de plus. Rien d'inutile.

== Description ==

**Alesta** est un plugin SEO ultra minimaliste, pense pour ceux qui veulent l'essentiel sans usine a gaz.

Une seule fonctionnalite, faite proprement :

* **Titre SEO** editable par page/article
* **Meta description** editable par page/article
* **Open Graph** (og:title, og:description, og:url, og:type) automatique
* **Twitter Card** (twitter:card) automatique

Pas de reglages compliques, pas d'onboarding a rallonge, pas de premium a debloquer, pas de dashboard bavard. Vous ouvrez un article, vous remplissez 2 champs, vous publiez. C'est tout.

= Pourquoi Alesta ? =

Les plugins SEO existants (Yoast, RankMath, All in One SEO) sont excellents mais font aussi 50 autres choses (schema, sitemap, redirections, breadcrumbs, analyse de contenu, notifications...). Si vous voulez juste editer votre title et votre meta description sans installer 3 Mo de code, Alesta fait ca.

= Ce qu'Alesta ne fait PAS =

* Analyse de mots-cles
* Sitemap XML (WordPress le fait deja depuis la version 5.5)
* Schema.org / Rich snippets
* Redirections
* Breadcrumbs
* Analyse de lisibilite
* Notifications, badges, upsell

Si vous avez besoin de ces fonctions, utilisez Yoast ou RankMath. Alesta est fait pour ceux qui veulent juste le strict necessaire.

= Compatibilite =

* Compatible avec tous les themes
* Compatible avec Classic Editor et Block Editor (Gutenberg)
* Zero conflit connu avec les autres plugins SEO (mais bien sur, activez-en un seul a la fois si vous voulez que les meta tags ne se dupliquent pas)

= Vie privee / RGPD =

Alesta n'envoie aucune donnee a l'exterieur. Aucune connexion externe, aucun tracking, aucune telemetrie. Le code est court, ouvert, et auditable en 5 minutes.

== Installation ==

1. Uploadez le dossier `alesta` dans `/wp-content/plugins/` (ou installez via l'admin WordPress > Extensions > Ajouter).
2. Activez le plugin dans le menu Extensions.
3. Editez un article ou une page : une metabox **Alesta - SEO** apparait sous l'editeur. Remplissez le titre SEO et la meta description.
4. C'est tout.

== Frequently Asked Questions ==

= Est-ce compatible avec Yoast SEO / RankMath ? =

Techniquement oui, mais il est preferable de n'activer qu'un seul plugin SEO a la fois pour eviter que les meta tags soient dupliques dans le `<head>`.

= Pourquoi mes changements ne se voient pas ? =

Pensez a vider le cache de votre plugin de cache (WP Rocket, W3 Total Cache, etc.). Les meta tags sont injectees dans le HTML de la page, un cache peut les figer.

= Ou est le fichier de configuration ? =

Il n'y en a pas. Tout se passe dans la metabox de chaque article/page. C'est volontaire : moins il y a de reglages, moins il y a de bugs.

= Peut-on editer les meta tags de la page d'accueil ? =

Si votre page d'accueil est une page WordPress (Reglages > Lecture > Page statique), oui, comme n'importe quelle page. Si c'est le fil des articles, non pour l'instant.

== Changelog ==

= 1.0.0 =
* Premiere version publique.
* Meta title + meta description editables par page/article.
* Open Graph et Twitter Card automatiques.

== Upgrade Notice ==

= 1.0.0 =
Premiere version publique.
