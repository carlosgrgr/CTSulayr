# AGENT.md

This file provides guidance to IA when working with code in this repository.

## What this repo is

A full WordPress **Multisite** install (subdomain mode) committed as-is, core and third-party plugins included. Almost everything under `wp-admin/`, `wp-includes/` and `wp-content/` is vendor code — do not edit it. The custom code lives in:

| Path | What it is |
|---|---|
| `wp-content/plugins/club-triatlon-sulayr/` | Club site plugin: athletes (`athlete` role), ACF fields, members module (`src/Modules/Members/`: service catalog, member history, change log, manager roles, receipts with PDF via dompdf, coach list, welcome pack), internal ranking (`src/Modules/Ranking/`), online card payments with Redsys (`src/Modules/Payments/`, in progress), wallet, WooCommerce glue. Stripe was removed (members spec, part A). Composer/PSR-4 (`ClubTriatlonSulayr\` → `src/`), `vendor/` is committed. |
| `wp-content/plugins/sulayr-reservas/` | Network-activated swimming reservations plugin (the current release work). No Composer, own autoloader (`SulayrReservas\` → `src/`), bootstrapped from `Plugin::init()` on `plugins_loaded`. |
| `wp-content/plugins/sulayr-scripts-manager/` | Single-file plugin, network-activated: per-site tracking codes (GTM/Analytics) in Settings → Scripts Manager, printed right after `<head>`, right after `<body>` (Astra's `astra_head_top` / `astra_body_top`, falling back to `wp_head` / `wp_body_open`) or in `wp_footer`. Only users with `unfiltered_html` (super admins in multisite) can edit them. |
| `wp-content/themes/ct-sulayr-theme/` | Astra child theme for the club site (blog 1). |
| `wp-content/themes/reservations-theme/` | Astra child theme for the reservations site (blog 2). |

Local sites (Apache, docroot `/var/www/html/CTSulayr`):

- Blog 1 — club: `clubtriatlonsulayr.local.com`, table prefix `wp_`
- Blog 2 — reservations (CN Nazarí): `cnnazari.clubtriatlonsulayr.local.com`, table prefix `wp_2_`

`WP_DEBUG` and `WP_DEBUG_LOG` are on; errors go to `wp-content/debug.log`. `wp-config.php` is gitignored.

## Commands

There is no build step. Verification is done with WP-CLI, scripts that load WordPress, and a PHPUnit suite for the club plugin's pure PHP classes (below).

```bash
cd /var/www/html/CTSulayr
wp site list --fields=blog_id,url                       # confirm both network sites
wp eval 'var_dump( get_current_blog_id() );'            # run code in blog 1
wp --url=cnnazari.clubtriatlonsulayr.local.com eval '…' # run code in blog 2 (reservations)
wp eval-file /path/to/check.php                         # one-off verification script
php -l wp-content/plugins/sulayr-reservas/src/Path/To/File.php   # syntax check
```

- Club plugin tests (pure PHP, no WordPress: ranking calculation and the Redsys client): `cd wp-content/plugins/club-triatlon-sulayr/tests && composer install && vendor/bin/phpunit`. They have their own `composer.json`; `tests/vendor/` is gitignored and `tests/` is never uploaded to PROD. With WordPress: `wp eval-file wp-content/plugins/club-triatlon-sulayr/tests/wp/check-ranking.php` and `…/tests/wp/check-pagos.php` and `…/tests/wp/check-cobro-mensual.php` (each creates and deletes its own test data; the payment checks inject test credentials, simulate signed Redsys answers and intercept emails, so they never call Redsys or send mail; `check-cobro-mensual.php` simulates March 2001 day by day).
- Club plugin dependencies: `composer install` inside `wp-content/plugins/club-triatlon-sulayr/`. Its `phpcs.xml` (WPCS-based) is the lint config, but WPCS is not installed in `vendor/` right now.
- The local CLI runs PHP 8.5, but both plugins target **PHP 7.4**: no union types, `mixed`, constructor promotion, enums, `match`, or nullsafe operator.

## Project documents (read before changing reservations behaviour)

Reservations specs are in Spanish under `docs/reservas/` and use the `-RESERVAS` suffix. Club-site specs are under `docs/club/` and are listed apart in `docs/FASES.md`. They are outside the precedence below. When reservations specs disagree, this order wins:

1. `docs/reservas/SPEC-MEJORAS-RESERVAS.md` (its section 16 wins over earlier paragraphs in the same file) and `docs/reservas/SPEC-FASE11-RESERVAS.md` override `docs/reservas/SPEC-RESERVAS.md` where they change behaviour. So does `docs/reservas/SPEC-ASISTENTES-SESION-RESERVAS.md` for section 3.1: swimmers see the names and reservation photos of the other swimmers booked in a session.
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

Implements `docs/club/SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md` (part A in `main` and PROD since 11 November 2026; parts B, C and D, with the Sulayr brand, merged into `main` (`21914c88d`) and their code uploaded to PROD on 12 November 2026; removed files deleted, settings configured, checks done and receipts started from November 2026 that day, following `docs/club/altas-y-ciclo-de-vida-socio/despliegue-prod.md`; manual tests left in `docs/club/altas-y-ciclo-de-vida-socio/pruebas.md`). Code in `wp-content/plugins/club-triatlon-sulayr/src/Modules/Members/`, wired in `App::load_members()`. Sections 16, 18, 19 and 20 of the spec have the implementation notes of parts A to D.

- **History is rows, not meta.** `MemberServices` (services with start/end dates, both inclusive, end null = current) and `StatusPeriods` (activo, congelado, baja) never delete rows: a change closes one row and opens another. Both write to `MemberLog`, which only inserts. Wrap bulk writes in `MemberLog::with_note()`.
- **Catalog is data.** Services are the CPT `sulayr_service` (slug = stable key); prices by member type and start date come from `ServiceCatalog::price_for()`. Member types, enrollment fee, notice days, email and consent texts, and step lists come from `MemberSettings::get()`, which falls back to defaults. Don't hard-code prices, types or texts.
- **Reservations contract.** Reservations only reads `sulayr_user_swimming = '1'` and `sulayr_user_athlete_status = 'active'`. The members module must keep writing those two metas from services and status (D38); reservations is not edited.
- **ACF dates** are stored as `Ymd`; the history tables use `Y-m-d`. Convert with `MemberDates::acf_to_iso()` / `iso_to_acf()`.
- **Roles.** `sulayr_member_manager` and `sulayr_shop_manager` are added on top of `athlete`. Check capabilities (`sulayr_manage_members`, `sulayr_view_member_log`), not role names. Managers have no `edit_users` in multisite, so member screens live under the Socios menu, not in `user-edit.php`.
- **Old ACF fields are retired (part C).** Plan, training type, swimming, directive and status are hidden in `user-edit.php` and Mi cuenta (`Lifecycle\LegacyFieldsHidden`). Services, type, freezes and leaves are changed only from Socios. Screens that used the old fields read `MemberSummary` (history) instead. The bridge `LegacyFieldsSync`, `LegacyMapping` and `AthleteUpdate` no longer exist.
- **Changes are scheduled rows (part C).** `Lifecycle\MemberChanges`: every service change, type change, leave or freeze is a row in `sulayr_member_requests` (`cambio`, `tipo`, `baja`, `congelacion`) with an effective date. History rows are written only when it is applied (daily task on the date, or at once for D27). Never write future-dated history rows; annulling a scheduled change must not touch history. Member requests come from Mi cuenta → Mis cuotas (`Lifecycle\FeesAccount`, which only shows a receipts summary); the member's receipts and PDFs are in Mi cuenta → Mis recibos (`Receipts\ReceiptsAccount`, endpoint `mis-recibos`, every status including «No aplica»); manager actions are in the member file (`Admin\MemberActions`).
- **Joining (part B).** `Join\PublicJoinForm` (shortcode `[sulayr_solicitud_alta]`, posts to its own page, no nonce, honeypot + per-IP limit), `Admin\RequestsPage` (Socios → Solicitudes / Nueva alta, `admin-post.php`), `Join\JoinApproval` (create or link a network account, history, consents, steps, welcome email). Approval never uses `wp_new_user_notification()`. Requests live in `sulayr_member_requests` as JSON.
- **Reservations metas.** Write `sulayr_user_athlete_status` and `sulayr_user_swimming` only through `ReservationsAccess::apply()`, which derives them from the history and, if they change, calls `Join\ReservationsSync`. That class calls `SulayrReservas\Sync\SyncService::sync()` directly when the plugin is active, because the reservations `profile_update` hook only runs for users with `manage_options` (member managers and WP-Cron don't have it). This is the one place where the club plugin calls into reservations; reservations itself is not edited.
- **Daily task.** WP-Cron `sulayr_members_daily` (00:15 club time, `Lifecycle\MembersDailyTask`): applies the day's changes, ends freezes, activates pending joins, expires join requests (D16), then books the board payment in the wallet and generates the month's receipts once per month (both off while «Primer mes con recibos» is empty). Idempotent. Socios has an «Ejecutar ahora» button because PROD has no WP-CLI. It only recalculates reservation metas for members it touches, never for everyone.
- **Member type has dated history (part D).** `MemberTypes` (table `sulayr_member_type_periods`) holds the type per date; the meta `sulayr_member_type` is only the current type. Change a type with `MemberTypes::set( $user, $type, $date )`, not by writing the meta. Receipts use the type of day 1 of the month.
- **Receipts (part D).** `Receipts\ReceiptCalculator` computes the automatic lines of a member and month from history and catalog; `Receipts\Receipts` stores receipts, lines and payments (generation, regeneration, outdated check, corrections, penalties, payments). Never change a paid receipt: corrections go to the next one. History changes fire `sulayr_member_history_changed` (from `MemberLog::record()` for services and status, and from `MemberTypes` and `Subsidies`); receipts of those members are re-checked on `shutdown`. Wallet movements created by code go through `Receipts\WalletLedger` so they look like manual ones. PDF with dompdf (`Receipts\ReceiptPdf`); `composer.json` pins `config.platform.php` to 7.4, so run `composer` there and keep that pin. The PDF uses static brand fonts (TTF + `.ufm` metrics, OFL) from `assets/pdf-fonts/`, registered in memory per request; dompdf's font cache goes to the system temp dir, never to the plugin.
- **Sulayr brand.** Every Socios screen wraps its content in `.sulayr-brand` (styles and fonts from `Admin\BrandStyles`; main action buttons get `button-primary sulayr-cta`, in Sol). Front member pages (join form, image consent, Mis cuotas, Mis recibos) use the theme's `--sulayr-*` variables in `public/css/styles.css`. `MemberMailer::send()` wraps every member email in `MemberMailer::layout()` and embeds the crest as `cid:sulayr-escudo` during that send; use `MemberMailer::button()` for the main link of an email. Brand rules: `docs/club/identidad-sulayr.html`.
- **Mi cuenta (D33, D32).** Athletes can't change status, DNI or birth date from Mi cuenta (`UserService` locks them). The image consent is a separate form (`Join\ImageConsentAccount`). Members on leave don't see «Perfil de deportista».

## Club ranking module

Implements `docs/club/SPEC-RANKING-INTERNO.md` (steps R0 to R6 in `main` (`7fb03aba1`) and uploaded to PROD on 16 November 2026. The configuration (sexes, manager role) is done. The PROD checks are still pending; see `docs/club/ranking-interno/despliegue-prod.md`). Code in `wp-content/plugins/club-triatlon-sulayr/src/Modules/Ranking/`, wired in `App::load_ranking()`. Section 13 of the spec has the implementation notes. `docs/club/resumen-ranking-interno.html` is a non-technical summary for the club board (written by hand on 16 November 2026, not generated from the spec); update it when the scoring or the workflow changes. Results are imported from a JSON that AI extracts from the federation PDFs (SPEC §14, decisions R39 to R41, merged into `main` and uploaded to PROD on 16 November 2026); manual entry is the fallback.

- **Calculation is pure PHP.** `Ranking\Calc\*` (`Keys`, `Baremo`, `AgeGroups`, `RaceTime`, `PointsCalculator`, `Standings`, `ResultValidator`) must not call WordPress functions: PHPUnit loads them without WordPress. Labels live in `Ranking\Labels`.
- **Tables, not CPTs.** `RankingSchema` (option `sulayr_ranking_db_version`): competitions, references (winner time and finishers per scope/team category/sex/age group), results (one per member and competition, with stored points) and an insert-only log (`RankingLog`, not `MemberLog`).
- **Each season has its own scoring.** Settings (ACF options page, post_id `sulayr_ranking`) are the current season's. Saving freezes the previous season's copy (`sulayr_ranking_baremo_<year>`) and the current one, then recalculates only the current season. Reads never create copies: `RankingSettings::for_season()` without `$create` falls back to the current settings. Always read `for_season()`, never the ACF fields, when calculating.
- **Only on blog 1.** The plugin is activated only on blog 1 of the multisite (not network-wide) and the ranking doesn't call `switch_to_blog()`. If someone activates it on another blog, empty tables are created there and nothing protects against it in code (SPEC §7).
- **Points are stored; standings are cached.** `RankingService::recalculate_competition()` after any change; standings in a transient per season, invalidated by bumping `sulayr_ranking_cache_version`. Sex and birth date changes recalculate that member on `shutdown`; status changes (`sulayr_member_history_changed`) only bump the cache.
- **Who appears.** Only `athlete` users with status `active` or `congelado` (`RankingRoster`). Members on leave keep their results but are hidden; they come back if they rejoin.
- **Sex** is the member meta `sulayr_member_sex` (`m`/`f`), required in the join form. Ranking managers set it in Ranking → Deportistas (they have no `edit_users`).
- **Roles.** `sulayr_ranking_manager` (on top of `athlete`) and administrators have `sulayr_manage_ranking`; member managers don't. Check the capability, not the role.
- **Import (SPEC §14).** Ranking → Importar resultados (`Admin\ImportPage`) reads the JSON (`Calc\ImportParser`), merges every classification with the same team category and sex (federado and open count as one: positions recomputed by time, fastest time is the winner, `Calc\ImportMerger`), matches athletes to members by name (`Calc\NameMatcher`; doubtful matches are listed for the manager) and opens «Añadir competición» prefilled through `Notices::save_state()`, without saving. The JSON format and the AI prompt live in `admin/ranking-import-prompt.md` (shown by the admin page). The skill `.claude/skills/ranking-importar/` is self-contained so it also runs in the Claude desktop app and claude.ai (upload the folder as a .zip): it carries an identical copy (`formato.md`) and a stdlib-only Python port of the parser and merger (`scripts/validar.py`). It takes attached PDFs or paths and hands back `resultados.json` for download; it never writes into the repo. `tests/Unit/ImportSkillTest.php` fails if the copy or the Python rules drift, so change the prompt, `formato.md`, `ImportParser`/`ImportMerger`, `validar.py` and SPEC §14.2 together.
- **Interest bonus (SPEC §15, R42/R43).** A competition can carry `interest_bonus` (0 or a multiple of the season's `interest_step` up to `interest_max`, defaults 50/300, stored in the season's baremo) and an optional `interest_label`. `PointsCalculator` adds it like the finish bonus (finishers with a reference only, absolute and age group); each result keeps a copy in `results.interest_bonus` for the breakdown. Allowed values: `RankingSettings::interest_values( $season )`, passed to `ResultValidator::validate()` as the 5th argument (default `[0]`). Settings refuse a step/max that would invalidate a bonus already used in the current season (`Baremo::interest_conflicts()`). Schema version 2.
- **Static asset versions.** PROD caches CSS and JS for seven days, so `App::asset_version()` appends the file's mtime to `SULAYR_VERSION` for the plugin's main front and admin styles and scripts. Use it for any new enqueue.
- **Front.** WooCommerce endpoint `ranking-interno` (`Front\RankingAccount`) and shortcode `[sulayr_ranking]`, both through `Front\RankingView` (GET parameters, read only). Templates under `templates/ranking/` can be overridden by the theme in `sulayr/ranking/` (`TemplateService::locate()`, filter `sulayr_ranking_template`); other plugin templates can't.

## Club payments module

Implements `docs/club/SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md` (part B in progress on branch `pago-online-parte-b`: steps B1 and B2 done and tested in local on 17 November 2026, also by hand in the browser; step B3, charges with the stored card, done on 23 November 2026 and tested against Redsys's test environment; part C, the monthly charge, in progress on branch `pago-online-parte-c` (branched from `pago-online-parte-b`): steps C1 and C2 done and tested in local on 23 November 2026; not in `main` nor PROD). Code in `wp-content/plugins/club-triatlon-sulayr/src/Modules/Payments/`; `CardPayments` is wired in `App::load_members()` and `CardAccount` on the front only. Sections 11 and 12 of the spec have the implementation notes and what is pending (automatic status query of a charge without response, C11 card validation without amount). Until then a manager closes such a charge by hand from the receipt detail (`CardCharges::resolve()`), after checking Redsys's portal. The official Redsys WooCommerce module (`wp-content/plugins/redsyspur/`, vendor code) is for the shop only (part A); it doesn't do recurring charges.

- **Credentials only in `wp-config.php` (C29).** `SULAYR_REDSYS_MERCHANT_CODE`, `SULAYR_REDSYS_TERMINAL`, `SULAYR_REDSYS_SECRET_KEY`, `SULAYR_REDSYS_ENVIRONMENT` (`test`; only the exact value `live` uses the real environment). Never in the database or the repo. Without them nothing about card payments is shown, so the code can go to PROD before the contract. The local `wp-config.php` has Redsys's **public** test credentials (generic merchant 999008881), not the club's.
- **Redsys client is pure PHP.** `Payments\Redsys\*` (`RedsysConfig`, `RedsysSignature`, `RedsysOrder`, `RedsysRequest`, `RedsysNotification`, `RedsysRestResult`) must not call WordPress functions: PHPUnit loads them without WordPress. Parameter names and URLs follow Redsys's public docs (MIT tokenization) and were checked against its test environment on 23 November 2026.
- **Charges without the member (B3).** `CardCharges::charge()` posts to `trataPeticionREST` (MIT with the stored reference) under a per-receipt MySQL `GET_LOCK`, and applies a signed answer through `CardPayments::apply()`, the same code as the notification. A `SIS` error means no charge (except `SIS0051`); anything else unclear leaves the transaction `enviada`, which blocks the receipt, and emails the club. `CardCharges::use_transport()` swaps the HTTP call in `check-pagos.php`.
- **Monthly charge (part C).** `MonthlyCharges` keeps each month's state in the option `sulayr_tpv_charges` (approval, notices, charge day, retries, batch results). `MembersDailyTask` calls `MonthlyCharges::run()` as its step 7, and approving schedules a single `sulayr_tpv_charge_now` event. Every step marks the state, so running twice never charges twice; a MySQL lock guards the batch. Dates go through `MonthlyCharges::today()` (`use_today()` in checks). Member emails (C23) are `ChargeNotices`, with texts `email_tpv_*` in Socios → Ajustes → Emails. The screens are `ChargesPanel`, hooked into `ReceiptsPage` through `sulayr_receipts_month_panel` and `sulayr_receipt_detail_payments`. Schema 6 adds `expiry_notice_at` to cards.
- **Tables in `MembersSchema` (version 6).** `sulayr_payment_cards` (one active card per member; rows are never deleted) and `sulayr_tpv_transactions` (`ds_order` = `AAMM` + `Q` + id with 7 digits, NULL until the id exists, unique).
- **Only the notification records payments.** `CardPayments::handle_notification()` (REST `POST /wp-json/sulayr/v1/redsys`, public, authenticated by the signature) closes the transaction first with `TpvTransactions::close()` (only updates while `enviada`), then adds the payment and saves the card, so repeated or concurrent notifications do nothing. The return pages only show the state. Mismatched amounts or a payment that no longer fits the receipt are not recorded and the club gets an email.
- **Payment method `tpv`.** Only `Receipts::add_tpv_payment()` records it; `Receipts::add_payment()` rejects it and the manual form doesn't offer it.
- **In-flight rule.** Never start a charge on a receipt with an `enviada` transaction (`TpvTransactions::in_flight()`). Redirect transactions (`pago_manual`, `validacion`) stop blocking after 30 minutes because an abandoned Redsys page never notifies; charges without the member (part C) always block.
- **Charge method.** Meta `sulayr_charge_method` (`tpv`/`otro`); empty follows the setting (`ChargeMethod::get()`), so switching everyone after the pilot is a settings change. Recurring-charge authorization is the consent type `cargos_recurrentes`, kept out of `MemberConsents::types()` so it never appears in the join form.

## Production

PROD is on Hostinger without SSH or WP-CLI. Code is deployed with FileZilla: upload new files first, then overwrite, and delete removed files by hand afterwards (FileZilla does not delete). Members parts B, C and D were uploaded to PROD on 12 November 2026 following `docs/club/altas-y-ciclo-de-vida-socio/despliegue-prod.md` (steps 1 to 4); all its steps are done. Any one-off data task needed in PROD must be runnable from wp-admin by an administrator, with a dry run first. WP-CLI is only available locally.

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
