I would like to add my new plugin to the Grav Repository.

**Repository:** https://github.com/GravMUD/grav-plugin-eventz
**Release:** https://github.com/GravMUD/grav-plugin-eventz/releases/tag/0.4.0
**Direct install:** https://github.com/GravMUD/grav-plugin-eventz/releases/download/0.4.0/grav-plugin-eventz.zip
**Plugin name:** Eventz
**Plugin slug:** eventz
**License:** MIT
**Grav target:** Grav 2.0 / Admin2
**Site / docs:** https://eventz.gravmud.site
**Discussions:** https://github.com/GravMUD/grav-plugin-eventz/discussions

---

## Summary

**Eventz** is a flat-file events + RSVP plugin for Grav 2.0 — chapters, recurrence, iCal export, public `/eventz` UI + embed.js, Admin2 cockpit, optional Messenger group wire-ins. **Single plugin** (v0.4.0 merged engine + Admin2 page). Admin2 sidebar via runtime API hooks only — **does not write `admin-next.yaml`**.

---

## Dependencies

- grav >= 2.0.0
- admin2 >= 1.0.0
- api >= 1.0.0

Optional: grav-mud-messenger (auto chat groups on event save).

---

## Suggested maintainer test plan (~10 min)

```bash
bin/gpm direct-install https://github.com/GravMUD/grav-plugin-eventz/releases/download/0.4.0/grav-plugin-eventz.zip
bin/grav cache
```

1. Confirm `user/plugins/eventz` exists (one folder only).
2. Enable in Admin2 → Plugins.
3. Admin2 → **Eventz** page loads (RSVP / chapters UI).
4. Public route `/eventz` serves events HTML (no Grav page required).
5. `GET /api/v1/mud-eventz/stats` returns JSON when API plugin enabled.

Live reference: https://eventz.gravmud.site

---

## GPM checklist

- [x] MIT LICENSE
- [x] README.md with install + requirements
- [x] blueprints.yaml (semver **0.4.0**, slug **eventz**)
- [x] CHANGELOG.md (Grav format)
- [x] Semver GitHub release with zip asset
- [x] Docs site + CNAME (eventz.gravmud.site)
- [x] Good Grav citizen — no `admin-next.yaml` writes

---

## Install (once listed)

```bash
bin/gpm install eventz
```

**Note:** GPM issue #4116 requested dual slugs (`grav-mud-eventz` + `eventz`). **Superseded by v0.4.0** — please list **one slug: `eventz`** only.

Pairs with [JavaBean for Admin2](https://github.com/getgrav/grav/issues/4100) (theming).

Happy to adjust anything for the index. Thanks Andy!

— Damian Caynes · FutureVision Labs · chief@gravmud.site
