# Cinemax — Cinema Ticket & Concessions Booking System

Cinemax is a web-based cinema ticketing and concessions management application built with PHP, MySQL, JavaScript, and PayMongo. The platform handles the entire cinema workflow—from interactive seat selection and online payment processing to QR-coded e-ticket generation, entrance gate verification, and live concession claim monitoring.

---

## Table of Contents

- [Features](#features)
  - [Customer Features](#customer-features)
  - [Administrative & Staff Portals](#administrative--staff-portals)
  - [Payment & Security](#payment--security)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Database Setup](#database-setup)
- [Installation & Configuration](#installation--configuration)
- [API Endpoints](#api-endpoints)
- [Verification & Scanner Workflow](#verification--scanner-workflow)
- [License](#license)

---

## Features

### Customer Features
- **Movie Catalog & Schedules:** Browse currently showing movies, upcoming features, and scheduled screening times.
- **Live Seat Reservation:** Choose available seats interactively via a seat map interface backed by real-time seat availability polling.
- **Concession Stand Add-Ons:** Select food and beverage items alongside movie tickets during checkout.
- **Digital QR Tickets:** View and download e-tickets featuring dynamic QR codes generated via `qrcode.js`.
- **User Accounts:** Register, sign in, update user credentials, and review past order history.

### Administrative & Staff Portals
- **Analytics Dashboard:** Monitor total ticket sales, revenue metrics, and admission volume.
- **Movie Management:** Add, edit, archive, and schedule screenings and posters.
- **Gate QR Scanner:** In-browser camera scanning powered by `jsQR.js` to validate entrance tickets at cinema gates.
- **Concession Scanner:** Scan customer snack QR codes to verify and log item redemptions at food counters.
- **Live Claim Monitor:** Real-time dashboard to monitor concession claims and orders.

### Payment & Security
- **PayMongo Gateway:** Processes credit/debit card and digital wallet transactions (GCash, Maya) via the PayMongo API.
- **Webhook Handlers:** Asynchronous transaction status processing via secure webhook listeners.
- **Secure Access Control:** Password hashing, prepared statements using PDO, CSRF protection, and directory shielding via `.htaccess`.

---

## Tech Stack

- **Backend:** PHP (Modular architecture)
- **Database:** MySQL / MariaDB via PDO
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Payment Processing:** PayMongo REST API & Webhooks[cite: 1]
- **QR Utilities:** `qrcode.js` (Generation) & `jsQR.js` (Camera-based parsing)[cite: 1]
- **Web Server:** Apache with `mod_rewrite` enabled[cite: 1]

---

## Project Structure

```plaintext
cinemax/
├── .htaccess                     # Global URL rewrites and root security[cite: 1]
├── index.php                     # Homepage and featured movies listing[cite: 1]
├── book.php                      # Showtime and seat booking page[cite: 1]
├── checkout.php                  # Order overview and snack selections[cite: 1]
├── pay.php                       # Payment processor router[cite: 1]
├── pay-now.php                   # Direct payment dispatcher[cite: 1]
├── pay-other.php                 # Secondary payment methods[cite: 1]
├── payment-success.php           # Post-payment confirmation page[cite: 1]
├── payment-cancel.php            # Cancelled transaction fallback[cite: 1]
├── ticket.php                    # E-ticket viewer with QR code[cite: 1]
├── card.php                      # Digital ticket pass card view[cite: 1]
├── account.php                   # User profile and order history[cite: 1]
├── signin.php                    # User authentication login[cite: 1]
├── signup.php                    # New user registration[cite: 1]
├── signout.php                   # User session logout[cite: 1]
├── terms.php                     # Service terms and policies[cite: 1]
├── webhook.php                   # PayMongo asynchronous webhook listener[cite: 1]
│
├── admin/                        # Administrative and staff modules[cite: 1]
│   ├── index.php                 # Analytics and operations dashboard[cite: 1]
│   ├── movies.php                # Movie list and status controller[cite: 1]
│   ├── add-movie.php             # New movie addition form[cite: 1]
│   ├── scanner.php               # Camera-based gate QR ticket scanner[cite: 1]
│   ├── snack-scanner.php         # Concession counter QR scanner[cite: 1]
│   └── claim-monitor.php         # Live snack order queue monitor[cite: 1]
│
├── api/                          # Asynchronous JSON API endpoints[cite: 1]
│   ├── dashboard.php             # Analytics metrics endpoint[cite: 1]
│   ├── seats.php                 # Showtime seat availability handler[cite: 1]
│   ├── scan.php                  # Gate ticket scan validation endpoint[cite: 1]
│   ├── snack-scan.php            # Concession QR validation endpoint[cite: 1]
│   ├── snack-search.php          # Snack catalog search endpoint[cite: 1]
│   ├── claim-monitor.php         # Concession claim polling endpoint[cite: 1]
│   └── payment-status.php        # Transaction verification endpoint[cite: 1]
│
├── config/                       # Configuration files[cite: 1]
│   ├── .htaccess                 # Protects configuration files[cite: 1]
│   ├── config.example.php        # Template configuration file[cite: 1]
│   └── config.php                # Environment secrets and credentials[cite: 1]
│
├── database/                     # Database schemas and migrations[cite: 1]
│   ├── .htaccess                 # Prevents direct database directory access[cite: 1]
│   └── cinemax.sql               # Full SQL database dump and sample data[cite: 1]
│
├── includes/                     # Backend helper libraries and core logic[cite: 1]
│   ├── .htaccess                 # Direct script execution prevention[cite: 1]
│   ├── auth.php                  # Authentication and session guards[cite: 1]
│   ├── bookings.php              # Ticket reservation queries and models[cite: 1]
│   ├── bootstrap.php             # Global autoloader and initialization[cite: 1]
│   ├── db.php                    # PDO database connection handler[cite: 1]
│   ├── helpers.php               # Response formatting and helper utilities[cite: 1]
│   ├── layout.php                # Global page header and footer templates[cite: 1]
│   ├── movies.php                # Movie retrieval and scheduling logic[cite: 1]
│   ├── paymongo.php              # PayMongo API client wrapper[cite: 1]
│   └── security.php              # CSRF, XSS, and sanitization guards[cite: 1]
│
├── storage/                      # Runtime cache and log files[cite: 1]
│   ├── .htaccess                 # Direct file access shield[cite: 1]
│   ├── cache/                    # Temporary application cache files[cite: 1]
│   └── logs/                     # Application error and payment webhook logs[cite: 1]
│
└── assets/                       # Static front-end assets[cite: 1]
    ├── css/                      # Stylesheets for client and admin views[cite: 1]
    ├── js/                       # Client-side scripts and event handlers[cite: 1]
    ├── img/                      # App logos, payment icons, and movie posters[cite: 1]
    └── vendor/                   # Third-party libraries (`jsQR.js`, `qrcode.js`)[cite: 1]
