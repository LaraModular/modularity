# LaraModularity

Modular architecture support for Laravel applications. This package enables you to organize your Laravel application into modules with automatic configuration loading, view registration, and dependency validation.

## Installation

Add the package via Composer with a path repository:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../laramodular-modularity",
      "options": {
        "symlink": true
      }
    }
  ],
  "require": {
    "laramodular/modularity": "@dev"
  }
}
```

Then run:

```bash
composer update laramodular/modularity
```

## Setup

Define your module load order in `composer.json`'s `extra` section:

```json
{
  "extra": {
    "laravel-modules": {
      "order": [
        "Core",
        "Identity"
      ]
    }
  }
}
```

That's it! No files needed in your `app/` folder.

## Module Structure

Modules can be either:

### Local Modules (in `app/` directory)

```
app/
  ModuleName/
    Configs/
      app.php
      database.php
    Database/
      Migrations/
        DatabaseMigrator.php
      Seeders/
        DatabaseSeeder.php
    Models/
    Views/
```

### Package Modules (composer packages)

Add to your package's `composer.json`:

```json
{
  "extra": {
    "laravel-module": {
      "name": "Core",
      "namespace": "LaraModule\\Core",
      "root": "src"
    }
  }
}
```

## Available Commands

### Migrate Modules

```bash
# Migrate all modules
php artisan module:migrate

# Migrate specific module
php artisan module:migrate Core

# Migrate specific class
php artisan module:migrate Core CreateUsersTable

# Fresh migration (drop all tables)
php artisan module:migrate --fresh

# Migrate and seed
php artisan module:migrate --seed
```

### Seed Modules

```bash
# Seed all modules
php artisan module:seed

# Seed specific module
php artisan module:seed Identity

# Seed specific class
php artisan module:seed Identity UserSeeder

# Truncate and seed
php artisan module:seed --fresh
```

## Features

- **Zero App Boilerplate**: Configuration lives in `composer.json` - `app/` folder stays clean
- **Auto-Discovery**: Automatically discovers modules from both `app/` directory and installed composer packages
- **Config Merging**: Module configs override app configs using `array_replace_recursive()`
- **View Cascade**: App views take precedence over module views
- **Ordered Loading**: Modules load in the order defined in `composer.json`
- **Custom Bootstrap**: Extends Laravel's `LoadConfiguration` bootstrap for seamless integration
- **Laravel Octane Ready**: Intended to be used with Laravel Octane to eliminate bootstrapping overhead

## How It Works

1. **ModuleRegistry**: Central registry that discovers modules from both local `app/` directory and composer packages
2. **ModuleConfigBootstrap**: Extends Laravel's config loader to merge module configs and register view paths
3. **Commands**: Artisan commands for running migrations, seeders, and dependency validation module-by-module

## Docker/Sail Integration

If using Docker/Sail with symlinked path repositories, add volume mounts to your `compose.yaml`:

```yaml
volumes:
  - '.:/var/www/html'
  - '../laramodular-modularity:/var/www/laramodular-modularity'
```

## License

MIT
