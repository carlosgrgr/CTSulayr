# AGENT.md

This file provides guidance to IA when working with code in this repository.

## What this repo is

A full WordPress **Multisite** install (subdomain mode) committed as-is, core and third-party plugins included. Almost everything under `wp-admin/`, `wp-includes/` and `wp-content/` is vendor code — do not edit it. The custom code lives in:

| Path | What it is |
|---|---|
| `wp-content/plugins/club-triatlon-sulayr/` | Club site plugin: athletes (`athlete` role), ACF fields, members module (`src/Modules/Members/`: service catalog, member history, change log, manager roles), wallet, WooCommerce glue. Stripe was removed (members spec, part A). Composer/PSR-4 (`ClubTriatlonSulayr\` → `src/`), `vendor/` is committed. |
| `wp-content/plugins/sulayr-reservas/` | Network-activated swimming reservations plugin (the current release work). No Composer, own autoloader (`SulayrReservas\` → `src/`), bootstrapped from `Plugin::init()` on `plugins_loaded`. |
| `wp-content/plugins/sulayr-scripts-manager/` | Single-file plugin, network-activated: per-site tracking codes (GTM/Analytics) in Settings → Scripts Manager, printed right after `<head>`, right after `<body>` (Astra's `astra_head_top` / `astra_body_top`, falling back to `wp_head` / `wp_body_open`) or in `wp_footer`. Only users with `unfiltered_html` (super admins in multisite) can edit them. |
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
- The local CLI runs PHP 8.5, but both plugins target **PHP 7.4**: no union types, `mixed`, constructor promotion, enums, `match`, or nullsafe operator.

## Project documents (read before changing reservations behaviour)

Reservations specs are in Spanish under `docs/reservas/` and use the `-RESERVAS` suffix. Club-site specs are under `docs/club/` and are listed apart in `docs/FASES.md`. They are outside the precedence below. When reservations specs disagree, this order wins:

1. `docs/reservas/SPEC-MEJORAS-RESERVAS.md` (its section 16 wins over earlier paragraphs in the same file) and `docs/reservas/SPEC-FASE11-RESERVAS.md` override `docs/reservas/SPEC-RESERVAS.md` where they change behaviour.
2. `docs/reservas/SPEC-RESERVAS.md` — functional requirements and approved decisions.
3. `docs/reservas/ANALISIS.md` — why the implementation differs from names or mechanisms in the original spec.
4. `wp-content/plugins/sulayr-reservas/DEVELOPMENT.md` — technical reference: class responsibilities, capability map, locking order, known incidents, pending tests, and per-phase notes. It never overrides the spec.

`docs/FASES.md` is the index. Phases 0–11 are the closed `sulayr-reservas` delivery and are already in `main`; that numbering does not continue. Later work is a named spec tied to one plugin (`sulayr-reservas` or `club-triatlon-sulayr`). A spec’s own document holds its status and, when it needs them, its internal steps. Club specs do not override `docs/reservas/SPEC-RESERVAS.md`. There is no fixed working branch.

`docs/PRIORIDADES.md` is the ordered list of work that still needs a definition. It is not a spec. Do not implement a row from it, and do not treat a nearby draft as that row’s definition, until the row says which document defines it.

`docs/reservas/ACEPTACION.md` is the acceptance matrix for SPEC §18. A row there counts as "Cumplido y probado" only once the check has been run. The `MANUAL-*.md` and `GUIA-RAPIDA-GESTOR.*` files in the plugin are end-user manuals.

Do not change requirements or approved decisions in these docs on your own. Status, dates and descriptions of what exists are a different matter: keep them current (next section).

## Keeping the docs in sync

Every development, merge or deployment ends with a pass over **all** the project `.md` files, not only the one closest to the change. The same fact (state of a spec, branch, whether it is in `main` or in PROD, a pending step) is often repeated in several documents; a change is not finished while any of them still says the old thing.

Before closing the work, or committing it:

1. Search every project `.md` for the names involved: the spec, the branch, the feature, `PROD`, `main`, `despleg`, `pendiente`. For example: `grep -rn -i "facturación\|despleg\|PROD" docs AGENT.md wp-content/plugins/sulayr-reservas/*.md wp-content/plugins/club-triatlon-sulayr/*.md`.
2. Update each hit that is now out of date. Check at least:
   - `docs/FASES.md`: the row of the spec (state, branch, `main`, PROD).
   - `docs/PRIORIDADES.md`: the row and its section, if the work matches one of its tasks.
   - The spec's own **Estado** line.
   - `DEVELOPMENT.md` (or the club plugin's equivalent): technical notes, state of the phase, what was verified and what is still pending.
   - `docs/reservas/ACEPTACION.md`: only rows whose check was actually run.
   - End-user manuals and quick guides (`MANUAL-*.md`, `GUIA-RAPIDA-*.md` and their `.html`), if the change alters what the user sees or does. Outdated screenshots count: if you do not replace them, write it down as pending in `DEVELOPMENT.md`.
   - This file, if commands, paths, conventions or architecture changed.
3. Write absolute dates (`28 de octubre de 2026`), never "today" or "next week".
4. Keep the doc updates in the same commit as the work, or in a commit right after it on the same branch, before it reaches `main`.

A deployment to PROD with no code change still requires this pass: the "No desplegada en PROD" lines must change on the day of the deployment. If the deployment leaves something switched off or half done, write down in those same places what it is and how to switch it on.

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

## Club members module

Implements `docs/club/SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md` (part A in `main` and PROD since 11 November 2026; part B committed on branch `altas-socio-parte-b`, tested locally on 11 November 2026, not merged into `main` nor deployed; part C on branch `altas-socio-parte-c` (branched from part B), tested locally on 11 November 2026, not committed; manual tests left in `docs/club/altas-y-ciclo-de-vida-socio/pruebas.md`; part D pending). Code in `wp-content/plugins/club-triatlon-sulayr/src/Modules/Members/`, wired in `App::load_members()`. Sections 16, 18 and 19 of the spec have the implementation notes of parts A, B and C; 19.7 is the handoff for part D.

- **History is rows, not meta.** `MemberServices` (services with start/end dates, both inclusive, end null = current) and `StatusPeriods` (activo, congelado, baja) never delete rows: a change closes one row and opens another. Both write to `MemberLog`, which only inserts. Wrap bulk writes in `MemberLog::with_note()`.
- **Catalog is data.** Services are the CPT `sulayr_service` (slug = stable key); prices by member type and start date come from `ServiceCatalog::price_for()`. Member types, enrollment fee, notice days, email and consent texts, and step lists come from `MemberSettings::get()`, which falls back to defaults. Don't hard-code prices, types or texts.
- **Reservations contract.** Reservations only reads `sulayr_user_swimming = '1'` and `sulayr_user_athlete_status = 'active'`. The members module must keep writing those two metas from services and status (D38); reservations is not edited.
- **ACF dates** are stored as `Ymd`; the history tables use `Y-m-d`. Convert with `MemberDates::acf_to_iso()` / `iso_to_acf()`.
- **Roles.** `sulayr_member_manager` and `sulayr_shop_manager` are added on top of `athlete`. Check capabilities (`sulayr_manage_members`, `sulayr_view_member_log`), not role names. Managers have no `edit_users` in multisite, so member screens live under the Socios menu, not in `user-edit.php`.
- **Old ACF fields are retired (part C).** Plan, training type, swimming, directive and status are hidden in `user-edit.php` and Mi cuenta (`Lifecycle\LegacyFieldsHidden`). Services, type, freezes and leaves are changed only from Socios. Screens that used the old fields read `MemberSummary` (history) instead. The bridge `LegacyFieldsSync`, `LegacyMapping` and `AthleteUpdate` no longer exist.
- **Changes are scheduled rows (part C).** `Lifecycle\MemberChanges`: every service change, type change, leave or freeze is a row in `sulayr_member_requests` (`cambio`, `tipo`, `baja`, `congelacion`) with an effective date. History rows are written only when it is applied (daily task on the date, or at once for D27). Never write future-dated history rows; annulling a scheduled change must not touch history. Member requests come from Mi cuenta → Mis cuotas (`Lifecycle\FeesAccount`); manager actions are in the member file (`Admin\MemberActions`).
- **Joining (part B).** `Join\PublicJoinForm` (shortcode `[sulayr_solicitud_alta]`, posts to its own page, no nonce, honeypot + per-IP limit), `Admin\RequestsPage` (Socios → Solicitudes / Nueva alta, `admin-post.php`), `Join\JoinApproval` (create or link a network account, history, consents, steps, welcome email). Approval never uses `wp_new_user_notification()`. Requests live in `sulayr_member_requests` as JSON.
- **Reservations metas.** Write `sulayr_user_athlete_status` and `sulayr_user_swimming` only through `ReservationsAccess::apply()`, which derives them from the history and, if they change, calls `Join\ReservationsSync`. That class calls `SulayrReservas\Sync\SyncService::sync()` directly when the plugin is active, because the reservations `profile_update` hook only runs for users with `manage_options` (member managers and WP-Cron don't have it). This is the one place where the club plugin calls into reservations; reservations itself is not edited.
- **Daily task.** WP-Cron `sulayr_members_daily` (00:15 club time, `Lifecycle\MembersDailyTask`): applies the day's changes, ends freezes, activates pending joins and expires join requests (D16). Idempotent. Socios has an «Ejecutar ahora» button because PROD has no WP-CLI. It only recalculates reservation metas for members it touches, never for everyone.
- **Mi cuenta (D33, D32).** Athletes can't change status, DNI or birth date from Mi cuenta (`UserService` locks them). The image consent is a separate form (`Join\ImageConsentAccount`). Members on leave don't see «Perfil de deportista».

## Production

PROD is on Hostinger without SSH or WP-CLI. Code is deployed with FileZilla: upload new files first, then overwrite, and delete removed files by hand afterwards (FileZilla does not delete). Any one-off data task needed in PROD must be runnable from wp-admin by an administrator, with a dry run first. WP-CLI is only available locally.

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
