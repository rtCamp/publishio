# Action Hooks

## TOC

- [Action Hooks](#action-hooks)
  - [TOC](#toc)
  - [`Publish_With_AI/get_template_part_$slug`](#Publish_With_AIget_template_part_slug)

## `Publish_With_AI/get_template_part_$slug`

Fires when a template part is requested.

```php
do_action( 'Publish_With_AI/get_template_part_' . $slug, string $slug, ?string $name, array $args );
```

### Parameters

- `$slug (string)`: Template slug.
- `$name (string|null)`: Optional. Template variation name.
- `$args (array<string, mixed>)`: Optional. Data to pass to the template.
