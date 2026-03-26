# Siemaszko Investments (Fixed)

A secure and maintainable WordPress plugin for managing real estate investments, including flats, buildings, floors, and commercial units. The plugin provides CRUD operations, search functionality, REST API integration, and PDF generation for property data.

---

## 📌 Table of Contents

- [Introduction](#introduction)
- [Features](#features)
- [Installation](#installation)
- [Usage](#usage)
- [Shortcodes](#shortcodes)
- [REST API](#rest-api)
- [Admin Panel](#admin-panel)
- [PDF Generation](#pdf-generation)
- [Search Functionality](#search-functionality)
- [Project Structure](#project-structure)
- [Dependencies](#dependencies)
- [Configuration](#configuration)

---

## 📖 Introduction

**Siemaszko Investments (Fixed)** is a custom WordPress plugin designed to manage real estate investment data. It allows administrators to create and manage investments, buildings, floors, flats, and related entities through a structured backend and exposes selected data via shortcodes and REST API endpoints.

---

## ✨ Features

- Full CRUD system for:
  - Investments
  - Buildings (objects)
  - Floors
  - Flats
  - Room types
  - Standards
- Admin panel with custom tables
- Frontend display via shortcodes
- AJAX-powered search functionality
- REST API endpoints for integration
- PDF generation for flat details
- Clean and modular architecture
- Secure and maintainable codebase

---

## ⚙️ Installation

1. Download or clone the repository.
2. Upload the plugin folder to: /wp-content/plugins/
3. Activate the plugin via the WordPress admin panel.
4. On activation, required database tables will be created automatically.

---

## 🚀 Usage

After activation:

- Use the admin panel to add investments and related data.
- Embed frontend views using shortcodes.
- Use REST API endpoints for external integrations.
- Generate PDFs for flats via dedicated endpoints.

---

## 🔗 Shortcodes

The plugin registers multiple shortcodes (via `SM_INV_Fixed_Shortcodes` and related classes).

Examples may include:

```php
[siemaszko_investments]
[siemaszko_search]
[siemaszko_commercial_locals]
```

Exact shortcode attributes depend on implementation inside class-shortcodes.php.

## 🌐 REST API

Custom REST API routes are registered via:
```
SM_INV_Fixed_REST::register_routes()
```

These endpoints allow:
- Fetching investment data
- Querying flats and objects
- Integration with external systems



## 🛠️ Admin Panel

The plugin adds a custom admin interface with structured tables:
- Investments
- Buildings (Objects)
- Floors
- Flats
- Room Types
- Standards

Each table is handled by a dedicated class extending a base table class.


## 📄 PDF Generation

Originally the idea was to generate PDF file using tcpdf or dompdf. But we had problems with implementing those libraries to wordpress since it generally need composer. Environment that I had couldn't have that so instead I just wrote small JS inside template. 

Original endpoint:
```
/wp-admin/admin-post.php?action=sm_inv_flat_pdf&flat_id={ID}
```

Handled by:
```
SM_INV_Fixed_PDF::render_flat_pdf()
```


## 🔍 Search Functionality

Includes advanced flat search:
- AJAX-based filtering
- Frontend shortcode integration
- Dynamic queries

Main classes:
- class-search.php
- class-search-ajax.php


## 📁 Project Structure

```
siemaszko-investments-fixed/
│
├── siemaszko-investments-fixed.php
│
├── assets/
│   ├── css/
│   │   ├── admin.css            # Admin panel styles
│   │   └── front.css            # Frontend styles
│   │
│   ├── js/
│   │   ├── search.js            # AJAX search logic
│   │   └── admin.js             # Admin interactions (if present)
│   │
│   └── img/ (or images/)
│       └── ...                  # Icons / UI assets
│
├── includes/
│   ├── class-plugin.php                   # Plugin bootstrap & initialization
│   ├── class-db.php                       # Database schema & queries
│   ├── class-utils.php                    # Utility/helper functions
│   ├── class-poi.php                      # Points of interest logic
│   ├── class-shortcodes.php               # Main shortcode system
│   ├── class-commercial-locals-shortcode.php
│   ├── class-search.php                   # Search logic
│   ├── class-search-ajax.php              # AJAX handlers
│   ├── class-pdf.php                      # PDF generation handler - Not in use
│
│   ├── admin/
│   │   ├── class-admin.php            # Admin panel setup - main functions (CRUD) for investments etc. 
│   │   │
│   │   └── tables/
│   │       ├── class-base-table.php       # Base table abstraction
│   │       ├── class-investments-table.php
│   │       ├── class-objects-table.php
│   │       ├── class-floors-table.php
│   │       ├── class-flats-table.php
│   │       ├── class-roomtypes-table.php
│   │       └── class-standards-table.php
│
│   └── rest/
│       ├── class-rest.php                 # REST API routes
│       └── OFFclass-shortcodes.php        # Test file - OFF means turned off...
```

## 📦 Dependencies
- WordPress (tested with modern versions 6.x.x+)
- PHP 7.4+ recommended
- No external Composer dependencies.

## ⚙️ Configuration
Key constants defined in the main plugin file:
```
define('SM_INV_FIXED_VERSION', '1.0.0');
define('SM_INV_FIXED_PATH', plugin_dir_path(__FILE__));
define('SM_INV_FIXED_URL', plugin_dir_url(__FILE__));
```

