# UI Namespace

Namespace:

```text
Hexa\PluginCore\WpAdminComponents
```

Folder:

```text
src/WpAdminComponents/
```

## Purpose

The UI namespace owns shared visual primitives for Hexa plugin admin screens.

Host plugins should use these primitives instead of rebuilding card, button, tooltip, and collapsible markup differently in each plugin.

## Classes

```text
CoreUi
ColorControl
ColorPalette
ElementorPaletteDetector
DetailedColorPicker
```

## Components

```text
render_assets()
card()
subcard()
collapsible()
pill()
tooltip()
copy_button()
ColorControl::render()
ColorPalette::render()
ElementorPaletteDetector::render()
DetailedColorPicker::render()
```

## Example

```php
use Hexa\PluginCore\WpAdminComponents\CoreUi;

CoreUi::render_assets();

echo CoreUi::card(
    [
        'title'     => 'Plugin Status',
        'body_html' => '<p>All systems are healthy.</p>',
        'meta_html' => CoreUi::pill( 'Healthy', 'success' ),
    ]
);
```

## Rule

If a host plugin needs cards, subcards, collapsibles, tooltips, status pills, copy buttons, brand-aware isolated color controls, saved color palettes, or Elementor palette detection, add the missing parameter or helper here first.
