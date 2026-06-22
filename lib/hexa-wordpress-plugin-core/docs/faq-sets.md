# FAQ Sets

Namespace:

```text
Hexa\PluginCore\FaqSets
```

Use this module when a host plugin needs repeatable FAQ sets with questions, WYSIWYG answers, shortcode output, and FAQPage JSON-LD schema.

## Class

- `FaqSetManager`: sanitizes sets, normalizes items, resolves primary sets, prepares safe answer links, builds FAQPage schema, and renders reusable list or accordion output.

## Save Admin Input

```php
use Hexa\PluginCore\FaqSets\FaqSetManager;

$manager = new FaqSetManager();
$sets = $manager->sanitizeSets( $raw_sets );
update_option( "my_plugin_faq_sets", $sets );
```

## Resolve A Set

```php
$set = $manager->resolveSet(
    get_option( "my_plugin_faq_sets", [] ),
    "primary",
    get_option( "my_plugin_primary_faq_set", "" )
);
```

## Render FAQ Output

```php
echo $manager->renderFaqs(
    $set,
    [
        "style" => "accordion",
        "inject_schema" => true,
    ]
);
```

## Render Schema Only

```php
echo $manager->renderSchemaScript( $set["items"] ?? [] );
```

Host plugins should keep option names and shortcode names in the host layer. The core owns normalization, schema construction, and reusable output behavior.
