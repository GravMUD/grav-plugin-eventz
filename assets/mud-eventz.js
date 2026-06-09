(function () {
  "use strict";

  var API = window.GRAVMUD_EVENTZ_API || "/api/mud-eventz";

  function esc(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function fmtDate(ev) {
    if (ev.date_label) return ev.date_label;
    if (!ev.starts_at) return "Date TBA";
    try {
      return new Date(ev.starts_at).toLocaleString(undefined, {
        weekday: "short",
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
    } catch (_e) {
      return ev.starts_at;
    }
  }

  function fetchJson(url, opts) {
    opts = opts || {};
    opts.credentials = "same-origin";
    return fetch(url, opts).then(function (r) {
      return r.json();
    });
  }

  function renderSummary(el, summary) {
    if (!el || !summary) return;
    el.innerHTML =
      '<span class="mud-eventz-stat"><strong>' +
      esc(String(summary.rsvps || 0)) +
      '</strong> RSVPs</span>' +
      '<span class="mud-eventz-stat"><strong>' +
      esc(String(summary.headcount || 0)) +
      "</strong> headcount</span>";
  }

  function rsvpForm(slug, open) {
    if (!open) {
      return '<p class="mud-eventz-closed">RSVP is closed for this event.</p>';
    }
    return (
      '<form class="mud-eventz-form" data-event-slug="' +
      esc(slug) +
      '">' +
      '<div class="mud-eventz-grid">' +
      '<label class="mud-eventz-field"><span>Name</span><input name="name" required maxlength="120" autocomplete="name" placeholder="Your name"></label>' +
      '<label class="mud-eventz-field"><span>Email</span><input name="email" type="email" required autocomplete="email" placeholder="you@example.com"></label>' +
      '<label class="mud-eventz-field mud-eventz-field--narrow"><span>Guests (incl. you)</span><select name="guests">' +
      [1, 2, 3, 4, 5, 6, 7, 8]
        .map(function (n) {
          return '<option value="' + n + '">' + n + "</option>";
        })
        .join("") +
      "</select></label>" +
      "</div>" +
      '<label class="mud-eventz-field"><span>Note (optional)</span><textarea name="note" rows="3" maxlength="500" placeholder="Dietary needs, talk idea, accessibility…"></textarea></label>' +
      '<label class="mud-eventz-honey" aria-hidden="true"><span>Website</span><input name="website" tabindex="-1" autocomplete="off"></label>' +
      '<div class="mud-eventz-actions"><button type="submit" class="mud-eventz-submit">Submit RSVP</button></div>' +
      '<p class="mud-eventz-status" role="status"></p></form>'
    );
  }

  function bindForm(root) {
    var form = root.querySelector(".mud-eventz-form");
    if (!form) return;
    var status = form.querySelector(".mud-eventz-status");
    var summaryEl = root.querySelector(".mud-eventz-summary");
    var slug = form.getAttribute("data-event-slug") || "";

    form.addEventListener("submit", function (ev) {
      ev.preventDefault();
      if (status) status.textContent = "Sending…";
      var fd = new FormData(form);
      fetchJson(API + "/rsvp/" + encodeURIComponent(slug), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: fd.get("name"),
          email: fd.get("email"),
          guests: fd.get("guests"),
          note: fd.get("note"),
          website: fd.get("website"),
        }),
      })
        .then(function (data) {
          if (!data.ok) {
            throw new Error(data.error || "RSVP failed");
          }
          if (status) {
            status.textContent = "You're on the list! Pizza count updated. See you there 🍕";
            status.classList.add("mud-eventz-status--ok");
          }
          form.reset();
          renderSummary(summaryEl, data.summary);
        })
        .catch(function (err) {
          if (status) {
            status.textContent = err.message || "RSVP failed";
            status.classList.add("mud-eventz-status--error");
          }
        });
    });
  }

  function renderEventCard(ev, mode) {
    var slug = ev.slug || "";
    var rsvp = ev.rsvp || {};
    var open = ev.rsvp_open !== false;
    var ics = API + "/ics/" + encodeURIComponent(slug);
    var inner =
      mode === "list"
        ? '<article class="mud-eventz-card" id="event-' + esc(slug) + '">' +
          "<h3>" +
          esc(ev.title || slug) +
          "</h3>" +
          '<p class="mud-eventz-meta">' +
          esc(ev.city || "") +
          " · " +
          esc(fmtDate(ev)) +
          "</p>" +
          (ev.venue ? '<p class="mud-eventz-venue">' + esc(ev.venue) + "</p>" : "") +
          (ev.description ? '<div class="mud-eventz-desc">' + esc(ev.description) + "</div>" : "") +
          '<div class="mud-eventz-summary"></div>' +
          rsvpForm(slug, open) +
      '<p class="mud-eventz-links"><a href="' +
      esc(ics) +
      '">Add to calendar ↓</a></p></article>'
        : "";

    return inner;
  }

  function renderSingle(root, ev) {
    var slug = ev.slug || "";
    var open = ev.rsvp_open !== false;
    var ics = API + "/ics/" + encodeURIComponent(slug);
    root.innerHTML =
      '<article class="mud-eventz-single">' +
      "<h2>" +
      esc(ev.title || slug) +
      "</h2>" +
      '<p class="mud-eventz-meta">' +
      esc(ev.city || "") +
      " · " +
      esc(fmtDate(ev)) +
      "</p>" +
      (ev.venue ? '<p class="mud-eventz-venue">' + esc(ev.venue) + "</p>" : "") +
      (ev.description ? '<div class="mud-eventz-desc">' + esc(ev.description) + "</div>" : "") +
      '<div class="mud-eventz-summary"></div>' +
      rsvpForm(slug, open) +
      '<p class="mud-eventz-links"><a href="' +
      esc(ics) +
      '">Download iCal ↓</a></p></article>';
    renderSummary(root.querySelector(".mud-eventz-summary"), ev.rsvp);
    bindForm(root);
  }

  function renderList(root, events) {
    if (!events.length) {
      root.innerHTML = '<p class="mud-eventz-loading">No upcoming events yet.</p>';
      return;
    }
    root.innerHTML = events
      .map(function (ev) {
        return renderEventCard(ev, "list");
      })
      .join("");
    events.forEach(function (ev, i) {
      var card = root.querySelectorAll(".mud-eventz-card")[i];
      if (card) {
        renderSummary(card.querySelector(".mud-eventz-summary"), ev.rsvp);
        bindForm(card);
      }
    });
  }

  function initRoot(root) {
    var mode = root.getAttribute("data-mode") || "list";
    var slug = root.getAttribute("data-event") || root.getAttribute("data-slug") || "";
    var series = root.getAttribute("data-series") || "";
    var api = root.getAttribute("data-api") || API;

    root.innerHTML = '<p class="mud-eventz-loading">Loading events…</p>';

    if (mode === "event" && slug) {
      fetchJson(api + "/event/" + encodeURIComponent(slug)).then(function (data) {
        if (!data.ok || !data.event) throw new Error("Event not found");
        renderSingle(root, data.event);
      }).catch(function () {
        root.innerHTML = '<p class="mud-eventz-error">Could not load event.</p>';
      });
      return;
    }

    var url = api + "/events";
    var chapter = root.getAttribute("data-chapter") || "";
    if (mode === "series" && series) {
      url = api + "/series/" + encodeURIComponent(series);
    } else if (mode === "chapter" && chapter) {
      url = api + "/chapter/" + encodeURIComponent(chapter) + "/events";
    } else if (chapter && mode === "list") {
      url = api + "/chapter/" + encodeURIComponent(chapter) + "/events";
    }

    fetchJson(url)
      .then(function (data) {
        if (!data.ok) throw new Error("Load failed");
        renderList(root, data.events || []);
      })
      .catch(function () {
        root.innerHTML = '<p class="mud-eventz-error">Could not load events.</p>';
      });
  }

  function boot() {
    document.querySelectorAll("[data-mud-eventz]").forEach(initRoot);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }

  window.__mudEventzBooted = true;
  document.dispatchEvent(new Event("mud-eventz-ready"));
})();
