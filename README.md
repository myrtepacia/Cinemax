<div align="center">
  <img src="assets/img/logo.png" alt="Cinemax Logo" width="220" />
  <h1>Cinemax</h1>
  <p><strong>Automated Cinema Ticketing, Real-Time Seat Reservation & Concession Fulfillment System</strong></p>
</div>

---

Cinemax is an end-to-end web platform engineered for movie theaters to manage film screenings, online seat reservations, concession sales, and admission operations. Powered by PHP, MySQL, Vanilla JavaScript, and PayMongo, the system coordinates customer booking flows with gatekeeper QR verification and live concession tracking.

---

## System Capabilities

### Customer Portal
* **Interactive Seat Selection:** Dynamic, visual seat picker that polls availability in real time to prevent double bookings.
* **Concession Ordering:** Add snacks and beverages directly to your movie ticket order during checkout.
* **Automated E-Ticket Delivery:** Generates digital pass cards and e-tickets featuring scannable QR code payloads.
* **Self-Service Accounts:** Registration, profile preferences, and searchable past booking history.

### Administration & Gate Verification
* **In-Browser Ticket Scanner:** Camera-driven QR code verification module powered by `jsQR.js` for entrance staff.
* **Concession Claim Scanner:** Dedicated QR scanner for concession stands to mark food vouchers as redeemed.
* **Live Claim Monitor:** Real-time visual display queue monitoring pending and fulfilled snack orders.
* **Showtime & Theater Management:** Tools to add movies, set screening schedules, and upload theatrical posters.
* **Performance Dashboard:** Visual metrics tracking daily admissions, revenue, and order volume.

### Payment Processing & Infrastructure Security
* **PayMongo Gateway:** Direct integration supporting Credit/Debit Cards, GCash, and Maya.
* **Asynchronous Webhook Engine:** Background webhook listeners that verify transaction payloads and update database records automatically.
* **Defensive Architecture:** Server-side input sanitization, PDO prepared statements, CSRF tokens, and directory-level `.htaccess` protection rules.

---

## Tech Stack

| Layer | Technology | Details |
|---|---|---|
| **Backend** | PHP 7.4+ / 8.x | Modular service architecture |
| **Database** | MySQL / MariaDB via `PDO` | Relational data schema |
| **Frontend** | HTML5, CSS3, JavaScript | Custom responsive UI |
| **Payment Gateway** | PayMongo REST API & Webhooks | Automated checkout processing |
| **QR Code Engine** | `qrcode.js` & `jsQR.js` | Dynamic ticket rendering and camera decoding |
| **Web Server** | Apache (`mod_rewrite`)[cite: 1] | URL rewriting and directory access control[cite: 1] |

---

## Repository Structure

```plaintext
cinemax/
├── .htaccess                     # Global URL rewrites and server hardening[cite: 1]
├── index.php                     # Customer homepage and movie showcase[cite: 1]
├── book.php                      # Showtime and seat booking interface[cite: 1]
├── checkout.php                  # Order overview and snack selection[cite: 1]
├── pay.php                       # Payment gateway dispatcher[cite: 1]
├── pay-now.php                   # Direct checkout routing[cite: 1]
├── pay-other.php                 # Secondary payment processor[cite: 1]
├── payment-success.php           # Post-transaction confirmation view[cite: 1]
├── payment-cancel.php            # Cancelled transaction fallback[cite: 1]
├── ticket.php                    # Scannable digital e-ticket display[cite: 1]
├── card.php                      # Digital pass card component[cite: 1]
├── account.php                   # User profile and order history[cite: 1]
├── signin.php                    # Customer login page[cite: 1]
├── signup.php                    # User registration portal[cite: 1]
├── signout.php                   # Session termination[cite: 1]
├── terms.php                     # Terms of service and guidelines[cite: 1]
├── webhook.php                   # PayMongo asynchronous webhook listener[cite: 1]
│
├── admin/                        # Operations & Staff Control Center[cite: 1]
│   ├── index.php                 # Analytics and operations dashboard[cite: 1]
│   ├── movies.php                # Movie catalog inventory[cite: 1]
│   ├── add-movie.php             # New screening and movie creation[cite: 1]
│   ├── scanner.php               # Camera-based admission QR ticket scanner[cite: 1]
│   ├── snack-scanner.php         # Concession booth voucher scanner[cite: 1]
│   └── claim-monitor.php         # Live queue display for food fulfillment[cite: 1]
│
├── api/                          # Internal Asynchronous Endpoints[cite: 1]
│   ├── dashboard.php             # Administrative metrics data feed[cite: 1]
│   ├── seats.php                 # Live seat map and availability state[cite: 1]
│   ├── scan.php                  # Gate ticket scan validation endpoint[cite: 1]
│   ├── snack-scan.php            # Concession QR redemption endpoint[cite: 1]
│   ├── snack-search.php          # Concession menu item search[cite: 1]
│   ├── claim-monitor.php         # Real-time concession polling feed[cite: 1]
│   └── payment-status.php        # Transaction verification polling[cite: 1]
│
├── config/                       # System Configuration Files[cite: 1]
│   ├── .htaccess                 # Direct web access shield[cite: 1]
│   ├── config.example.php        # Configuration template[cite: 1]
│   └── config.php                # Database credentials & PayMongo API secrets[cite: 1]
│
├── database/                     # Database Migrations[cite: 1]
│   ├── .htaccess                 # SQL direct download protection[cite: 1]
│   └── cinemax.sql               # Database schema and seed dataset[cite: 1]
│
├── includes/                     # Reusable Core Services & Helpers[cite: 1]
│   ├── .htaccess                 # PHP source file isolation[cite: 1]
│   ├── auth.php                  # Session controls and authentication checks[cite: 1]
│   ├── bookings.php              # Reservation logic and transaction models[cite: 1]
│   ├── bootstrap.php             # Global autoloader and runtime initializers[cite: 1]
│   ├── db.php                    # PDO database connection factory[cite: 1]
│   ├── helpers.php               # Formatting, sanitization, and output helpers[cite: 1]
│   ├── layout.php                # Template wrappers (header, navbar, footer)[cite: 1]
│   ├── movies.php                # Movie catalog query controllers[cite: 1]
│   ├── paymongo.php              # PayMongo API integration wrapper[cite: 1]
│   └── security.php              # CSRF, XSS, and authorization guards[cite: 1]
│
├── storage/                      # Runtime Logs & Transient Cache[cite: 1]
│   ├── .htaccess                 # Private logs directory shield[cite: 1]
│   ├── cache/                    # Application cache storage[cite: 1]
│   └── logs/                     # System logs and webhook audit trail[cite: 1]
│
└── assets/                       # Static Assets & Front-End Dependencies[cite: 1]
    ├── css/                      # Application stylesheets[cite: 1]
    ├── js/                       # Front-end business logic and AJAX handlers[cite: 1]
    ├── img/                      # Logos, UI badges, and movie posters[cite: 1]
    └── vendor/                   # Embedded libraries (`jsQR.js`, `qrcode.js`)[cite: 1]
