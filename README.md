# Inventory Bureau — Batches 1 and 2

Internal inventory platform for an organization with storage areas across three cities and a Central Administrative Bureau. The bureau approves operations but does not own physical stock. Identity, access control, the application shell, cities, storage areas and employee responsibility are implemented. Inventory and stock-operation workflows are not implemented.

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

The explicitly invoked development seeder only runs with APP_ENV=local. It creates one verified account per role with a different cryptographically random password, displays each password once in the terminal, and skips existing accounts without resetting credentials. The default seeder creates nothing. Never deploy development accounts or use this seeder in production. Provisioning the first production administrator remains an operator responsibility; administrators can then create stock employees through User Management.

## Implemented

- Login/logout, hashed passwords, database sessions, CSRF protection and login throttling.
- Password reset, email verification, profile/password settings, and starter-kit two-factor/passkey support.
- Public registration GET and POST endpoints and registration UI removed.
- `admin` and `stock_employee` roles, serialized in `auth.user.role`; role cannot be changed through profile mass assignment.
- Central `access-administration` Laravel gate; unauthorized requests return 403.
- Responsive collapsible sidebar, header, profile menu, breadcrumbs and light/dark/system appearance.
- Database-backed organizational dashboard counts and each employee's current assignment; inventory and approval metrics remain placeholders.
- Searchable, paginated city and storage-area management, active/inactive status, city/status filters and storage details.
- Employee assignment, reassignment and unassignment with retained history and concurrency protection.
- Read-only My Storage Area view scoped to the signed-in employee.
- Minimal user management with password-setup email links and current assignment visibility.
- PostgreSQL configuration, migrations, private local filesystem, development factories/seeder, tests and CI.

Both roles can view the future inventory, movements, approvals, documents and reports placeholders. Only administrators can manage organizational records, assignments and users. A stock employee may view only their currently assigned storage area's details. Future inventory records and approval actions still need resource-scoped policies when implemented.

## Organizational model and assignment rules

Run `php artisan migrate` to apply `2026_10_15_100000_create_organizational_structure.php` to an existing installation. It only adds tables and indexes; it does not reset existing accounts or data. Do not use `migrate:fresh` on an operational database.

| Entity | Relationships and purpose |
| --- | --- |
| City | Has many storage areas; name, optional unique code, description and active status. No fixed limit of three cities. |
| StorageArea | Belongs to exactly one city; globally unique code, description, location and active status. Has many assignments, a current assignment and a current responsible employee. |
| StorageAreaAssignment | Belongs to a storage area and a user; `assigned_at` and nullable `ended_at` preserve each period of responsibility. |
| User | Has many historical assignments and a `currentStorageArea()` relation. |

An assignment is current when `ended_at IS NULL`. Two partial unique indexes enforce at most one current assignment per area and per employee, including under concurrent writes. They use PostgreSQL SQL also supported by SQLite tests; other database engines are not supported by this migration. See the [PostgreSQL partial-index documentation](https://www.postgresql.org/docs/current/indexes-partial.html).

`ChangeStorageAreaAssignment` locks the target user and storage-area row inside a transaction, checks eligibility and the assignment ID the administrator last saw, closes the old assignment and inserts the new one atomically. Stale requests receive a validation error instead of overwriting newer responsibility. Serialization conflicts are retried and uniqueness conflicts become validation messages. Storage-area updates use the same area lock, preventing a new assignment from bypassing concurrent deactivation. Future assignment writers must use this action and the same lock order.

Only stock employees can be assigned. An employee managing another area is not silently moved: the administrator must explicitly end the old responsibility first. Ending and reassigning retain rows; assignment history is never deleted through the application. Foreign keys restrict deletion of referenced cities, storage areas and users. Self-deletion remains available for users without assignment history; users with history receive an explanatory error and remain logged in.

Codes are normalized to uppercase and accept letters, digits, hyphens and underscores (maximum 30 characters). City codes may be omitted. No cities or areas are automatically seeded.

### Permissions and pages

| Capability | Administrator | Stock employee |
| --- | --- | --- |
| Cities, storage-area lists/forms | Manage all | Denied |
| Storage-area details | View all, manage assignment, view history | Own current area only; no other employee list or history |
| Employee Assignments | Current responsibilities, available employees, history via area details | Denied |
| User Management | Search/list, create stock employees, send setup links | Denied |
| My Storage Area | Use organization management pages | Own current assignment or helpful empty state |
| Dashboard | Real organization counts | Own assigned area, city and status |

Management routes and Form Requests reuse the central administrator gate. `StorageAreaPolicy` enforces per-record access; the employee page derives its identity from the session, never a supplied user ID. All write endpoints validate input and retain the existing CSRF/session protection. Role, password and verification fields cannot be injected into employee creation.

### Employee account setup

User Management accepts name and email only. The server stores a random hashed initial password and sends a password-setup link using Laravel's [password broker](https://laravel.com/framework/docs/passwords). It never exposes that password or the setup token to the administrator. The employee chooses their password using the existing reset flow and verifies their email before entering the workspace. The account list labels email-verification status explicitly; account suspension and role editing are not part of this batch.

If delivery fails, the account is retained and the administrator can retry **Send setup link** after correcting mail configuration. Links expire and resend requests are throttled. With the local log mailer, messages appear in `storage/logs/laravel.log`, not a real inbox. Protect these logs because setup and verification URLs are sensitive. Production requires a working mail transport.

### Local organization fixtures

```sh
php artisan db:seed --class=OrganizationDevelopmentSeeder
```

This explicit, local-only seeder creates three clearly labeled example cities, six example areas, three stock employees and three assignments. It preserves existing records and does not reopen ended assignments. Fixture-account passwords are random and never printed; use password reset to sign into those accounts and complete email verification. Factories are available for each organizational model. The default seeder remains empty, and both development seeders reject production.

### Assumptions awaiting client confirmation

- City deactivation does not cascade to storage areas or assignments. Active storage areas in an inactive city may still receive assignments; no unconfirmed cascading rule is imposed.
- Storage-area deactivation prevents new assignments but preserves current responsibility and employee visibility. End responsibility explicitly when required.
- “Active assignment” means an unended assignment even when its area is inactive. Such an employee remains unavailable for another area until that assignment ends.
- Assignments require the stock-employee role, not prior email verification; unverified employees still cannot enter the authenticated workspace until verification.
- The bureau remains an administrative concept, not a storage area. Actual city names, addresses, account suspension rules and changes to assignment cardinality still require organizational input.

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

`php artisan test` defaults to an isolated in-memory SQLite database through phpunit.xml. To test PostgreSQL, create a **separate disposable** database whose name ends in `_test` (e.g. inventory_test), then export DB_CONNECTION=pgsql, DB_DATABASE=inventory_test and the connection credentials in the shell before invoking the same command. Environment variables override the PHPUnit defaults. Never run tests against an operational database: RefreshDatabase and DatabaseMigrations recreate tables. CI runs the suite on PostgreSQL 18, including four real race tests using parallel PHP processes, separate database connections and a synchronization barrier. SQLite runs skip those four PostgreSQL-only cases; database uniqueness is still tested directly.

PowerShell example after configuring the test connection:

```powershell
$env:DB_CONNECTION = 'pgsql'
$env:DB_DATABASE = 'inventory_test'
php artisan test
Remove-Item Env:DB_CONNECTION, Env:DB_DATABASE
```

Wayfinder generates TypeScript route/action helpers during `npm run build` and `npm run dev`. Before a standalone TypeScript check on a fresh checkout, run `php artisan wayfinder:generate --with-form` or build first. `composer ci:check` combines frontend checks, PHP formatting/static analysis and tests.

## Structure and future modules

- `app/Enums/Role.php`: initial role vocabulary; extend alongside gates/policies.
- `app/Models/User.php`: authentication identity with enum casting and hidden secrets.
- `app/Providers/AppServiceProvider.php`: centralized gate and secure production defaults.
- `app/Http/Controllers/`: HTTP interactions; Settings and Fortify retain starter-kit validation.
- `app/Http/Requests/`: validated organizational, assignment, employee and settings input.
- `app/Models/City.php`, `StorageArea.php`, `StorageAreaAssignment.php`: organizational relationships.
- `app/Actions/ChangeStorageAreaAssignment.php`: atomic responsibility changes.
- `app/Policies/StorageAreaPolicy.php`: per-area read authorization.
- `routes/web.php`: authenticated module routes and Administration authorization.
- `database/migrations/`: authentication, framework infrastructure, roles and organizational structure. No inventory schema.
- `resources/js/components/`, `layouts/`, `pages/`: shared UI and Inertia pages.
- `tests/Feature/`: Batch 1 regression coverage, organization management, user setup, scoped reads, retained history, seeders and concurrency. `tests/Support/assignment-worker.php` is the race-test subprocess.
- `config/filesystems.php`: local disk rooted at storage/app/private, with direct serving disabled.

Add future Inventory, Stock Movements, Approvals, Documents, Notifications and Audit History functionality using normal Laravel controllers, Form Requests, models and policies. Introduce actions/services only when concrete business logic warrants them. Enforce storage-area scope on the backend. Do not infer scope from a browser-supplied role or storage ID.

Future stock-changing actions must use database transactions and enforce approval independently from physical execution. Confirm the workflow before adding tables. The separate assignment table can support future multiple responsibilities by revisiting the two partial unique indexes and associated validation, without losing assignment history.

## Storage

Use Laravel's Storage abstraction and the private local disk. No file binaries belong in PostgreSQL. No upload/download endpoints or PDF generation are implemented. Do not link private storage into public/. Future downloads need authentication and resource authorization.

The S3 disk already accepts AWS_ENDPOINT, AWS_BUCKET, AWS_DEFAULT_REGION and path-style configuration, so an S3-compatible provider can be added later with its Flysystem adapter. No cloud credentials, integration or adapter is installed in this batch.

## Next batch

For Batch 3, confirm the inventory catalogue fields, quantities versus individually tracked assets, units and storage-level visibility before implementing item records. Resolve the organizational assumptions above. Confirm approval/rejection states, physical execution responsibility and transfers before implementing any stock-changing workflow. Design broader audit history and account deactivation before operational deployment.

Item catalogues, categories, quantities, stock entries/exits, transfers, approval execution, notifications, signatures, PDFs, image uploads and cloud integration remain planned.

## Validation performed on this workstation

On 2026-10-15, PHP 8.4.26, Node 22.23.3 and PostgreSQL 18 were used:

- PostgreSQL migrations completed successfully.
- Batch 2 PHPUnit: 69 tests, 627 assertions passed against PostgreSQL, including the existing Batch 1 suite and four concurrent-connection cases.
- SQLite: 65 tests, 607 assertions passed; the four PostgreSQL-only race cases were skipped. A Batch 1 role-index rollback issue exposed by the new migration tests was corrected for SQLite compatibility.
- Pint, Larastan, ESLint, Prettier and TypeScript checks passed.
- Vite production build and Composer manifest validation passed.
- Live HTTP smoke checks passed for both roles: login with CSRF, database-backed session, role-specific Inertia properties, all Batch 2 management list/create routes, My Storage Area route access, administrator access enforcement, logout and subsequent guest redirect. Results are saved locally in the ignored `.local-tools/batch2-http-verification.txt`.
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
