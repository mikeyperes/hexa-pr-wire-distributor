# Field Structures

Namespace:

```text
Hexa\PluginCore\FieldStructures
```

Use this namespace for reusable displays and status checks around ACF field groups, custom post types, taxonomies, and option-backed feature structures.

Host plugins still own the exact fields they register. Core owns the common dashboard structure used to explain, toggle, test, and document those fields.

## Classes

```text
FieldStructureManager
FieldStructureRenderer
```

## Render Example

```php
use Hexa\PluginCore\FieldStructures\FieldStructureRenderer;

echo ( new FieldStructureRenderer() )->render(
    [
        [
            "id"           => "post_faqs",
            "label"        => "Post FAQ ACF",
            "type"         => "acf",
            "setting_key"  => "post_faqs_acf_enabled",
            "enabled"      => true,
            "registered"   => function(): bool { return function_exists("acf_get_field_group") && acf_get_field_group("group_example"); },
            "acf_group_key" => "group_example",
            "location"     => "post editor",
            "fields"       => [ "question", "answer", "enabled_for_schema" ],
            "code_example" => "[smp_post_faqs]",
        ],
    ],
    [ "save_action" => "example_save_settings", "nonce" => Example_Admin_Ajax::nonce() ]
);
```

Rules: use one row per structure, include identity details, include use instructions and test reports where available, and do not put plugin-specific ACF arrays inside Hexa Core.
