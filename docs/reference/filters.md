# Filter Hooks

## TOC

- [Filter Hooks](#filter-hooks)
  - [TOC](#toc)
  - [`publish_with_ai/template_args`](#Publish_With_AItemplate_args)
- [`publish_with_ai/template_file_names`](#Publish_With_AItemplate_file_names)
  - [`publish_with_ai/located_template`](#Publish_With_AIlocated_template)
  - [`publish_with_ai/template_paths`](#Publish_With_AItemplate_paths)

## `publish_with_ai/template_args`

Filters the arguments passed to a template part.

```php
apply_filters( 'publish_with_ai/template_args', array $args, string $slug, ?string $name );
```

### Parameters

- `$args (array<string, mixed>)`: Data passed to the template.
- `$slug (string)`: Template slug.
- `$name (string|null)`: Optional. Template variation name.

## `publish_with_ai/template_file_names`

Filters the list of template file names to locate.

```php
apply_filters( 'publish_with_ai/template_file_names', array $templates, string $slug, ?string $name );
```

### Parameters

- `$templates (array<int, string>)`: List of template file names to locate.
- `$slug (string)`: Template slug.
- `$name (string|null)`: Optional. Template variation name.

## `publish_with_ai/located_template`

Filters the located template path.

```php
apply_filters( 'publish_with_ai/located_template', string|false $template, string[] $templates );
```

### Parameters

- `$template (string|false)`: Full path to the located template, or false if not found.
- `$templates (string[])`: Template files that were searched for.

## `publish_with_ai/template_paths`

Filters the list of paths to search for templates.

```php
apply_filters( 'publish_with_ai/template_paths', array $paths );
```

### Parameters

- `$paths (array<int, string>)`: List of paths to search for templates, keyed by their priority (lower number = higher priority).
