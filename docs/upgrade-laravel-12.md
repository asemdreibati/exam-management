# Upgrade to Laravel 12

The application moved from Laravel 8 (out of support, 78 security advisories
reported by `composer audit`) to **Laravel 12.69** on 2026-10-04.
`composer audit` now reports no advisories.

The application keeps its existing structure (`app/Http/Kernel.php`,
`app/Console/Kernel.php`, the providers listed in `config/app.php`). Laravel 12
still supports that structure, so the newer, slimmer skeleton was not adopted.

## Requirements

- **PHP 8.2 or newer** (was 8.1). Tested on PHP 8.4.
- The same PHP extensions as before, plus those Laravel 12 requires
  (`ctype`, `filter`, `hash`, `mbstring`, `openssl`, `session`, `tokenizer`).

## Dependency changes

| Package | Before | After | Notes |
|---|---|---|---|
| `laravel/framework` | 8.83 | 12.69 | |
| `laravel/sanctum` | 2 | 4 | The existing `personal_access_tokens` migration is unchanged. |
| `laravel/tinker` | 2.5 | 2.10 | |
| `spatie/laravel-permission` | 5 | 6 | Middleware namespace is `Spatie\Permission\Middleware`; config replaced with the v6 default (ours had no custom settings). |
| `maatwebsite/excel` | 3.1 | 3.1.64+ | Same major version. |
| `kyslik/column-sortable` | 6 | 7 | Same API. |
| `guzzlehttp/guzzle` | 7.0 | 7.8+ | |
| `fruitcake/laravel-cors` | 2 | removed | Laravel's built-in `Illuminate\Http\Middleware\HandleCors` reads the same `config/cors.php`. |
| `psr/simple-cache` | pinned 1.0 | unpinned | The pin conflicted with Laravel 12. |
| `laravelcollective/html` | 6 | removed earlier | Abandoned; replaced by plain Blade forms. |
| `livewire/livewire` | 2 | removed earlier | Unused. |
| `facade/ignition` (dev) | 2 | `spatie/laravel-ignition` 2 | Its successor. |
| `phpunit/phpunit` (dev) | 9 | 11 | |
| `nunomaduro/collision` (dev) | 5 | 8 | |
| `barryvdh/laravel-debugbar` (dev) | 3.6 | 3.16 | |

## Code changes

- **`app/Http/Kernel.php`**: built-in CORS middleware; Spatie 6 middleware
  namespace; `$routeMiddleware` renamed to `$middlewareAliases`.
- **Passwords**: `User` uses the `hashed` cast instead of a
  `setPasswordAttribute` mutator that always called `bcrypt()`. The cast hashes
  plain-text passwords and leaves existing hashes untouched. With the mutator,
  any framework code that writes an already-hashed password (Laravel 11+
  re-hashes passwords on `Auth::attempt` when the cost changes) would have
  hashed it twice and locked the user out. Existing cost-10 hashes keep
  working; a test checks this.
- **Permission tables migration**: refreshed from the v6 stub under the same
  filename. It creates the same tables and columns, and databases that
  already ran it skip it.
- **Tests**: PHPUnit 11 needs static data providers and `#[DataProvider]`
  attributes; `phpunit.xml` uses `<source>` instead of `<coverage>`; the user
  factory hashes its default password once with `Hash::make`.

Behaviour is unchanged: all 304 tests pass, including the 226 recorded
distribution scenarios and a smoke test that renders every page.

## Deploying

1. Make sure the server runs **PHP 8.2+**.
2. Deploy the code, then:
   ```
   composer install --no-dev --optimize-autoloader
   rm -f bootstrap/cache/*.php          # cached package/provider lists from Laravel 8
   php artisan migrate                  # nothing new to run on an existing database
   php artisan optimize:clear
   ```
3. If you cache config or routes in production, rebuild them:
   `php artisan config:cache && php artisan route:cache`.
4. Log in and run one distribution on a copy of production data to confirm.

Rollback: redeploy the previous commit and run `composer install` again. There
are no database changes to undo.
