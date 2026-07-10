# Brand Colors

Namespace:

```text
Hexa\PluginCore\BrandColors
```

Folder:

```text
src/BrandColors/
```

## Purpose

Brand color helpers keep host plugins from rebuilding the same HWS Base Tools Brand Assets lookup and color conversion logic.

## Classes

```text
BrandColorProvider
```

## Contract

- `BrandColorProvider::primary_color($fallback)` returns the HWS Base Tools primary color, normalized as a six-character hex value.
- `BrandColorProvider::secondary_color($fallback)` returns the HWS Base Tools secondary color.
- `BrandColorProvider::payload($fallback)` returns source labels, hex values, RGB strings, and the Brand Assets admin URL.
- `BrandColorProvider::elementor_payload($fallback_primary, $fallback_secondary)` returns Elementor primary/secondary color and font-family tokens when Elementor site settings exist.
- `BrandColorProvider::elementor_palette()` returns every Elementor system/custom color as normalized display rows.
- `BrandColorProvider::rgb_string($hex)` converts a hex value to `rgb(r, g, b)`.
- `Hexa\PluginCore\WpAdminComponents\ColorControl::render()` owns the visual picker/hex/RGB/swatch/copy/import control.
- `Hexa\PluginCore\WpAdminComponents\ElementorPaletteDetector::render()` owns the reference-only Elementor palette detector.
- `Hexa\PluginCore\WpAdminComponents\ColorPalette::render()` owns multi-color saved palettes and can compose the Elementor detector.
- `Hexa\PluginCore\WpAdminComponents\DetailedColorPicker::render()` owns the paired primary/secondary visual picker and optional font controls.

## Host Plugin Rule

Host plugins pass setting keys and wire AJAX persistence. Core owns the reusable visual structure and HWS brand color lookup.

```php
use Hexa\PluginCore\BrandColors\BrandColorProvider;
use Hexa\PluginCore\WpAdminComponents\ColorControl;

$brand = BrandColorProvider::payload('#2d5277');

echo ColorControl::render([
    'key' => 'accent_color',
    'label' => 'Accent color',
    'value' => $settings['accent_color'] ?? $brand['primary_color'],
    'default' => $brand['primary_color'],
    'import_brand' => true,
]);
```

## Isolated Color Control

Use `ColorControl::render()` when a feature needs one color field. It is storage-agnostic and does not require the palette structure.

The control is isolated from the palette wrapper. Callers may hide any visual part with `show_picker`, `show_hex_input`, `show_rgb`, `show_hex_code`, `show_swatch`, or `show_copy`.

```php
use Hexa\PluginCore\WpAdminComponents\ColorControl;

echo ColorControl::render([
    'key' => 'button_color',
    'label' => 'Button color',
    'value' => $settings['button_color'] ?? '#3157d5',
    'hex_input_class' => 'my-plugin-button-color',
    'show_rgb' => false,
    'show_hex_code' => false,
]);
```

## Generic Color Palette

Use `ColorPalette::render()` when a feature needs a named set of saved color fields and an optional reference-only Elementor palette.

The core owns the card/grid/picker/detector UI. The host plugin only passes field keys, labels, values, and classes used by its save code.

```php
use Hexa\PluginCore\WpAdminComponents\ColorPalette;

echo ColorPalette::render([
    'title' => 'Colors',
    'description' => 'Edit any value and save the feature settings.',
    'colors' => [
        [
            'key' => 'primary_color',
            'label' => 'Primary color',
            'value' => $settings['primary_color'] ?? '#3157d5',
            'hex_input_class' => 'my-plugin-color',
        ],
        [
            'key' => 'secondary_color',
            'label' => 'Secondary color',
            'value' => $settings['secondary_color'] ?? '#111827',
            'hex_input_class' => 'my-plugin-color',
        ],
    ],
    'elementor_detector' => [
        'title' => 'Elementor palette',
        'button_label' => 'Load Elementor colors',
    ],
]);
```

## Elementor Palette Detector

Use `ElementorPaletteDetector::render()` by itself when a screen only needs the Elementor reference palette without any saved color fields.

```php
use Hexa\PluginCore\WpAdminComponents\ElementorPaletteDetector;

echo ElementorPaletteDetector::render([
    'title' => 'Elementor palette',
    'description' => 'Reference only. Copy any value into another field.',
]);
```

## Detailed Color Picker

Use this when a feature needs primary and secondary colors together, optional Elementor token import, and optional font controls.

Visual example:

```text
Detailed Color Picker
+----------------------+----------------------+
| Primary color        | Secondary color      |
| Picker Hex RGB Copy  | Picker Hex RGB Copy  |
| Swatch               | Swatch               |
+----------------------+----------------------+
[Import Elementor colors and fonts]
```

```php
use Hexa\PluginCore\BrandColors\BrandColorProvider;
use Hexa\PluginCore\WpAdminComponents\DetailedColorPicker;

$brand = BrandColorProvider::payload('#2d5277');

echo DetailedColorPicker::render([
    'title' => 'Loop item design tokens',
    'primary' => [
        'key' => 'primary_color',
        'value' => $settings['primary_color'] ?? $brand['primary_color'],
        'hex_input_class' => 'plugin-primary-color',
    ],
    'secondary' => [
        'key' => 'secondary_color',
        'value' => $settings['secondary_color'] ?? $brand['secondary_color'],
        'hex_input_class' => 'plugin-secondary-color',
    ],
    'show_primary' => true,
    'show_secondary' => true,
    'show_elementor_import' => true,
    'show_fonts' => true,
    'fonts' => [
        [
            'key' => 'primary_font_family',
            'token' => 'primary_font_family',
            'label' => 'Primary font family',
            'value' => $settings['primary_font_family'] ?? '',
        ],
        [
            'key' => 'secondary_font_family',
            'token' => 'secondary_font_family',
            'label' => 'Secondary font family',
            'value' => $settings['secondary_font_family'] ?? '',
        ],
    ],
]);
```
