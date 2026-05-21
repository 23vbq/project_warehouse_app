# Felgapol WMS

A warehouse management system for tracking inventory, processing warehouse documents, and monitoring stock levels across storage locations.

> Frontend designed with [Claude Design](https://claude.ai/design).

<img width="1866" height="936" alt="image" src="https://github.com/user-attachments/assets/8ea9e452-6ede-46c4-855c-006f09ecd4c0" />


---

## Features

- **Product catalog** — manage SKU/EAN registry with product types (finished goods, semi-finished, raw materials, consumables) and minimum stock level alerts; each product has a detail page with a KPI strip (total stock, unit price, total value, min level), stock breakdown per location, and a lazy-loaded paginated movement history
- **Warehouse locations** — define and manage storage zones
- **Stock tracking** — real-time inventory state per product/location pair using decimal precision (bcmath)
- **Warehouse documents** — create and confirm:
  - **PZ** (Receipt) — incoming goods
  - **WZ** (Release) — outgoing goods
  - **MM** (Relocation) — inter-location transfers
  - **INW** (Adjustment) — inventory adjustment (stock increase or decrease per location)
  - **KPZ / KWZ / KMM / KINW** (Correction) — corrective document issued against a confirmed PZ/WZ/MM/INW; reverses stock effects partially or fully while preserving the original document
- **Post-correction effective state** — when confirmed corrections exist, the operation show page and the list accordion display a computed net state ("Pozycje po korektach") alongside an amber warning banner; the banner is suppressed for draft-only corrections
- **Operations list accordion** — clicking any row in the operations list expands an inline detail panel showing document lines and, where applicable, the post-correction net lines; data is loaded lazily via Turbo Frame
- **Stocktaking** — dedicated counting module; manager creates a stocktaking session which snapshots current stock levels, warehouse employees enter counted quantities inline (Turbo Stream, no page reload), and on completion the system generates an INW adjustment document and updates stock accordingly
- **Print view** — printable layout for all warehouse document types
- **Dashboard** — inventory overview (in progress)
- **Auto-numbering** — sequential document numbers per prefix and calendar month (e.g. `PZ/2025/05/0001`, `KPZ/2025/05/0001`); each prefix sequences independently
- **Role-based access** — `ROLE_WAREHOUSE_EMPLOYEE` and `ROLE_WAREHOUSE_MANAGER` (every user also receives `ROLE_USER` automatically)

---

## Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.4 |
| Framework | Symfony 8.0 |
| ORM | Doctrine ORM 3.6 |
| Database | MySQL 8.4 |
| Frontend | Twig |
| UI libraries | Tailwind CSS v4, Flowbite, Hotwired Turbo, Stimulus.js, Tom Select, Chart.js |

---

## Getting Started

**Prerequisites:** Docker with Compose plugin

```bash
git clone <repo-url>
cd project_warehouse_app
docker-compose up
```

The app will be available at `http://localhost:8000`.

On first start the entrypoint script will install Composer dependencies, run Doctrine migrations, clear the Symfony cache, and start Apache.

---

## Demo Data (Fixtures)

The project ships with data fixtures that populate the database with a realistic warehouse scenario for presentation purposes.

```bash
docker-compose exec app bin/console doctrine:fixtures:load --no-interaction
```

**What gets loaded:**

| Type | Count | Details |
|---|---|---|
| Users | 2 | `manager` / `manager123` (ROLE_WAREHOUSE_MANAGER), `pracownik` / `pracownik123` (ROLE_WAREHOUSE_EMPLOYEE) |
| Locations | 30 | Zones A-01…A-10 (raw materials), B-01…B-10 (semi-finished), C-01…C-10 (finished goods) |
| Products | 12 | 3 finished goods, 2 semi-finished, 3 raw materials, 4 consumables |
| Documents | 6 | 3× PZ, 1× MM, 2× WZ — all confirmed, spread over the last 30 days |

The documents are confirmed in a logical order — receipts build up stock, the relocation moves semi-finished goods between zones, and releases reduce finished goods inventory.

> **Warning:** `doctrine:fixtures:load` **purges the entire database** before loading. Do not run on production.

---

## Creating the First User

There is no self-registration and no user management UI in the application. Users can only be created via the Symfony console command:

```bash
docker-compose exec app bin/console app:create-user
```

---

## Project Structure

```
src/
├── Controller/     # HTTP layer (Product, Location, Operation, Stocktaking, Dashboard, API, Security)
├── Entity/         # Doctrine entities (User, Product, Location, Stock, Operation hierarchy, Stocktaking)
├── Form/           # Symfony form types
├── Repository/     # Custom query logic per entity
├── Service/        # Business logic
│   ├── OperationService.php    # Document numbering, draft→confirmed transition
│   ├── CorrectionService.php   # Correction delta computation, effective-state aggregation
│   ├── StocktakingService.php  # Stocktaking lifecycle (snapshot, save line, complete)
│   └── StockService.php        # Inventory math with bcmath precision
├── Twig/           # Custom Twig extensions (pluralize)
├── Enum/           # ProductType, OperationStatus, StocktakingStatus
└── Command/        # CLI commands (create-user)

templates/          # Twig views per feature module
migrations/         # Doctrine migration files
```

---

## Operations Model

Operations use Doctrine JOINED inheritance with a `type` discriminator column. Five types are supported:

- **PZ — Receipt** — incoming goods; confirming increases stock at the target location
- **WZ — Release** — outgoing goods; confirming decreases stock at the source location
- **MM — Relocation** — inter-location transfer; confirming decreases stock at the source and increases it at the destination
- **INW — Adjustment** — manual stock correction per location; a line with only `locationTo` adds stock, a line with only `locationFrom` removes it
- **Correction (KPZ / KWZ / KMM / KINW)** — corrective document issued against a confirmed PZ/WZ/MM/INW; see [Corrections](#corrections) below

All operations start in `DRAFT` status. Once all required data is present the operation can be confirmed, transitioning it to `CONFIRMED` and triggering the stock update via `StockService`.

```
DRAFT → (validate) → CONFIRMED
```

Document numbers are generated automatically on creation, scoped per prefix and calendar month. Each prefix has its own independent sequence:

```
PZ/2025/05/0001
WZ/2025/05/0003
MM/2025/05/0001
INW/2025/05/0001
KPZ/2025/05/0001
```

The sequence resets at the start of each month.

---

## Corrections

A correction (korekta) is a document issued against an already-confirmed PZ, WZ, MM, or INW. The original document is never modified — full audit trail is preserved.

### Base lines and successive corrections

When a new correction is created, the system first computes the **effective lines** — the net state of the original operation after all previously confirmed corrections. This effective state is used as the base for both the correction form pre-fill and the delta computation.

This means successive corrections always work against the current warehouse reality, not the original document:

```
PZ:  Product A, B-01, qty 10
KPZ1 (confirmed): reduces to qty 7   → effective: qty 7
KPZ2 (new):       user enters qty 5  → delta against qty 7 = subtract 2 (not subtract 5)
```

When no confirmed corrections exist the original document lines are used as the base, producing the same result as before.

### Delta computation

`CorrectionService::computeLines()` determines the actual stock-adjustment lines from the desired state entered in the correction form and the base lines:

| Scenario | Result |
|---|---|
| Same product + same location, different quantity | Single delta line in the appropriate direction |
| Changed product or location | Full reversal of the base line + application of the desired line |
| Line removed from the form | Full reversal of the base line |
| No net change detected | Form submission is rejected — no no-op corrections are persisted |

Relocation corrections are handled differently: the form pre-fills locations already reversed (correction direction), so the desired line is used directly rather than going through reversal + application.

### Effective state display

`CorrectionService::computeEffectiveLines()` aggregates the original operation lines and all its **confirmed** corrections into a single signed accumulator, then converts the result back to XOR `OperationLine` objects (exactly one location set, always positive quantity). Lines that cancel out to zero are omitted. For relocations the XOR lines are re-paired into `from → to` entries for display.

The effective state is shown on the operation show page and in the operations list accordion whenever at least one confirmed correction exists. An amber banner indicates that the stock state has changed. Draft corrections do not trigger the banner.

### Constraints

- Corrections can only be created against confirmed PZ / WZ / MM / INW documents
- A correction document itself cannot be edited after creation — delete it and create a new one
- Each correction line must have exactly one location set (source or destination, not both)

---

## Stocktaking

Stocktaking is a standalone module for conducting physical inventory counts.

### Workflow

```
open → in_progress → completed
                   → cancelled
```

1. **Create** — manager opens a new stocktaking session; the system immediately snapshots current stock levels into `StocktakingLine` records (`expectedQuantity`)
2. **Count** — warehouse employees enter counted quantities inline; each row save is a Turbo Stream partial update (no page reload); status transitions from `open` to `in_progress` on the first save
3. **Complete** — manager confirms; the system computes the delta per line, generates an INW adjustment document, confirms it (updating stock), and links it to the stocktaking session
4. **Cancel** — session is closed without generating any adjustment

Lines with no `countedQuantity` are skipped on completion — the count is effectively partial.

---

## API Endpoints

Internal JSON endpoints consumed by form autocomplete (requires `ROLE_USER`):

| Method | Path | Description |
|---|---|---|
| GET | `/api/product/search?query=` | Search products by name/SKU/EAN (returns up to 10 results) |
| GET | `/api/product/get?id=` | Get single product with price |
| GET | `/api/location/search?query=` | Search locations by code/name (returns up to 10 results) |

---

## Database

Schema is managed entirely through Doctrine Migrations — never modify the schema manually. To run migrations:

```bash
docker-compose exec app bin/console doctrine:migrations:migrate
```

Migrations are also executed automatically on container startup via the entrypoint script.

---

## Code Quality

The project follows **Gitflow** — feature branches are merged into `develop`, and `develop` is merged into `main` for releases.

**PHP CS Fixer** and **Twig CS Fixer** are enforced in CI on every pull request targeting `develop` or `main`.

To fix locally (inside the container):

```bash
docker-compose exec app vendor/bin/php-cs-fixer fix
docker-compose exec app vendor/bin/twig-cs-fixer fix
```

---

## Environment Variables

| Variable | Description |
|---|---|
| `DATABASE_URL` | Doctrine DSN |
| `APP_SECRET` | Symfony app secret |
| `APP_ENV` | `dev` / `prod` / `test` |
| `BUILD_ENV` | Controls Docker entrypoint behavior (`dev` enables Tailwind watch) |
