# AGENTS.md

## Project
WordPress plugin: Reserva de Aseo Elementor.

## Context
This plugin allows clients to reserve domestic cleaning staff from an Elementor widget.

## Stack
- WordPress
- PHP
- Elementor widget API
- MySQL using $wpdb
- JavaScript fetch/AJAX

## Important files
- reserva-aseo-elementor.php
- includes/db.php
- includes/ajax.php
- includes/personal-cpt.php
- includes/elementor-widget.php
- includes/widget-reserva-aseo.php
- includes/admin-reservas.php
- assets/js/app.js
- assets/css/style.css

## Rules
- Do not modify WordPress core files.
- Do not hardcode domains or absolute paths.
- Use WordPress sanitization and escaping functions.
- Use $wpdb->prepare for SQL queries.
- Keep compatibility with Elementor Free and Pro.
- Keep compatibility with Apache and Nginx.
- The plugin must work in LocalWP and cPanel hosting.

## Current features
- Custom Post Type for cleaning staff.
- Admin can upload staff photos using featured images.
- Elementor widget displays staff cards.
- Client can choose staff, date, and jornada.
- Reservations are saved in custom table.
- Admin can filter reservations.
- Admin can confirm or cancel reservations.

## Next tasks
- Improve availability validation:
  - If full day is reserved, morning and afternoon must be blocked.
  - If morning or afternoon is reserved, full day must be blocked.
- Add email notification when admin confirms or cancels a reservation.
- Add address and phone fields to the reservation form.