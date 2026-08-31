# Abaya Factory ERP

Laravel-based ERP for managing manufacturing, inventory, procurement, sales, accounting, workers, and operational reporting for an abaya production workflow.

The application is structured around connected business processes rather than isolated CRUD screens. Production, stock movements, purchasing, sales, accounting, and permissions are represented as related workflows inside one system.

> This repository is private. The documentation below describes only functionality visible in the codebase and does not expose production credentials, customer data, or internal deployment details.

## Core Modules

### Manufacturing

- Production orders with confirm, hold, resume, cancel, complete, and output workflows
- Bills of materials (BOMs)
- Manufacturing operations
- Worker assignments
- Material issue, usage, return, and waste tracking
- Production defects and rework workflows
- Material variance reporting

### Inventory & Products

- Products and product variants
- Materials and material inventory
- Product and material cost layers
- Barcode lookup and printable production/product labels
- Inventory valuation and operational inventory reporting

### Procurement

- Supplier management and supplier ledgers
- Purchase orders and approval workflow
- Goods receipts
- Supplier invoices
- Supplier payments

### Sales & Customers

- Customer management and customer ledgers
- Sales invoices
- Sales returns
- Payment receipts

### Accounting

- Chart of accounts
- Journal entries and reversal workflow
- General ledger
- Trial balance
- Profit and loss reporting
- Balance sheet
- Cash/bank balances
- Customer and supplier balances
- Inventory valuation
- Cost of goods sold reporting
- Fiscal periods and year-closing workflows

### Access Control

- Authentication and active-user checks
- Role and permission management
- Route-level permission enforcement using Spatie Laravel Permission

### Reporting

The application exposes reporting areas for sales, customers, collections, purchases, inventory, materials, manufacturing, workers, defects, returns, and accounting.

## Tech Stack

- PHP 8.2+
- Laravel 12
- Eloquent ORM
- Spatie Laravel Permission
- Laravel Breeze
- Pest
- Laravel Telescope (development)
- Vite

## Architecture Notes

The application is organized around Laravel controllers, Eloquent models, services, authorization middleware, and domain-specific workflows. Accounting reporting logic includes dedicated service classes, while route-level permissions protect sensitive operations.

Several operations are modeled as state transitions rather than direct record editing. Examples include production order lifecycle actions, purchase approvals, fiscal-period closing, defect approval/rejection, and rework processing.

## Local Setup

```bash
git clone <repository-url>
cd abaya-factory-erp
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

Configure the database and local environment values in `.env` before running migrations.

## Tests

```bash
php artisan test
```

## Portfolio Notes

This project is most useful for demonstrating backend work involving interconnected business rules, authorization, relational data, manufacturing workflows, accounting concepts, inventory movement, and ERP-style domain modeling.

Because the repository is private, any future public portfolio version should be sanitized before publication and should not include real business data, credentials, or confidential implementation details.
