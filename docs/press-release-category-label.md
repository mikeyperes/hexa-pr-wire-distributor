# Press release category label

Use `[hpr_press_release_categories]` in a shortcode-enabled field or Elementor Shortcode widget. It uses the current loop release; `post_id="123"` selects a release. `separator=" · "` overrides the default comma-space separator.

Output excludes the `press-release` slug, the configured generic import category, and any category named Press Release. Invalid category links are omitted before joining. Missing categories, only excluded categories, invalid posts and other post types return an empty string.

```html
<a class="hpr-press-release-category" href="https://example.com/category/client/">Client</a>
```

Names, URLs and separators are escaped. No wrapper, CSS, JavaScript or assets are loaded. Templates own the prefix and appearance. Registration adds no visitor output until used.
