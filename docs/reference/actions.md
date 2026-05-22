# Action Hooks

## TOC

- [Action Hooks](#action-hooks)
  - [TOC](#toc)
  - [`publish_with_ai/get_template_part_$slug`](#publish_with_aiget_template_part_slug)

## `publish_with_ai/get_template_part_$slug`

Fires when a template part is requested.

```php
do_action( 'publish_with_ai/get_template_part_' . $slug, string $slug, ?string $name, array $args );
```

### Parameters

- `$slug (string)`: Template slug.
- `$name (string|null)`: Optional. Template variation name.
- `$args (array<string, mixed>)`: Optional. Data to pass to the template.
