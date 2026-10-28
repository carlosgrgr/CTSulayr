# AGENT.md

This file provides guidance to IA when working with code in this repository.

## What this repo is

A full WordPress **Multisite** install (subdomain mode) committed as-is, core and third-party plugins included. Almost everything under `wp-admin/`, `wp-includes/` and `wp-content/` is vendor code — do not edit it. The custom code lives in:

| Path | What it is |
|---|---|
| `wp-content/plugins/club-triatlon-sulayr/` | Club site plugin: athletes (`athlete` role), ACF fields, Stripe subscriptions, wallet, WooCommerce glue. Composer/PSR-4 (`ClubTriatlonSulayr\` → `src/`), `vendor/` is committed. |
| `wp-content/plugins/sulayr-reservas/` | Network-activated swimming reservations plugin (the current release work). No Composer, own autoloader (`SulayrReservas\` → `src/`), bootstrapped from `Plugin::init()` on `plugins_loaded`. |
| `wp-content/themes/ct-sulayr-theme/` | Astra child theme for the club site (blog 1). |
| `wp-content/themes/reservations-theme/` | Astra child theme for the reservations site (blog 2). |

Local sites (Apache, docroot `/var/www/html/CTSulayr`):

- Blog 1 — club: `clubtriatlonsulayr.local.com`, table prefix `wp_`
- Blog 2 — reservations (CN Nazarí): `cnnazari.clubtriatlonsulayr.local.com`, table prefix `wp_2_`

`WP_DEBUG` and `WP_DEBUG_LOG` are on; errors go to `wp-content/debug.log`. `wp-config.php` is gitignored.

## Commands

There is no build step and no PHPUnit suite. Verification is done with WP-CLI and scripts that load WordPress.

```bash
cd /var/www/html/CTSulayr
wp site list --fields=blog_id,url                       # confirm both network sites
wp eval 'var_dump( get_current_blog_id() );'            # run code in blog 1
wp --url=cnnazari.clubtriatlonsulayr.local.com eval '…' # run code in blog 2 (reservations)
wp eval-file /path/to/check.php                         # one-off verification script
php -l wp-content/plugins/sulayr-reservas/src/Path/To/File.php   # syntax check
```

- Club plugin dependencies: `composer install` inside `wp-content/plugins/club-triatlon-sulayr/`. Its `phpcs.xml` (WPCS-based) is the lint config, but WPCS is not installed in `vendor/` right now.
- The local CLI runs PHP 8.4, but both plugins target **PHP 7.4**: no union types, `mixed`, constructor promotion, enums, `match`, or nullsafe operator.

## Project documents (read before changing reservations behaviour)

Reservations specs are in Spanish under `docs/reservas/` and use the `-RESERVAS` suffix. Club-site specs are under `docs/club/` and are listed apart in `docs/FASES.md`. They are outside the precedence below. When reservations specs disagree, this order wins:

1. `docs/reservas/SPEC-MEJORAS-RESERVAS.md` (its section 16 wins over earlier paragraphs in the same file) and `docs/reservas/SPEC-FASE11-RESERVAS.md` override `docs/reservas/SPEC-RESERVAS.md` where they change behaviour.
2. `docs/reservas/SPEC-RESERVAS.md` — functional requirements and approved decisions.
3. `docs/reservas/ANALISIS.md` — why the implementation differs from names or mechanisms in the original spec.
4. `wp-content/plugins/sulayr-reservas/DEVELOPMENT.md` — technical reference: class responsibilities, capability map, locking order, known incidents, pending tests, and per-phase notes. It never overrides the spec.

`docs/FASES.md` is the index. Phases 0–11 are the closed `sulayr-reservas` delivery and are already in `main`; that numbering does not continue. Later work is a named spec tied to one plugin (`sulayr-reservas` or `club-triatlon-sulayr`). A spec’s own document holds its status and, when it needs them, its internal steps. Club specs do not override `docs/reservas/SPEC-RESERVAS.md`. There is no fixed working branch.

`docs/PRIORIDADES.md` is the ordered list of work that still needs a definition. It is not a spec. Do not implement a row from it, and do not treat a nearby draft as that row’s definition, until the row says which document defines it.

`docs/reservas/ACEPTACION.md` is the acceptance matrix for SPEC §18. A row there counts as "Cumplido y probado" only once the check has been run. The `MANUAL-*.md` and `GUIA-RAPIDA-GESTOR.*` files in the plugin are end-user manuals.

Do not change requirements or approved decisions in these docs on your own. Update `DEVELOPMENT.md`, `docs/FASES.md` and `docs/reservas/ACEPTACION.md` when work changes what they describe.

## Reservations plugin architecture

How the data and the two sites fit together:

- **Two sites, split data.** The club (blog 1) owns athletes, ACF data and the `athlete` role. Reservations (blog 2) owns sessions, bookings, generated months and a *local* swimmer record (`wp_2_sulayr_swim_swimmers`). Only the sync log (`wp_sulayr_swim_sync_log`) lives in blog 1. Club user meta is never written from this plugin.
- **Site switching.** All `switch_to_blog()` calls go through `Context\BlogContext::onClub()` / `onBookings()`, which restore the context in a `finally`. Blog IDs come from network options (`sulayr_reservas_club_blog_id`, `sulayr_reservas_bookings_blog_id`) via `Config\SiteConfig`. Never hard-code blog IDs or table prefixes; use `$wpdb->prefix` after switching.
- **Roles.** `nadador` and `gestor` exist only in blog 2. Creating roles or capabilities outside `onBookings()` would leak them into the club. Permission checks use the `sulayr_*` capabilities listed in `DEVELOPMENT.md`, not role names.
- **Booking concurrency.** `BookingService` wraps each operation in a transaction. It always locks the swimmer row (`FOR UPDATE`) before the session row, then recounts seats and the weekly limit. The unique key `(session_id, user_id)` is the backstop against duplicates. Keep that lock order.
- **Sessions.** `SessionGenerator` only inserts missing sessions and never updates existing ones. Session times are stored as wall-clock time (`15:00:00`) in `Europe/Madrid`. All date logic uses `wp_timezone()` / `DateTimeImmutable`, never the server timezone.
- **Front end.** A single shortcode, `[sulayr_reservas]`, takes no attributes. `PanelPage` finds the page by the shortcode it contains, not by its slug. `PanelActions` checks nonce + capability, then calls the service. Front-end forms must not rely on `admin-post.php`, because `AdminAccess` redirects `nadador` / `gestor` out of `wp-admin` in blog 2. `admin-ajax.php` is exempt from that redirect.
- **Sync from the club.** `Sync\ClubSync` hooks `profile_update` at priority 30. It reads club data and writes only the local swimmer table.
- **Schema.** Tables are created with `dbDelta`. The version is stored in the per-site option `sulayr_reservas_db_version`. Schema changes bump that version and must preserve existing data.

## Conventions

- **Reservations plugin:**
  - Prefix capabilities, options, tables and internal hooks with `sulayr_`.
  - Build dynamic SQL with `$wpdb->prepare()`.
  - Admin and UI text is in Spanish.
  - Code style: tabs, `array()` syntax.
- **Club plugin:**
  - Spaces for indentation, short `[]` arrays (enforced by `phpcs.xml`), Yoda conditions.
  - Its constants use the `SULAYR_` prefix, so don't confuse them with `SULAYR_RESERVAS_*`.
- **Do not edit `club-triatlon-sulayr` from reservations work.** Integration is hooked in from `sulayr-reservas`. Club specs (documentos, cobros de cuotas) are implemented in the club plugin.
- **Known WooCommerce issue.** Removing a user from blog 2 triggers a WooCommerce hook that fails on the missing table `wp_2_wc_customer_lookup`. `SwimmerService` suppresses that error. Do not create the table to silence it.
- **Commits.** Don't commit unless asked. There is no fixed working branch.
