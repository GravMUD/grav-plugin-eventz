# Eventz

**Site:** [eventz.gravmud.site](https://eventz.gravmud.site) · **Repo:** [GravMUD/grav-plugin-eventz](https://github.com/GravMUD/grav-plugin-eventz)

Flat-file **events + RSVP** for Grav 2.0 — chapters, recurrence, Admin2 cockpit, iCal, messenger wire-ins.

> *Mobilizon without Postgres, Elixir, or Framasoft purple.*

**License:** MIT — free forever.

## Requirements

| Package | Version |
|---------|---------|
| [Grav](https://github.com/getgrav/grav) | `>=2.0.0` |
| [Admin2](https://github.com/getgrav/grav-plugin-admin2) | `>=1.0.0` |
| [API](https://github.com/getgrav/grav-plugin-api) | `>=1.0.0` |

Optional: **grav-mud-messenger** for auto chat groups on event save.

## Installation

One plugin from the [latest release](https://github.com/GravMUD/grav-plugin-eventz/releases):

```bash
bin/gpm direct-install https://github.com/GravMUD/grav-plugin-eventz/releases/download/0.4.0/grav-plugin-eventz.zip
bin/grav cache
```

Once listed in GPM:

```bash
bin/gpm install eventz
bin/grav cache
```

Enable in Admin2 → Plugins.

### Upgrade from 0.3.x (two-plugin install)

1. Remove `grav-mud-eventz` and the old thin `eventz` shell if both were installed.
2. Install **one** `eventz` package (v0.4.0+).
3. Rename `user/config/plugins/grav-mud-eventz.yaml` → `eventz.yaml` (or re-save in Admin2).

Event data stays in `user/data/mud-eventz/` — unchanged.

## Configuration

**Admin2 → Eventz** — create events, chapters, recurrence, messenger wire.

**Settings → Plugins → Eventz** — API route, public route (`/eventz`), organiser email.

Admin2 sidebar registration uses runtime API hooks only — **no writes to `admin-next.yaml`**.

## Docs

Full spec: `Docs/GRAVMUD-EVENTZ.md` in the GravMUD monorepo.
