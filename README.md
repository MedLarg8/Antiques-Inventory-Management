# Inventory Bureau — Batch 1

Internal inventory platform foundation for an organization with storage areas across three cities and a Central Administrative Bureau. The bureau approves operations but does not own physical stock. This batch contains identity, access control and the application shell only.

## Stack and prerequisites

Based on the [official Laravel React starter kit](https://github.com/laravel/react-starter-kit), upstream commit `717b8f55aefd82d25d4119eaebdc8e3a72b8d7e5`.

- PHP 8.3+ (PHP 8.4 recommended), Composer 2; Laravel 13, Eloquent, Fortify.
- Required PHP extensions include ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, PDO, pdo_pgsql, session, tokenizer and xml. Enable pdo_sqlite for the isolated in-memory test suite.
- Node 22.12+ and npm; React 19, TypeScript, Inertia 3, Vite, Tailwind 4, shadcn/ui and Lucide.
- PostgreSQL (validated with 18). SQLite is only used for optional isolated tests.
- PHPUnit, Pint, Larastan, ESLint and Prettier.

## Install

```sh
composer install
cp .env.example .env
php artisan key:generate
npm ci
```

On PowerShell, use `Copy-Item .env.example .env`. Create a PostgreSQL login and database using your database administrator account. Use an interactive password prompt so the password is not recorded in shell history:

```sh
createuser --pwprompt inventory_app
createdb --owner=inventory_app inventory
```

Configure the local, ignored `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=inventory
DB_USERNAME=inventory_app
DB_PASSWORD=
```

Set DB_PASSWORD to your locally chosen secret; the example intentionally contains no usable credentials. Set APP_URL to the application URL and APP_NAME to your organization name.

```sh
php artisan migrate
php artisan db:seed --class=DevelopmentUserSeeder
npm run build
composer run dev
```

Open http://localhost:8000. Alternatively run `php artisan serve` and `npm run dev` in separate terminals. For compiled assets, only `php artisan serve` is necessary after the build.

The explicitly invoked development seeder only runs with APP_ENV=local. It creates one verified account per role with a different cryptographically random password, displays each password once in the terminal, and skips existing accounts without resetting credentials. The default seeder creates nothing. Never deploy development accounts or use this seeder in production. Production provisioning is an operator responsibility until administrator account management is implemented.

## Implemented

- Login/logout, hashed passwords, database sessions, CSRF protection and login throttling.
- Password reset, email verification, profile/password settings, and starter-kit two-factor/passkey support.
- Public registration GET and POST endpoints and registration UI removed.
- `admin` and `stock_employee` roles, serialized in `auth.user.role`; role cannot be changed through profile mass assignment.
- Central `access-administration` Laravel gate; unauthorized requests return 403.
- Responsive collapsible sidebar, header, profile menu, breadcrumbs and light/dark/system appearance.
- Role-specific dashboard placeholders and seven future-module pages, with no fabricated data.
- PostgreSQL configuration, migrations, private local filesystem, development factories/seeder, tests and CI.

Both roles can view the shared placeholder pages; only administrators can view Administration. This does not grant access to future inventory records or approval actions. Those require resource-scoped policies when implemented.

Password resets and verification mail use the log mailer in development. Configure a real Laravel mail transport for deployment; log mail is not email delivery. Preserve the application key, use HTTPS, APP_ENV=production, APP_DEBUG=false and SESSION_SECURE_COOKIE=true in deployment. Serve only the `public/` directory.

## Validation

```sh
php artisan test
composer lint:check
composer types:check
npm run check
npm run types:check
npm run build
```

`php artisan test` defaults to an isolated in-memory SQLite database through phpunit.xml. To test PostgreSQL, create a **separate disposable** database (e.g. inventory_test), then export DB_CONNECTION=pgsql, DB_DATABASE=inventory_test and the connection credentials in the shell before invoking the same command. Environment variables override the PHPUnit defaults. Never run tests against an operational database: RefreshDatabase recreates tables. CI runs the suite on PostgreSQL 18.

Wayfinder generates TypeScript route/action helpers during `npm run build` and `npm run dev`. Before a standalone TypeScript check on a fresh checkout, run `php artisan wayfinder:generate --with-form` or build first. `composer ci:check` combines frontend checks, PHP formatting/static analysis and tests.

## Structure and future modules

- `app/Enums/Role.php`: initial role vocabulary; extend alongside gates/policies.
- `app/Models/User.php`: authentication identity with enum casting and hidden secrets.
- `app/Providers/AppServiceProvider.php`: centralized gate and secure production defaults.
- `app/Http/Controllers/`: HTTP interactions; Settings and Fortify retain starter-kit validation.
- `app/Http/Requests/`: validated settings input.
- `routes/web.php`: authenticated module routes and Administration authorization.
- `database/migrations/`: users, password reset tokens, sessions and framework cache/queue infrastructure; role migration. No inventory schema.
- `resources/js/components/`, `layouts/`, `pages/`: shared UI and Inertia pages.
- `tests/Feature/`: authentication, settings, registration exclusion and authorization.
- `config/filesystems.php`: local disk rooted at storage/app/private, with direct serving disabled.

Add future Locations/Storage Areas, Inventory, Stock Movements, Approvals, Documents, Notifications and Audit History functionality using normal Laravel controllers, Form Requests, models and policies. Introduce actions/services only when concrete business logic warrants them. Enforce storage-area scope on the backend. Do not infer scope from a browser-supplied role or storage ID.

Future stock-changing actions must use database transactions and enforce approval independently from physical execution. Confirm the workflow before adding tables. A separate assignment relation can support changing employee/storage responsibilities without baking a permanent one-to-one constraint into users.

## Storage

Use Laravel's Storage abstraction and the private local disk. No file binaries belong in PostgreSQL. No upload/download endpoints or PDF generation are implemented. Do not link private storage into public/. Future downloads need authentication and resource authorization.

The S3 disk already accepts AWS_ENDPOINT, AWS_BUCKET, AWS_DEFAULT_REGION and path-style configuration, so an S3-compatible provider can be added later with its Flysystem adapter. No cloud credentials, integration or adapter is installed in this batch.

## Next batch

Confirm city/storage definitions, employee assignment lifecycle and visibility boundaries before implementing storage management and an inventory catalogue. Decide quantity versus individually tracked assets, units, low-stock rules, approval/rejection states, physical execution responsibility and the transfer procedure. Design audit history and account deactivation before deploying operational workflows.

Stock entries/exits, transfers, approval execution, signatures, PDFs, cloud integration and user-management screens remain planned.

## Validation performed on this workstation

On 2026-10-15, PHP 8.4.26, Node 22.23.3 and PostgreSQL 18 were used:

- PostgreSQL migrations completed successfully.
- PHPUnit: 46 tests, 293 assertions passed against PostgreSQL and separately against in-memory SQLite.
- Pint, Larastan, ESLint, Prettier and TypeScript checks passed.
- Vite production build and Composer manifest validation passed.
- Live HTTP smoke checks passed for both roles: login with CSRF, database-backed session, role-specific Inertia properties, administrator access enforcement, logout and subsequent guest redirect.
- Browser visual verification could not run because no browser was connected to the available browser tooling. Responsive and theme behavior still need a visual review.

## Current workstation quick start

The preinstalled PHP 8.0 and Node 20 were insufficient. Compatible portable runtimes were placed in the ignored `.local-tools/` directory; global installations were not changed. PostgreSQL uses a separate local cluster at port 55432, with randomly generated credentials in the ignored `.env`. These machine-local files are not included in a fresh clone.

In PowerShell from the project directory:

```powershell
. .\.local-tools\activate.ps1
php .local-tools/composer.phar run dev
```

If port 8000 is already serving the app, open http://127.0.0.1:8000 instead. Initial development login credentials are in the ignored `.local-tools/development-accounts.txt`; no password is committed. Treat that local file as a secret and remove it after recording the credentials securely.

After a machine restart, start this project's isolated PostgreSQL cluster before starting Laravel:

```powershell
& 'C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe' -D "$PWD\.local-tools\pgdata" -l "$PWD\.local-tools\postgres.log" -o '-h 127.0.0.1 -p 55432' start
```

To stop that cluster, use the same `pg_ctl.exe` with `-D "$PWD\.local-tools\pgdata" stop`. It is separate from the pre-existing PostgreSQL service. On other machines, follow the standard installation instructions above.
