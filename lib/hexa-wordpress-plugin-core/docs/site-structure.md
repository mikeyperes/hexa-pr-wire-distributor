# Site Structure

Namespace:

```text
Hexa\PluginCore\SiteStructure
```

Purpose:

- Define reusable critical page blueprints.
- Store assigned WordPress page IDs through host-provided option prefixes.
- Create managed WordPress pages without overwriting existing external pages.
- Create and delete WordPress navigation menus.
- Create custom URL menu items in a selected menu and optional parent menu item.
- Add all assigned pages to a selected menu.
- Attach a whole menu blueprint or one assigned page beneath a specific menu item.
- Render the admin UI and register admin-AJAX actions from the same host configuration.

## Classes

```text
PageStructureManager
SiteStructureAjaxController
SiteStructureRenderer
```

## Host Responsibilities

The host plugin provides:

- `pages`: nested page blueprint keyed by stable page keys.
- `menu_structures`: named menu blueprints with `page_keys`.
- `option_prefix`: where assigned page IDs are stored when callback storage is not used.
- `template_option_prefix`, `default_templates`, or template callbacks when starter text should be stored or applied to created pages.
- optional assignment callbacks when a host stores page IDs in one settings array.
- `managed_meta_key` and `managed_key_meta_key`: used to prevent deleting pages the host did not create.
- optional page detail renderer when the admin UI should expose slugs, edit/view links, or host-specific metadata under each row.
- renderer section flags: `show_pages` and `show_menus` when a host needs page assignment and menu building on separate tabs.
- AJAX action names.
- A nonce action and nonce value.

The shared core must not hard-code a host plugin slug, page keys, menu names, or option names.

## Example

```php
use Hexa\PluginCore\SiteStructure\PageStructureManager;
use Hexa\PluginCore\SiteStructure\SiteStructureAjaxController;
use Hexa\PluginCore\SiteStructure\SiteStructureRenderer;

$manager = new PageStructureManager([
    'option_prefix' => 'example_page_',
    'managed_meta_key' => '_example_managed_page',
    'managed_key_meta_key' => '_example_page_key',
    'pages' => [
        'profile' => [
            'title' => 'Profile',
            'slug' => 'profile',
            'children' => [
                'education' => ['title' => 'Education', 'slug' => 'education'],
            ],
        ],
    ],
    'menu_structures' => [
        'header' => [
            'title' => 'Header',
            'description' => 'Primary navigation.',
            'page_keys' => ['profile', 'education'],
        ],
    ],
]);

(new SiteStructureAjaxController($manager, [
    'nonce_action' => 'example_ajax',
    'actions' => [
        'assign_page' => 'example_assign_page',
        'create_page' => 'example_create_page',
        'delete_page' => 'example_delete_page',
        'create_navigation_menu' => 'example_create_navigation_menu',
        'delete_navigation_menu' => 'example_delete_navigation_menu',
        'create_menu_item' => 'example_create_menu_item',
        'attach_page_to_menu_item' => 'example_attach_page_to_menu_item',
        'attach_menu_structure' => 'example_attach_menu_structure',
        'menu_inventory' => 'example_menu_inventory',
    ],
]))->register();

echo (new SiteStructureRenderer($manager, [
    'nonce' => wp_create_nonce('example_ajax'),
    'actions' => [
        'assign_page' => 'example_assign_page',
        'create_page' => 'example_create_page',
        'delete_page' => 'example_delete_page',
        'create_navigation_menu' => 'example_create_navigation_menu',
        'delete_navigation_menu' => 'example_delete_navigation_menu',
        'create_menu_item' => 'example_create_menu_item',
        'attach_page_to_menu_item' => 'example_attach_page_to_menu_item',
        'attach_menu_structure' => 'example_attach_menu_structure',
        'menu_inventory' => 'example_menu_inventory',
    ],
]))->render();
```

## Testing

At minimum:

```bash
find src/SiteStructure -name '*.php' -print0 | xargs -0 -n1 php -l
```

In a WordPress host plugin, verify:

- the admin page renders the critical page table
- the admin page renders navigation menu controls
- registered AJAX actions exist for the host action names
- assigning an existing page updates the host option
- creating a managed page stores the page ID and metadata
- deleting an unmanaged assigned page only unassigns it
- creating a custom menu item validates the selected menu and parent item
- adding all assigned pages skips unassigned pages and does not duplicate existing page items
- attaching one assigned page validates that the parent menu item belongs to the selected menu
- saving starter/template text persists through the host storage path
- applying starter/template text updates the assigned page content only through the registered AJAX action
- page detail rows refresh after assignment, creation, deletion, and slug changes

## Rendering Pages And Menus Separately

`SiteStructureRenderer` defaults to showing both page assignment and menu builder sections. Set `show_pages => false` for a menu-only toolbox, or `show_menus => false` when the host page should only manage critical page assignments and starter/template content.
