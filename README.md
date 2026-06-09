# Eventz

**Site:** [eventz.gravmud.site](https://eventz.gravmud.site) · **Repo:** [GravMUD/grav-plugin-eventz](https://github.com/GravMUD/grav-plugin-eventz)

Flat-file **events + RSVP** for Grav 2.0 — chapters, recurrence, Admin2 cockpit, iCal export, public embeds, optional Messenger wire-ins.

> *Mobilizon without Postgres, Elixir, or Framasoft purple.*

**License:** MIT — free forever.

---

## Requirements

| Package | Version |
|---------|---------|
| [Grav](https://github.com/getgrav/grav) | `>=2.0.0` |
| [Admin2](https://github.com/getgrav/grav-plugin-admin2) | `>=1.0.0` |
| [API](https://github.com/getgrav/grav-plugin-api) | `>=1.0.0` |

Optional: **grav-mud-messenger** for auto chat groups when you save an event.

---

## Installation

```bash
bin/gpm direct-install https://github.com/GravMUD/grav-plugin-eventz/releases/download/0.4.0/grav-plugin-eventz.zip
bin/grav cache
```

Once listed in GPM:

```bash
bin/gpm install eventz
bin/grav cache
```

Enable **Eventz** in Admin2 → Plugins.

### Upgrade from 0.3.x (two-plugin install)

1. Remove the old `grav-mud-eventz` engine + thin `eventz` shell.
2. Install one **eventz** package (v0.4.0+).
3. Rename `user/config/plugins/grav-mud-eventz.yaml` → `eventz.yaml` (or re-save in Admin2).

Event data in `user/data/mud-eventz/` is unchanged.

---

## Configuration

`user/config/plugins/eventz.yaml`:

```yaml
enabled: true
api_route: api/mud-eventz          # legacy path; stable for existing links
public_route: eventz               # /eventz, /eventz/embed.js, /eventz/event/{slug}
notify_email: chief@gravmud.site   # RSVP notification recipient
default_messenger_group_prefix: event-
```

**Admin2 → Eventz** — create events, chapters, recurrence, RSVP export, messenger wire status.

**Settings → Plugins → Eventz** — route prefixes and organiser email.

Admin2 sidebar + page registration use runtime API hooks only — **no writes to `admin-next.yaml`**.

---

## What it does

| Area | Detail |
|------|--------|
| Storage | Flat JSON in `user/data/mud-eventz/` — cPanel-friendly, git-friendly |
| Public UI | `/eventz` catalog, per-event pages, `embed.js` — **no Grav page required** |
| RSVP | Name, email, guests, note + honeypot; organiser email on submit |
| Calendar | Per-event `.ics` download |
| Chapters | Series hubs + recurring spawn (weekly / monthly / etc.) |
| Admin2 | Full cockpit at **Eventz** in the sidebar |
| Wire-ins | Optional Messenger group on save (when messenger is installed) |

Works on any Grav 2 site — not MUD-specific.

---

## Public surfaces (no `.mud` required)

| Path | Purpose |
|------|---------|
| `/eventz` | Event catalog + RSVP forms |
| `/eventz/event/{slug}` | Single event |
| `/eventz/series/{id}` | Chapter / series hub |
| `/eventz/embed.js` | Drop-in loader for any HTML page |

**Embed snippet:**

```html
<div data-mud-eventz data-series="my-chapter"></div>
<script src="/eventz/embed.js"></script>
```

**Iframe embed:**

```html
<div data-mud-eventz-iframe data-series="my-chapter" data-height="520"></div>
<script src="/eventz/embed.js"></script>
```

Optional: Grav `.mud` fences (see below) if you use the MUD compiler.

---

## API

Default public prefix: `/api/mud-eventz` (configurable).  
Grav 2 bridge: `/api/v1/mud-eventz/...` when the API plugin is enabled.

### Public routes

| Route | Method | Purpose |
|-------|--------|---------|
| `/events` | GET | List events + RSVP summary |
| `/event/{slug}` | GET | Single event + RSVP summary |
| `/rsvp/{slug}` | POST | Submit RSVP (JSON body) |
| `/rsvp/{slug}/summary` | GET | `{ rsvps, headcount }` |
| `/ics/{slug}` | GET | `text/calendar` download |
| `/series/{series}` | GET | Events in a chapter / series |

**RSVP POST body:**

```json
{
  "name": "Alex",
  "email": "you@example.com",
  "guests": 2,
  "note": "Vegetarian",
  "website": ""
}
```

`website` is a honeypot — must be empty.

### Admin routes (Grav 2 API bridge)

Prefix: `/api/v1/eventz/admin/...` (requires API token + `api.access`).

| Route | Method | Purpose |
|-------|--------|---------|
| `/stats` | GET | Dashboard counts |
| `/events` | GET | List events |
| `/event` | POST | Create / update event |
| `/chapters` | GET, POST | List / save chapters |
| `/chapters/{slug}` | GET | Chapter detail |
| `/chapters/{slug}/spawn` | POST | Spawn recurring occurrences |
| `/rsvps/{slug}` | GET | Full RSVP entries |
| `/rsvps/{slug}/csv` | GET | CSV export payload |
| `/event/{slug}/rsvp-open` | POST | `{ "open": true \| false }` |

Legacy **grav-mud-admin** shims at `/api/v1/mud-admin/eventz/...` remain for EvvyTink sites.

---

## Storage layout

```
user/data/mud-eventz/
  events/
    brisbane-pilot.json
    denver-chapter.json
  rsvp/
    brisbane-pilot.json
  chapters/
    getgrav-global.json
```

### Event file (minimal)

```json
{
  "slug": "brisbane-pilot",
  "title": "Community Meetup",
  "description": "Plain or markdown description",
  "starts_at": "2026-07-15T18:00:00+10:00",
  "ends_at": "2026-07-15T21:00:00+10:00",
  "venue": "Library · Room 2",
  "city": "Brisbane, QLD",
  "capacity": 40,
  "rsvp_open": true,
  "notify_email": "organiser@example.com",
  "chat_group": "meetup-brisbane",
  "series": "getgrav-brisbane"
}
```

### RSVP file

```json
{
  "updated": "2026-06-05T12:00:00+00:00",
  "entries": [
    {
      "name": "Alex",
      "email": "you@example.com",
      "guests": 1,
      "note": "",
      "at": "2026-06-05T12:00:00+00:00"
    }
  ]
}
```

You can edit JSON by hand, via Admin2, or via the API.

---

## `.mud` fences (optional)

If you use GravMUD / `.mud` pages:

```mud
:::eventz
:::

:::eventz{series="getgrav-global"}
:::

:::eventz{event="brisbane-pilot"}
:::
```

Aliases: `:::events` · `:::meetup` · `:::mud-eventz`

---

## Integrations

**GetGRAV! / goggrav** — campaign `/meetup` can delegate RSVP to Eventz when both plugins are enabled. Set `meetup.event_slug` in goggrav config to match your event file slug.

**Messenger** — on event save, Eventz can wire a chat group using `default_messenger_group_prefix` + event slug (requires grav-mud-messenger).

---

## Support

- [Discussions](https://github.com/GravMUD/grav-plugin-eventz/discussions)
- [Releases](https://github.com/GravMUD/grav-plugin-eventz/releases)

FutureVision Labs · Team DC · groundswell not fork `<3`
