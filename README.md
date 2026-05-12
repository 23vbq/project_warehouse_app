# Felgapol WMS

A warehouse management system for tracking inventory, processing warehouse documents, and monitoring stock levels across storage locations.

> Frontend designed with [Claude Design](https://claude.ai/design).

---

## Features

- **Product catalog** — manage SKU/EAN registry with product types (finished goods, semi-finished, raw materials, consumables) and minimum stock level alerts
- **Warehouse locations** — define and manage storage zones
- **Stock tracking** — real-time inventory state per product/location pair using decimal precision (bcmath)
- **Warehouse documents** — create and confirm:
  - **PZ** (Receipt) — incoming goods
  - **WZ** (Release) — outgoing goods
  - **MM** (Relocation) — inter-location transfers
- **Dashboard** — inventory overview and low-stock alerts
- **Auto-numbering** — sequential document numbers per type/month (e.g. `PZ/2025/05/0001`)
- **Role-based access** — `ROLE_USER`, `ROLE_WAREHOUSE_MANAGER` hierarchy

---

## Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.4 |
| Framework | Symfony 8.0 |
| ORM | Doctrine ORM 3.6 |
| Database | MySQL 8.4 |
| Frontend | Twig + Tailwind CSS v4 + Flowbite |
| SPA-like navigation | Hotwired Turbo + Stimulus.js |
| UI components | Tom Select, Flowbite Datepicker |
| Container | Docker + Docker Compose |
| CI | GitHub Actions (PHP CS Fixer, Twig CS Fixer) |

---

## Requirements

- Docker
- Docker Compose

---

## Getting Started

```bash
git clone <repo-url>
cd project_warehouse_app
docker-compose up
```

The app will be available at `http://localhost:8000`.

On first start the entrypoint script will install Composer dependencies, run Doctrine migrations, clear the Symfony cache, and start Apache.

---

## Creating the First User

There is no self-registration. Use the Symfony console command:

```bash
docker-compose exec app bin/console app:create-user
```

---

## Project Structure

```
src/
├── Controller/     # HTTP layer — 6 controllers (Product, Location, Operation, Dashboard, API, Security)
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

Operations use Doctrine JOINED inheritance — a single `operation` discriminator column determines whether a row is a Receipt, Release, or Relocation. All operations start in `DRAFT` status and transition to `CONFIRMED` once validated, at which point `StockService` applies the inventory delta.

```
DRAFT → (validate) → CONFIRMED
```

Confirming a Receipt increases stock; confirming a Release decreases stock (with availability validation); confirming a Relocation decreases from source and increases at destination.

---

## API Endpoints

Internal JSON endpoints consumed by form autocomplete (requires `ROLE_USER`):

| Method | Path | Description |
|---|---|---|
| GET | `/api/product/search?query=&limit=` | Search products by name/SKU/EAN |
| GET | `/api/product/get?id=` | Get single product with price |
| GET | `/api/location/search?query=&limit=` | Search locations by code/name |

---

## Code Quality

The codebase enforces PSR-12 for PHP and consistent formatting for Twig templates via two linters: **PHP CS Fixer** and **Twig CS Fixer**. Both run automatically in CI on every pull request targeting `develop` or `main`.

To run locally (inside the container):

```bash
docker-compose exec app vendor/bin/php-cs-fixer check
docker-compose exec app vendor/bin/php-cs-fixer fix
docker-compose exec app vendor/bin/twig-cs-fixer check
```

---

## Database

| Variable | Default (Docker) |
|---|---|
| Host | `db` |
| Database | `felgapol` |
| User | `appuser` |
| Password | `apppassword` |
| Root password | `root` |

Schema is managed entirely through Doctrine Migrations — never edit the schema manually.

---

## Environment Variables

| Variable | Description |
|---|---|
| `DATABASE_URL` | Doctrine DSN |
| `APP_SECRET` | Symfony app secret |
| `APP_ENV` | `dev` / `prod` / `test` |
| `BUILD_ENV` | Controls Docker entrypoint behavior (`dev` enables Tailwind watch) |