# v0.4.2
## 06/12/2026

1. [](#bugfix)
    * **Grav API `/api/v1/*` 500 on RC5 deploys** — public JSON at `/api/mud-eventz` via early `interceptPublicApi()`
    * **Embed defaults** — fence + Twig API base use legacy direct path, not `/api/v1/mud-eventz`

# v0.4.1
## 06/05/2026

1. [](#security)
    * **Admin API** — PII/CSV/mutations gated on `api.config.read` / `api.config.write` (not bare `api.access`)
    * **CSV export** — formula-injection prefix for cells starting with `=+-@`
    * **RSVP mail** — CRLF stripped from names/subjects; `From:` from site email config (not hardcoded domain)
2. [](#improved)
    * **Data path** — `user-data://eventz/` via locator (not `GRAV_ROOT/user/data/mud-eventz`)
    * **Defaults** — empty `notify_email`, plugin ships disabled; generic Admin2/embed copy (no site-specific slugs)
    * **API bridge** — unconditional `onApi*` registration; removed legacy no-API fallback engine
3. [](#bugfix)
    * RSVP capacity + `max_rsvp_entries` guard on submit

# v0.4.0
## 06/09/2026

1. [](#improved)
    * **Single plugin** — merged `grav-mud-eventz` engine + Admin2 shell into one `eventz` package (Andy-good-citizen lane)
    * Config key `plugins.eventz` (reads legacy `plugins.grav-mud-eventz` if present)
    * Runtime Admin2 registration only — no `admin-next.yaml` writes
2. [](#bugfix)
    * GPM: one zip `grav-plugin-eventz.zip`, one slug `eventz`

# v0.3.0
## 06/09/2026

1. [](#new)
    * **GPM release** — GitHub repo, docs site at [eventz.gravmud.site](https://eventz.gravmud.site)
    * **Messenger wire** — auto-create chat groups on event save (`MudEventzWire` + `MudMessengerGroups::upsert`)
    * **Admin2 Eventz page** — chapters, recurrence, messenger wire status on save
2. [](#improved)
    * Public `/eventz` UI, embed.js, iCal export, series hub
    * Grav 2 API bridge at `/api/v1/mud-eventz`

# v0.2.0
## 05/2026

1. [](#new)
    * EvvyTink admin — RSVP list, CSV export, close/reopen
    * `:::eventz` fence · chapters · wire-ins skeleton

# v0.1.0
## 04/2026

1. [](#new)
    * Flat-file events + RSVP skeleton
    * goggrav `/meetup` delegation
