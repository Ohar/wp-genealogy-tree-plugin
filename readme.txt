=== Drevo Genealogy Trees ===
Contributors: ohar
Tags: genealogy, family tree, html import, shortcode
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/license/mit/

Imports static genealogy HTML exports into a public site and displays a selected tree with a shortcode.

== Description ==

WARNING — UNTRUSTED CODE MUST NOT BE IMPORTED. This plugin publishes imported HTML, JavaScript, CSS, images and JSON as a public static package. Import only an export that you created yourself or whose contents, provenance, licenses and scripts you have independently reviewed.

The site administrator is solely responsible for reviewing each imported package, obtaining all required rights to its code, images and personal data, and deciding whether it may be published. Imported packages can contain executable JavaScript and links to third-party resources. The plugin author makes no guarantee that an imported package is safe, complete, lawful, private or suitable for publication.

This plugin is intended for a trusted administrator to import a static genealogy export and embed it with `[genealogy_tree slug="your-tree-slug"]`. Imported trees are stored below `wp-content/genealogy/` and are publicly accessible to visitors who know or discover their URL.

The plugin itself does not send telemetry or make external network requests. It does not include third-party libraries. It can remove some contact fields and reduce some living-person cards from supported exports, but this is a best-effort convenience filter only. It is not a complete anonymization, privacy, legal-compliance or consent-management tool. Review every generated file, including HTML, JavaScript, JSON and image metadata, before publishing.

Compatibility with an export format does not imply affiliation with, endorsement by, or authorization from the owner of that export software or trademark.

Support email: code@ohar.name

== Security model ==

Active HTML and JavaScript are a deliberate feature of an imported static export. The plugin is designed for a trusted site administrator, equivalent to an administrator who intentionally installs a trusted plugin or deploys trusted static files to the same site.

The administrator is responsible for reviewing each package and ensuring it is authorized for publication. The plugin does not inspect, sanitize, license, or make an imported package safe. Do not use this plugin as a multi-user file-upload service or grant untrusted users access to the import screen, server filesystem, or the exported files.

For protection from an otherwise trusted package that is later compromised, or from a mistaken review, host published trees on a separate origin, such as `trees.example.com`, rather than on the same WordPress origin. A warning or a liability disclaimer cannot provide this technical isolation.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin, or install it from the WordPress.org directory when available.
2. Activate Drevo Genealogy Trees.
3. Open Tools > Genealogy Trees.
4. Before uploading, inspect the archive and ensure you have the right to publish every included file and all personal data.
5. Enter a title and a lowercase slug, then upload the ZIP export. The archive must contain an HTML file in its root directory.
6. Add `[genealogy_tree slug="your-tree-slug"]` to a post or page.

For large packages, a trusted administrator may place a package under `wp-content/genealogy/{slug}/` through a secure server-management workflow, then register it on the plugin page. The folder must contain `index.html`.

== Frequently Asked Questions ==

= Is it safe to upload any ZIP archive? =

No. Never upload an archive from an untrusted person or source. The package may contain executable JavaScript and public personal data. Review its contents first and use only material you are authorized to publish.

= Does the privacy filter guarantee anonymization? =

No. It is a best-effort filter for a limited supported export structure. You must independently inspect all output and obtain any required consent before publication.

= Where are imported trees stored? =

They are stored in `wp-content/genealogy/` and served as public static files. Removing the plugin does not automatically remove those files or the plugin option.

= Does the plugin contact external services? =

No. The plugin's own code makes no external requests. An imported package can still contain its own external links or scripts; review and remove them as necessary.

== Privacy ==

The plugin stores the administrator-supplied tree title and slug in WordPress options. It stores imported genealogy files in `wp-content/genealogy/`; these files may contain personal data and are publicly accessible. The plugin does not transmit data to the author or other external services.

Before importing or publishing, the site administrator must determine the lawful basis for publication, obtain any required permissions, inspect all files for personal data and third-party resources, and define a retention and removal procedure. The plugin's optional filtering is not a guarantee of anonymization.

To remove imported data, delete the relevant folder under `wp-content/genealogy/` and remove or deactivate the plugin. Deactivation and uninstall do not delete imported trees automatically.

== Changelog ==

= 1.0.0 =
* Initial release.
