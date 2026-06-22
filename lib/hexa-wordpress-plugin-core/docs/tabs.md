# Tabs Namespace

Namespace:

```text
Hexa\PluginCore\WpAdminTabs
```

Folder:

```text
src/WpAdminTabs/
```

## Purpose

The tabs namespace standardizes admin tab definitions and rendering.

Host plugins decide which tabs exist. The core provides a consistent registry, definition format, and an automatic Hexa core documentation tab.

## Required Pattern

Each tab has:

- ID
- label
- renderer
- optional capability
- optional status metadata, such as deprecated

Tab IDs are lowercase slugs:

```text
overview
shortcodes
settings
update-center
```

Do not use labels as IDs. Do not include emojis in IDs.

## Automatic Hexa Core Tab

Classes:

```text
CoreTabConfig
CoreTabModule
CoreTabRenderer
```

Host dashboards expose two filters:

```php
$tabs = apply_filters( 'example_dashboard_tabs', $tabs );

if ( apply_filters( 'example_dashboard_render_tab', false, $tab_id ) ) {
    return;
}
```

Then the host plugin registers the core tab:

```php
( new CoreTabModule(
    new CoreTabConfig(
        [
            'tabs_filter'   => 'example_dashboard_tabs',
            'render_filter' => 'example_dashboard_render_tab',
            'core_root'     => __DIR__ . '/lib/hexa-wordpress-plugin-core',
            'readme_path'   => __DIR__ . '/lib/hexa-wordpress-plugin-core/README.md',
            'library_path'  => __DIR__ . '/HEXA_PLUGIN_CORE_LIBRARY.md',
        ]
    )
) )->register();
```

The core tab renders:

- README file locations
- friendly explanation
- technical explanation
- README-style guide
- activity log demo
