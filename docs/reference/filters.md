# Filter Hooks

## TOC

- [Filter Hooks](#filter-hooks)
  - [TOC](#toc)
  - [`Publish_With_AI/template_args`](#Publish_With_AItemplate_args)
- [`Publish_With_AI/template_file_names`](#Publish_With_AItemplate_file_names)
  - [`Publish_With_AI/located_template`](#Publish_With_AIlocated_template)
  - [`Publish_With_AI/template_paths`](#Publish_With_AItemplate_paths)

## `Publish_With_AI/template_args`

Filters the arguments passed to a template part.

```php
apply_filters( 'Publish_With_AI/template_args', array $args, string $slug, ?string $name );
```

### Parameters

- `$args (array<string, mixed>)`: Data passed to the template.
- `$slug (string)`: Template slug.
- `$name (string|null)`: Optional. Template variation name.

## `Publish_With_AI/template_file_names`

Filters the list of template file names to locate.

```php
apply_filters( 'Publish_With_AI/template_file_names', array $templates, string $slug, ?string $name );
```

### Parameters

- `$templates (array<int, string>)`: List of template file names to locate.
- `$slug (string)`: Template slug.
- `$name (string|null)`: Optional. Template variation name.

## `Publish_With_AI/located_template`

Filters the located template path.

```php
apply_filters( 'Publish_With_AI/located_template', string|false $template, string[] $templates );
```

### Parameters

- `$template (string|false)`: Full path to the located template, or false if not found.
- `$templates (string[])`: Template files that were searched for.

## `Publish_With_AI/template_paths`

Filters the list of paths to search for templates.

```php
apply_filters( 'Publish_With_AI/template_paths', array $paths );
```

### Parameters

- `$paths (array<int, string>)`: List of paths to search for templates, keyed by their priority (lower number = higher priority).
