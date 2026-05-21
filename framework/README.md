# Publish with AI - Framework

The core rtCamp plugin framework provides a set of reusable and composable, "Open-Code" PHP classes. These files work best when left as close to the original as possible, to make it easy to pull in updates from the main repository. However, they can be modified by plugins as needed.

## Principles

- **Reusability**: The framework is designed to be used across multiple plugins without modification. It should provide a solid foundation that can be extended or modified as needed by individual plugins.
- **Composability**: Plugins can choose to modify only the parts of the framework they need to, allowing for a balance between maintainability and ownership.

> [!IMPORTANT]
> Before making modifications to the framework, ask yourself if the change is something that would be useful to other plugins.
> If _yes_, consider contributing the change back to the main repository so that all plugins can benefit from it.
> If _no_, consider how to best implement the change inside your `inc/` directory without modifying the core framework files.

## Contents

````bash
framework/
├── README.md # 🎯 You are here.
│
│   # Contracts set expectations for how classes should be structured and interact with each other.
├── Contracts/
│   ├── Abstracts/
│   │   ├── Abstract_Post_Type.php
│   │   ├── Abstract_REST_Controller.php
│   │   └── Abstract_Taxonomy.php
│   ├── Interfaces/
│   │   ├── CLI_Command.php # WordPress CLI command classes.
│   │   └── Registrable.php # Classes that hook into WordPress with actions/filters.
│   └── Traits/
│       └── Singleton.php   # Singleton antipattern.
│
│   # Core framework traits and utility classes are designed to be composable and overloadable.
├── AssetLoaderTrait.php    # Trait for loading assets (scripts/styles/blocks).
├── AutoloaderTrait.php     # Trait for Composer autoloader.
├── Encryptor.php           # Simple encryption/decryption utility class.
└── TemplateLoaderTrait.php # Trait for (over-)loading plugin template parts

## Testing

Tests for the framework are located in [`tests/php/Framework`](../tests/php/Framework)

. They should have complete unit test coverage and should not contain any plugin-specific tests or dependencies.

```bash
# Optional: restart wp-env with coverage enabled
npm run wp-env stop && npm run wp-env start -- xdebug=coverage

# Run tests
npm run test:php
npm run test:php -- tests/php/Framework
````
