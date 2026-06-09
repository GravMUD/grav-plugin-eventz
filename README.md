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

Install **both** plugins from the [latest release](https://github.com/GravMUD/grav-plugin-eventz/releases):

1. `grav-mud-eventz` — engine, API, public UI
2. `eventz` — Admin2 page shell (`/plugin/eventz`)

```bash
bin/gpm direct-install https://github.com/GravMUD/grav-plugin-eventz/releases/download/0.3.0/grav-mud-eventz.zip
bin/gpm direct-install https://github.com/GravMUD/grav-plugin-eventz/releases/download/0.3.0/grav-plugin-eventz.zip
bin/grav cache
```

Enable both in Admin2 → Plugins.

## Configuration

**Admin2 → Eventz** — create events, chapters, recurrence, messenger wire.

**Settings → Plugins → Eventz** — API route, public route (`/eventz`), organiser email.

## Docs

Full spec: `Docs/GRAVMUD-EVENTZ.md` in the GravMUD monorepo.
