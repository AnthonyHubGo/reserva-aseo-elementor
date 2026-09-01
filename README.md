# Reserva Aseo Elementor

WordPress/Elementor plugin for booking domestic cleaning staff, with a full payment integration through [Wompi](https://wompi.co) (a Colombian payment gateway). Built for **SAT Soluciones a Tiempo**, a real client, as part of ongoing freelance work.

## What it does

- Custom Elementor widget where clients pick a cleaning staff member, a date, and a jornada (morning / afternoon / full day)
- Availability validation across jornadas (a full-day booking blocks morning and afternoon, and vice versa)
- A 3-step booking flow with real-time staff availability
- Full Wompi checkout: payment creation, retries on failed attempts, resuming pending payments, and transaction status verification
- Per-jornada pricing configuration
- Admin panel: reservation table, payment status, staff photo management (Custom Post Type), cancellation reasons, confirm/cancel actions, configurable email notifications

## My contribution

This project is a collaboration between two developers. The base plugin (booking form, staff availability calendar, admin panel) was built by a project partner; **my specific contribution, visible in this branch's commit history, was the complete Wompi payment integration and a redesign of the booking flow**:

- Designed and implemented the Wompi payment integration end to end: checkout, payment retries, resuming pending payments, transaction status checks, and webhook-based event handling
- Configured per-jornada pricing for the payment flow
- Added payment status visibility to the admin reservation table
- Redesigned the booking form into a clearer 3-step flow
- Added configurable email notifications and phone/address fields to the reservation record

The full commit history (unmodified) is preserved in this repository, so authorship per change is verifiable directly from `git log`, not just from this description.

## Stack

- PHP (WordPress plugin architecture, Elementor widget API)
- Wompi REST API (payments)
- MySQL via `$wpdb` (WordPress) and `mysqli` (standalone migration/snapshot scripts)
- JavaScript (fetch/AJAX for the booking widget)
- Custom lightweight test harness (`tests/`) for payment-mode credential validation and DB migration checks

## Security note

No credentials are stored in this repository. Wompi API keys and database credentials are read from WordPress options (configured through the admin panel) or from an external `wp-config.php` path passed at runtime to the maintenance scripts — never hardcoded in source.

## Context

This is real client work, shared publicly with the client relationship's and my project partner's agreement, as a portfolio sample of backend/payments integration work. It is not an open-source project accepting external contributions.
