# Entity Sources

Namespace: `Hexa\PluginCore\EntitySources`

This namespace provides an optional canonical website/entity source shared by host plugins. The primary entity may be a WordPress user or a post-backed Person, Organization, Publication, or profile. Sites without a primary entity remain valid.

## Classes

- `CanonicalEntityResolver`: resolves saved settings, extracts ACF/native fields, and finds the WordPress user bound to a post-backed entity.
- `PrimaryEntityManager`: owns site-type and entity settings plus one-time legacy migration.
- `PrimaryEntityModule`: registers migration, search, and save hooks.
- `PrimaryEntityAjaxController`: guarded AJAX saving.
- `PrimaryEntityRenderer`: shared selector, bound-author preview, field inventory, and consumer status UI.
- `EntityFieldInspector`: groups native and ACF values while masking credential-like fields.

```php
$manager = new \Hexa\PluginCore\EntitySources\PrimaryEntityManager(
    [
        'entity_option' => 'hws_primary_entity',
        'site_type_option' => 'hws_site_type',
        'site_types' => [ 'news_outlet' => 'News Outlet', 'other' => 'Other' ],
        'sources' => [
            'wordpress_user' => [ 'label' => 'WordPress Author', 'kind' => 'user' ],
        ],
        'ajax_action' => 'hws_save_primary_entity',
    ]
);
$bootstrap->add_module( new \Hexa\PluginCore\EntitySources\PrimaryEntityModule( $manager ) );
```

Host plugins consume `CanonicalEntityResolver::resolve()` and keep legacy settings as read-only migration fallbacks. Test with `php tests/entity-sources.php`, then verify optional/no-entity, direct-user, and post-with-bound-author cases in the live UI and each consuming plugin.
