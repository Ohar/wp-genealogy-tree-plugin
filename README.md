# Drevo Genealogy Trees

WordPress plugin for uploading static genealogy HTML exports from "Drevo Zhizni" as isolated packages and rendering them via shortcode.

## Install

Copy this folder to:

```text
wp-content/plugins/drevo-genealogy/
```

Then activate the plugin in WordPress admin.

The optional MU loader is included at:

```text
mu-plugins/drevo-genealogy-loader.php
```

Use it only if the plugin should be force-loaded from `wp-content/mu-plugins/`.

## Usage

Admin page:

```text
Tools -> Genealogy Trees
```

Shortcode:

```text
[genealogy_tree slug="your-tree-slug"]
```

During import, home address and phone fields are removed from all person cards. Living person cards are additionally filtered for public display.
