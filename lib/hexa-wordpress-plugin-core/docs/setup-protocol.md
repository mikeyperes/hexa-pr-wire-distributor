# Setup Protocol

All host plugins must initialize `hexa/plugin-core` the same way.

## Goal

The core owns reusable behavior. The host plugin owns identity and site-specific choices.

Host identity includes:

- plugin slug
- plugin basename
- plugin version
- plugin path
- plugin URL
- GitHub repository
- admin page slug
- WordPress capability

These values must be passed through `PluginContext`.

## Standard Bootstrap Sequence

```php
use Hexa\PluginCore\CoreBootstrap\CoreBootstrap;
use Hexa\PluginCore\CoreRuntime\PluginContext;

$context = new PluginContext(
    [
        'slug'        => 'example-plugin',
        'basename'    => plugin_basename( __FILE__ ),
        'version'     => '1.0.0',
        'path'        => plugin_dir_path( __FILE__ ),
        'url'         => plugin_dir_url( __FILE__ ),
        'github_repo' => 'owner/example-plugin',
        'admin_page'  => 'example-plugin',
        'capability'  => 'manage_options',
    ]
);

$core = new CoreBootstrap( $context );

$core
    ->add_module( $module_one )
    ->add_module( $module_two )
    ->boot();
```

## Module Rule

Modules implement:

```php
Hexa\PluginCore\CoreContracts\ModuleInterface
```

Modules must not execute behavior at file include time. They register WordPress hooks only inside:

```php
public function register(): void
```

## Boot Rule

`CoreBootstrap::boot()` is idempotent. Calling it twice must not double-register modules.

## Host Plugin Adapter Rule

A host plugin may write adapter classes in its own namespace.

Example:

```text
HWS\BaseTools\Admin\Shortcodes\ShortcodesModule
```

That adapter may consume:

```text
Hexa\PluginCore\ShortcodeRegistry\ShortcodeRegistry
```

But the shared core must not reference the host adapter namespace.

