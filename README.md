# Inventory Bureau - Batches 1-4

Internal inventory platform for an organization with storage areas across three cities and a Central Administrative Bureau. The bureau approves operations but does not own physical stock. Identity, access control, the application shell, cities, storage areas and employee responsibility are implemented. The inventory catalog, private photographs and read-only recorded-stock visibility are implemented. Stock-operation workflows remain planned.

## Stack and prerequisites

Based on the [official Laravel React starter kit](https://github.com/laravel/react-starter-kit), upstream commit `717b8f55aefd82d25d4119eaebdc8e3a72b8d7e5`.

- PHP 8.3+ (PHP 8.4 recommended), Composer 2; Laravel 13, Eloquent, Fortify.
- Required PHP extensions include ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, PDO, pdo_pgsql, gd, bcmath, session, tokenizer and xml. Enable pdo_sqlite for the isolated in-memory test suite.
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
- Database-backed organizational dashboard counts and each employee's current assignment; catalog and scoped stock metrics are database-backed; approval metrics remain planned.
- Searchable, paginated city and storage-area management, active/inactive status, city/status filters and storage details.
- Employee assignment, reassignment and unassignment with retained history and concurrency protection.
- Read-only My Storage Area view scoped to the signed-in employee.
- Employee directory, profile/role/status management, immediate access revocation, private onboarding, depot responsibility and administrative history.
- PostgreSQL configuration, migrations, private local filesystem, development factories/seeder, tests and CI.

Both roles can use the scoped inventory catalog and view movements, approvals, documents and reports placeholders. Only administrators can manage organizational records, assignments and users. A stock employee may view only their currently assigned storage area's details. Inventory reads enforce resource-scoped policies; approval actions remain unimplemented.

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
| User Management | Employee directory, profiles, roles, account status, onboarding, responsibility and history | Denied |
| My Storage Area | Use organization management pages | Own current assignment or helpful empty state |
| Dashboard | Real organization counts | Own assigned area, city and status |

Management routes and Form Requests reuse the central administrator gate. `StorageAreaPolicy` enforces per-record access; the employee page derives its identity from the session, never a supplied user ID. All write endpoints validate input and retain the existing CSRF/session protection. Employee passwords and verification cannot be injected into creation. Initial role and status are explicit validated administrator inputs; subsequent role and status changes use separate endpoints.

### Employee account setup

Batch 4 extends User Management with role selection, account status, optional profile fields, employee details, audit history and secure email/private-handoff onboarding. Passwords are selected by employees through the existing Laravel reset flow. See the Batch 4 section below for the current procedure.

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
- The bureau remains an administrative concept, not a storage area. Actual city names, addresses and changes to assignment cardinality still require organizational input.

Password and verification notifications do not emit secrets through the log mailer. Configure real mail delivery or use the private administrator-assisted onboarding flow described in Batch 4. Preserve the application key, use HTTPS, APP_ENV=production, APP_DEBUG=false and SESSION_SECURE_COOKIE=true in deployment. Serve only the `public/` directory.

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
- `database/migrations/`: authentication, framework infrastructure, roles and organizational structure and the Batch 3 catalog/inventory schema.
- `resources/js/components/`, `layouts/`, `pages/`: shared UI and Inertia pages.
- `tests/Feature/`: Batch 1 regression coverage, organization management, user setup, scoped reads, retained history, seeders and concurrency. `tests/Support/assignment-worker.php` is the race-test subprocess.
- `config/filesystems.php`: local disk rooted at storage/app/private, with direct serving disabled.

Add future Stock Movements, Approvals, Documents, Notifications and Audit History functionality using normal Laravel controllers, Form Requests, models and policies. Introduce actions/services only when concrete business logic warrants them. Enforce storage-area scope on the backend. Do not infer scope from a browser-supplied role or storage ID.

Future stock-changing actions must use database transactions and enforce approval independently from physical execution. Confirm the workflow before adding tables. The separate assignment table can support future multiple responsibilities by revisiting the two partial unique indexes and associated validation, without losing assignment history.

## Storage

Use Laravel's Storage abstraction and the private local disk. No file binaries belong in PostgreSQL. Inventory photographs use authorized upload and streaming endpoints; PDF generation remains unimplemented. Do not link private storage into public/. Every inventory download requires authentication and resource authorization.

The S3 disk already accepts AWS_ENDPOINT, AWS_BUCKET, AWS_DEFAULT_REGION and path-style configuration, so an S3-compatible provider can be added later with its Flysystem adapter. No cloud credentials, integration or adapter is installed in this batch.

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

## Batch 3: catalog and stock visibility

The catalog is now implemented. Stock requests, movements, approvals and opening-balance imports remain future work.

### Domain and schema

- `item_categories`: unique code, name, optional description and parent, active status. Administrators manage categories; employees can read them. Parent relationships are shown on the searchable category list and as ancestry paths in editors. Cycle validation runs inside a transaction; a PostgreSQL advisory lock serializes hierarchy changes.
- `items`: shared definitions with unique reference, category, tracking mode, unit, description, brand, model, notes and active status. Definitions are not owned by a storage area. Deactivation preserves all related records.
- `item_assets`: unique asset reference, parent item, serial number, description and condition notes. These are descriptive registrations, not confirmed physical stock.
- `storage_area_inventory`: one record per item/area, decimal(18,3) quantity and nullable minimum, plus nullable `initialized_at`. Database constraints reject negatives and nonzero uninitialized quantities. Quantity is not mass assignable and there is no public quantity-write endpoint.
- `item_images`: exactly one item or asset owner, private disk/object references, original MIME/size/name, preview/thumbnail paths, caption, display order, primary marker, uploader and removal timestamp. Foreign keys restrict orphaning/deleting referenced records. Partial unique indexes permit at most one primary image per owner.

Tracking modes are `quantity_consumable`, `quantity_reusable`, and `individual_asset`. Classification is independent of category; antiques are not automatically individual assets. Units are piece, box, liter, kilogram and meter, with no conversions. Item locks serialize balance configuration, asset registration and mode changes. Mode changes cannot invalidate existing balances/assets; units cannot change once balance records exist. Cross-table tracking rules are enforced by these application transactions, so future import/operation code must preserve the same invariants.

### Recorded quantities and thresholds

Administrators configure an area's threshold from the item detail page. If no balance exists, configuration creates a zero-valued **uninitialized** record. This associates the definition with the area for visibility; it does not confirm that stock is physically present. Existing quantities and initialization markers are preserved. Clear the minimum to disable a threshold.

Uninitialized quantities serialize as null and display “Not initialized”; they never produce low-stock alerts. An initialized zero is a recorded zero. Low stock means strictly `quantity < minimum_quantity`, only when initialized and a minimum exists. Comparisons use SQL decimals / PHP BCMath, not JavaScript floats. Dashboard low-stock counts refer to item/area balance records; initialized item-type counts are distinct items. Quantities in different units are not summed.

Individual assets never receive quantity balances or directly editable location fields. Registered asset counts are separate from physical availability, which remains unavailable. Since there is no authorized asset-location association yet, asset records and their photographs are administrator-only. Employees can view only item definitions, images, balances and thresholds associated with their current assigned area; unassigned employees see an empty catalog. Existing inactive records remain readable within that scope.

Future approved operations must atomically append an immutable movement record and update the corresponding locked balance, with authorization, idempotency and audit history. Opening balances must use that controlled workflow and set initialization explicitly. Individual assets will need controlled location records instead of duplicate quantity balances. None of those operations is implemented in Batch 3.

### Pages and permissions

- Inventory catalog: server-paginated search by name/reference, category and mode filters, administrator status filter and primary thumbnails.
- Category list and create/edit forms; item create/edit/detail pages.
- Individual asset registration, edit and detail pages, linked from the parent item.
- Private photo galleries with upload, caption editing, numeric display order (lower first), primary selection and confirmed removal.
- Storage-area details and My Storage Area link to a paginated read-only inventory section.
- Dashboards retain organizational data and add real, scoped catalog, initialized-balance and low-stock counts.

All writes use backend administrator authorization. Item, area and image reads use resource policies. Route manipulation does not expand employee scope. No catalog deletion, stock editing, transfers, opening balances, approvals or financial features are exposed.

### Private photographs

Intervention Image 4 with GD decodes JPEG, PNG and WebP. Upload validation checks detected MIME, configurable size and pixel count before decoding. UUID-based paths never use the client filename. Original files are retained; generated WebP previews fit 1600×1600 and thumbnails fit 320×320 without upscaling. Previews are optimized static images with metadata stripped; originals may retain camera metadata. Animation is not processed.

Configure `INVENTORY_IMAGE_DISK=local` (private `storage/app/private`), `INVENTORY_IMAGE_MAX_KB=10240` and `INVENTORY_IMAGE_MAX_PIXELS=24000000`. Enable PHP GD with JPEG/PNG/WebP support and BCMath; EXIF is recommended for orientation. Set PHP `upload_max_filesize=12M`, `post_max_size=16M` or larger and use sufficient memory (512M on this workstation). Restart PHP after changing extensions or limits. Reverse-proxy request limits must also accommodate uploads.

The authenticated image endpoint authorizes the owner for every original, preview and thumbnail request. Responses are private/no-store and nosniff; originals download as attachments with generated filenames. Storage paths are hidden from serialized models; no public storage symlink or public object URL is used.

Uploads compensate file writes if processing/storage/database work fails. Removal first makes metadata inaccessible and promotes a replacement primary image, then deletes files. Failed removal retains soft-deleted paths for retry:

```sh
php artisan inventory:prune-images
```

Run this command periodically through your operational scheduler. A hard process crash during upload, or failed compensation while storage is unavailable, may leave private orphan files; failed compensation is logged and needs operator reconciliation. File storage and PostgreSQL cannot share one atomic transaction. Back up both together.

For a future OVHcloud migration, install Laravel's S3 Flysystem adapter, configure the existing S3 disk with private bucket access and OVH endpoint/region/credentials, verify streaming/permissions, then select that disk for new uploads. Existing rows retain their original disk; moving old objects requires a verified copy and metadata migration. No cloud credentials, adapter, bucket or external integration was added.

### Upgrade and verification

Apply additive migrations to the existing configured database, without resetting it:

```sh
composer install
php artisan migrate
npm ci
npm run build
```

Use a dedicated PostgreSQL test database; never point the test suite at development or production. Existing PostgreSQL test setup above applies. Run `composer ci:check` with test-database environment overrides, plus `npm run build`. The optional SQLite suite uses in-memory data; PostgreSQL remains authoritative for constraints and concurrent-connection tests.

Batch 3 tests cover category cycles and duplicates, item validation/deactivation, incompatible mode changes, asset metadata boundaries, threshold decimal validation, uninitialized stock, employee scope, private image retrieval, invalid/oversized images, primary selection/reorder, removal, missing files, database/file failure compensation and cleanup retries. Batch 1–2 regressions remain included.

Browser visual verification is pending because no browser is connected to the available tooling. Responsive layout, themes and interactive gallery behavior still need a visual review. Automated HTTP tests and a production build do not substitute for that review.

### Stock workflow planning (future Batch 5)

Confirm approval versus physical-execution responsibilities, opening-balance authorization, immutable movement history, idempotency, concurrency/locking, and controlled individual-asset locations before implementing stock operations. Account deactivation and employee administrative history are implemented in Batch 4; wider inventory audit history remains future work. Batch 3 stops at the catalog and read-only recorded-stock boundary.

Batch 3 final workstation verification (2026-10-15): PostgreSQL **83 tests / 828 assertions passed**; SQLite **79 tests / 808 assertions passed**, with four PostgreSQL race tests skipped. Pint, Larastan level 7, ESLint, Prettier, TypeScript, Composer validation and the Vite production build passed. The additive development migration completed. Live HTTP checks passed for both roles: login/CSRF/session, catalog and categories, administrator forms, scoped dashboard metrics, Batch 2 routes and logout. Logs remain in ignored `.local-tools/batch3-*.txt` files. Browser visual verification remains pending.

## Batch 4: employee administration and access

Administrators can search, filter, sort and paginate the employee directory; create either supported primary role; edit name, email, optional phone and administrative notes; manage account access; initiate password setup/reset; and manage depot responsibility from employee details. Filters include role, active status, assigned status, city and depot. Sorting supports name, email, creation date and last login. No employee list is downloaded in full.

The existing Batch 2 user controller, password broker, assignment action, assignment endpoint/history and PostgreSQL overlap constraints are reused. The role enum and central Laravel gates remain the authorization foundation. Shared frontend permission flags drive administrative navigation; role labels and capability summaries come from the server. A single primary role remains sufficient for the current model, so no role-table migration or permission package was introduced. Revoking all access means deactivating the account; a null primary role is not supported.

### Schema and migration

Run `php artisan migrate` against the existing configured database. The additive `2026_10_16_100000_add_employee_administration.php` migration adds:

- `users.is_active`, indexed and defaulting to true, preserving access for existing users.
- Nullable `phone` and `administrative_notes`.
- `auth_version`, an integer revocation counter, initially zero.
- Nullable `last_login_at`, populated by successful authentication events from this batch onward.
- `employee_audits`: actor, affected employee, action, timestamp and non-sensitive JSON details, with indexed restrictive foreign keys.

No historical activity or previous login timestamps are fabricated. Existing depot assignments and inventory records are preserved. Administrative notes and revocation counters are hidden from shared user serialization; notes are returned explicitly only on administrator employee pages.

### Role, account and responsibility safeguards

Role and status changes use transactions, a PostgreSQL transaction-level advisory lock and a target-user row lock. Account creation, administrative profile changes, actor revalidation and self-service deletion use the same administrative lock where necessary. Concurrent writers cannot demote, deactivate or delete the last active administrator. The transaction rechecks the actor's current administrative permission after acquiring the lock. Ordinary profile input cannot change role, active status, verification, sessions or audit data.

Administrators cannot deactivate themselves. Users with an unended depot assignment must have that assignment **explicitly ended first** before promotion to an incompatible role or account deactivation. The UI provides an end-assignment action on employee details. Reactivation restores account eligibility only; it never reopens an old assignment. Only active stock employees can receive new assignments, and inactive depots reject new responsibility. The existing one-active-depot-per-employee and one-active-employee-per-depot database constraints and stale-assignment checks remain in force.

Account deletion remains a self-service starter-kit feature only for accounts without protected business or administrative history. The last active administrator is protected there too. Administration exposes deactivation rather than employee deletion.

### Sessions and authentication

The configured Eloquent provider filters inactive accounts for password, session and remember-me retrieval. A login event check also rejects inactive users entering through alternate authentication paths. An account-access middleware reloads current database permissions for every authenticated web request, including settings and Fortify routes. It rejects inactive accounts and sessions whose captured `auth_version` differs from the database. Pending two-factor challenges capture the version before the password step and are rejected if access changes.

Role changes, status changes, administrative email changes and successful password resets increment the counter, clear remembered authentication and remove reset tokens. With the database session driver, only that user's session rows are deleted. For cookie, file, cache or other Laravel session drivers, the counter check is the secure fallback: an old session cannot regain access after reactivation. Existing sessions from before the migration are compatible only while the account's counter remains zero. Other users' sessions are not globally revoked.

This project has no Sanctum/personal API access-token system. A future API guard must apply the same active-account and revocation rules; it must not bypass the web middleware's security boundary. Revocation applies to subsequent requests, not cancellation of an operation already executing.

Changing email administratively clears verification, revokes sessions and removes old reset tokens. The employee must sign in and verify the new address through the normal signed verification route. Editing profile metadata cannot silently verify it.

### Safe onboarding on the internal network

1. Create the employee, select the appropriate role and status, and confirm their work email and identity. The initial password is random, hashed, and never shown to the administrator.
2. When a real mail transport is configured, the existing password-setup notification is sent. Details also offers a confirmed resend/reset action.
3. When email is unavailable, open employee details and confirm **Generate private setup link**. The authenticated POST response displays an expiring password-reset link once, plus a signed verification link for an unverified account. Deliver them privately to the verified recipient. Do not paste them into tickets, shared notes or chat logs.
4. The employee opens the setup link on their own device, chooses their password, and signs in. After sign-in, they open the verification link in the same browser. This follows the existing verification route and does not bypass two-factor authentication.
5. Assign depot responsibility separately when the employee is eligible.

The password broker stores a **hash** of the token, not plaintext. Password links expire after 60 minutes by default (`auth.passwords.users.expire`), are invalid after successful use, and are replaced when a new token is generated. Link issuance is rate limited and uses the broker's one-minute throttle. Verification signatures expire in 60 minutes and are bound to the intended account/email. A verified address cannot be silently replaced through this flow.

The private response is no-store/no-referrer and uses encrypted Inertia history. It cannot be reopened with GET or a refresh. Tokens are never placed in audit records, flash/session messages, public files or application log calls, and token/signature input is excluded from validation input flashing. Anyone receiving a setup link can set that account's password until it expires or is used, so the private handoff requires identity checks.

Authentication notifications are **suppressed for log/array and unrecognized mail transports outside automated tests**, to prevent password and verification links entering ordinary logs. Administrators receive an error if they request email delivery without a supported real transport. Public forgot-password keeps a non-disclosing response even when delivery is unavailable. Supported transport configuration includes SMTP and sendmail; no external provider integration was installed.

Use the configured `APP_URL` and Laravel mail/session configuration for the organization's hostname; no server address is hardcoded in this implementation. For internal-network use, configure HTTPS, production mode, debug off and secure cookies. Encrypted Inertia history requires a browser secure context (HTTPS, or localhost for development). Web server/proxy access and error logs must redact reset-token paths and verification signatures; do not enable request/response body logging on authentication or private-handoff routes. Production deployment itself remains outside this batch.

Forgotten two-factor factors and passkey recovery remain an operator recovery concern; password reset does not disable those factors.

### Administrative activity and dashboard

Lightweight append-only-through-the-application audit records capture account creation, changed profile field names, role changes, activation/deactivation, depot responsibility changes and password-reset initiation. Records identify the administrator and affected employee. Details contain only action-specific fields such as old/new role/status, depot identifiers, assignment identifiers and delivery method. Assignment history remains the business source of responsibility periods; the audit points to that history.

Passwords, reset/verification links, tokens, session cookies, phone values and administrative-note contents are not copied into audit details. Employee details displays paginated assignment and administrative history. No ordinary UI or write route can edit/delete audit records. This is lightweight application audit history, not cryptographically tamper-proof database logging. Referenced accounts cannot be deleted, preserving actors and targets.

The administrator dashboard adds total/active/inactive account counts, active administrators, depot employees and eligible unassigned employees. Existing unassigned-depot, organization and inventory metrics are reused. Employees receive no administrative counts. Last login means a successful authentication event recorded since this migration, not last page activity; earlier values display as not recorded.

### Verification and limitations

Use the dedicated `inventory_test` PostgreSQL database and the test-database environment overrides in the validation instructions above. Never point tests at development or production. Run `composer ci:check` and `npm run build`. PostgreSQL remains authoritative for row locks, advisory locks and partial unique indexes; optional SQLite concurrency cases are skipped.

On 2026-10-16 the full PostgreSQL suite passed **99 tests / 1061 assertions**, including three new concurrent-connection cases: simultaneous administrator demotions, cross-deactivation, and promotion versus depot assignment. The existing four assignment-race cases also passed. SQLite passed **92 tests / 1023 assertions**, skipping those seven PostgreSQL-only cases. Functional coverage includes onboarding expiry/single use, role/status/profile authorization, inactive login, old-session rejection, reactivation, pending two-factor revocation, targeted database-session deletion, assignment history, audit secrecy and all Batch 1-3 regressions.

Browser visual verification remains pending because no browser is connected. Actual SMTP delivery and production internal-network hosting have not been configured or tested. Batch 5 stock requests, approvals, execution, transfers and quantity updates remain unimplemented.

Pint, Larastan level 7, ESLint, Prettier, TypeScript, Composer manifest validation and the Vite production build passed. The additive development migration completed. Live HTTP checks passed for both roles: login, CSRF, database sessions, employee directory/create/detail/edit authorization, employee dashboard metrics, prior-batch routes, and logout. Local verification logs are in the ignored `.local-tools/batch4-*.txt` files.
