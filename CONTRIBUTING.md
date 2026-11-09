# Contributing

Thanks for helping improve Woo Performance Toolkit.

## Layout

- `src/` contains **pure logic** (settings schema, SQL builders, thresholds,
  recommendation rules, matchers). It must not call WordPress or WooCommerce
  functions so that it can be unit-tested with plain PHPUnit.
- `includes/` contains the **WordPress glue** (hooks, admin screens, `$wpdb`
  calls, WP-CLI). Keep it thin: decisions belong in `src/`.

## Setup

```bash
composer install
composer test      # PHPUnit
composer lint      # php -l on every file
composer phpcs     # WordPress Coding Standards (informational in CI)
```

## Guidelines

- Target PHP 8.1+, WordPress 6.4+, WooCommerce 8+.
- All SQL goes through `$wpdb->prepare()`. Builders in `src/` return templates
  using only `%i`, `%s` and `%d`; never concatenate values into SQL.
- Check capabilities and nonces on every admin action, and escape all output.
- New behaviour needs tests. Bug fixes need a regression test.
- New features must be toggleable and off (or harmless) by default.
- Keep commits focused and describe the why in the message.

## Pull requests

1. Fork and create a topic branch.
2. Make sure `composer test` and `composer lint` pass.
3. Update `CHANGELOG.md` and `readme.txt` when behaviour changes.
4. Open the pull request and describe how you tested it.

## Reporting security issues

Please do not open a public issue for vulnerabilities. Contact the maintainer
via https://saizul.com instead.
