# Schema Tools

Namespace: `Hexa\PluginCore\SchemaTools`

Core owns JSON-LD document normalization, graph utilities, duplicate-node merging, script rendering, and WordPress output hooks. Host plugins continue to own schema object selection and field mapping.

## Classes

- `SchemaGraph`: cleans nested schema values and supports graph composition.
- `SchemaDocumentRenderer`: normalizes documents, deduplicates graph nodes, and renders safe JSON or JSON-LD scripts.
- `SchemaInjector`: one-shot module that calls a host provider on a configured WordPress hook.
- `SchemaDashboardRenderer`: shared schema inspection UI.

```php
$bootstrap->add_module(
    new \Hexa\PluginCore\SchemaTools\SchemaInjector(
        [ ExampleSchemaProvider::class, 'current' ],
        [ 'hook' => 'wp_head', 'priority' => 2, 'script_id' => 'example-schema' ]
    )
);
```

Test normalization and node deduplication with `php tests/schema-document.php`. Host regression tests must compare graph types, IDs, relationships, and critical fields against the pre-migration frontend output.
