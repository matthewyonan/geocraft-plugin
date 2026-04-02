# GeoCraft Plugin

WordPress plugin scaffold for GeoCraft AI GEO content publishing and analytics workflows.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Composer 2+

## Local Development

```bash
composer install
composer lint
composer test
```

## Project Structure

- `geocraft-plugin.php` plugin entry point and hook registration
- `includes/` core plugin classes
- `admin/` admin view and static assets
- `languages/` translation template placeholder
- `tests/phpunit/` PHPUnit suite and bootstrap
- `tests/integration/` integration test placeholder
- `.wordpress-org/` WordPress.org asset placeholders

## CI/CD

- Pull requests run PHPCS, PHPUnit, and plugin check workflow.
- Git tags (`v*`) build a plugin zip and trigger WordPress.org deploy action.
