# Felgapol WMS

A warehouse management system for tracking inventory, processing warehouse documents, and monitoring stock levels across storage locations.

> Frontend designed with [Claude Design](https://claude.ai/design).

<img width="1866" height="936" alt="image" src="https://github.com/user-attachments/assets/8ea9e452-6ede-46c4-855c-006f09ecd4c0" />


---

## Features

- **Product catalog** — manage SKU/EAN registry with product types (finished goods, semi-finished, raw materials, consumables) and minimum stock level alerts
- **Warehouse locations** — define and manage storage zones
- **Stock tracking** — real-time inventory state per product/location pair using decimal precision (bcmath)
- **Warehouse documents** — create and confirm:
  - **PZ** (Receipt) — incoming goods
  - **WZ** (Release) — outgoing goods
  - **MM** (Relocation) — inter-location transfers
- **Print view** — printable layout for warehouse documents
- **Dashboard** — inventory overview (in progress)
- **Auto-numbering** — sequential document numbers per type/month (e.g. `PZ/2025/05/0001`)
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

## Creating the First User

There is no self-registration and no user management UI in the application. Users can only be created via the Symfony console command:

```bash
docker-compose exec app bin/console app:create-user
```

---

## Project Structure

```
src/
├── Controller/     # HTTP layer (Product, Location, Operation, Dashboard, API, Security, Default)
├── Entity/         # Doctrine entities (User, Product, Location, Stock, Operation hierarchy)
├── Form/           # Symfony form types
├── Repository/     # Custom query logic per entity
├── Service/        # Business logic
│   ├── OperationService.php   # Document numbering, draft→confirmed transition
│   └── StockService.php       # Inventory math with bcmath precision
├── Enum/           # ProductType, OperationStatus
└── Command/        # CLI commands (create-user)

templates/          # Twig views per feature module
migrations/         # Doctrine migration files
```

---

## Operations Model

Operations use Doctrine JOINED inheritance with a `type` discriminator column. Three types are supported:

- **PZ — Receipt** — incoming goods; confirming increases stock at the target location
- **WZ — Release** — outgoing goods; confirming decreases stock (availability is validated before confirmation)
- **MM — Relocation** — inter-location transfer; confirming decreases stock at the source and increases it at the destination

All operations start in `DRAFT` status. Once all required data is present the operation can be confirmed, transitioning it to `CONFIRMED` and triggering the stock update via `StockService`.

```
DRAFT → (validate) → CONFIRMED
```

Document numbers are generated automatically on creation, scoped per type and calendar month, and are unique within the system:

```
PZ/2025/05/0001
WZ/2025/05/0003
MM/2025/05/0001
```

The sequence resets at the start of each month.

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
