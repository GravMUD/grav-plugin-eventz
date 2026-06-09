/**
 * GravMUD Eventz — Admin2 RSVP + chapters + recurrence
 */
(function () {
  const TAG = window.__GRAV_PAGE_TAG || 'grav-eventz--page';
  const BTN = 'inline-flex items-center rounded-md border border-border bg-muted/40 px-3 py-1.5 text-xs font-semibold hover:bg-accent';
  const BTN_PRI = 'inline-flex items-center rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground hover:opacity-90';
  const INPUT = 'flex h-9 w-full rounded-md border border-input bg-muted/50 px-3 py-1 text-sm';
  const TEXTAREA = 'flex min-h-[4rem] w-full rounded-md border border-input bg-muted/50 px-3 py-2 text-sm';
  const FREQ = ['weekly', 'monthly', 'quarterly', 'yearly'];

  function apiCfg() {
    return {
      serverUrl: window.__GRAV_API_SERVER_URL || window.__GRAV_CONFIG__?.serverUrl || '',
      apiPrefix: window.__GRAV_API_PREFIX || window.__GRAV_CONFIG__?.apiPrefix || '/api/v1',
      token: window.__GRAV_API_TOKEN || null,
    };
  }

  function apiUrl(path) {
    const c = apiCfg();
    return `${c.serverUrl}${c.apiPrefix}${path.startsWith('/') ? path : `/${path}`}`;
  }

  async function api(path, options) {
    const c = apiCfg();
    const headers = { Accept: 'application/json', ...(options?.headers || {}) };
    if (!(options?.body instanceof FormData)) headers['Content-Type'] = 'application/json';
    if (c.token) headers['X-API-Token'] = c.token;
    const res = await fetch(apiUrl(path), { ...options, headers, credentials: 'include' });
    const json = await res.json();
    const data = json.data !== undefined ? json.data : json;
    if (!res.ok) throw new Error(data.detail || data.error || data.message || `HTTP ${res.status}`);
    return data;
  }

  function esc(s) {
    return String(s ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  class EventzAdminPage extends HTMLElement {
    connectedCallback() {
      if (this._booted) return;
      this._booted = true;
      this._tab = 'rsvp';
      this._slug = '';
      this._chapterSlug = '';
      this._chapter = null;
      this.className = 'block h-full min-h-[28rem] text-foreground';
      this.innerHTML = `
        <div class="flex h-full min-h-[28rem] flex-col gap-4 p-6">
          <header class="flex flex-wrap items-start justify-between gap-3 border-b border-border pb-4">
            <div>
              <h2 class="text-lg font-bold">Eventz</h2>
              <p class="mt-1 max-w-xl text-sm text-muted-foreground">Chapters · repeating meetups · RSVP admin · CSV export.</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" data-tab="rsvp" class="${BTN_PRI}">RSVPs</button>
              <button type="button" data-tab="chapters" class="${BTN}">Chapters</button>
            </div>
          </header>
          <div data-stats class="flex flex-wrap gap-4 rounded-lg border border-border bg-muted/20 px-4 py-3 text-sm"></div>

          <div data-panel-rsvp class="grid min-h-0 flex-1 gap-4 lg:grid-cols-[minmax(0,280px)_1fr]">
            <aside class="overflow-y-auto rounded-lg border border-border bg-muted/10 p-3">
              <h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-muted-foreground">Events</h3>
              <ul data-events class="space-y-2 text-sm"></ul>
            </aside>
            <section class="overflow-y-auto rounded-lg border border-border bg-muted/10 p-3">
              <div class="mb-3 flex flex-wrap gap-2">
                <button type="button" data-refresh class="${BTN}">Refresh</button>
                <button type="button" data-export class="${BTN}">Export CSV</button>
                <button type="button" data-close class="${BTN}">Close RSVP</button>
                <button type="button" data-open class="${BTN_PRI}">Reopen RSVP</button>
              </div>
              <h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-muted-foreground">RSVPs</h3>
              <ul data-rsvps class="space-y-2 text-sm"></ul>
            </section>
          </div>

          <div data-panel-chapters class="hidden min-h-0 flex-1 gap-4 lg:grid lg:grid-cols-[minmax(0,240px)_1fr]">
            <aside class="overflow-y-auto rounded-lg border border-border bg-muted/10 p-3">
              <div class="mb-2 flex items-center justify-between gap-2">
                <h3 class="text-xs font-bold uppercase tracking-wide text-muted-foreground">Chapters</h3>
                <button type="button" data-chapter-new class="${BTN}">+ New</button>
              </div>
              <ul data-chapters class="space-y-2 text-sm"></ul>
            </aside>
            <section class="overflow-y-auto rounded-lg border border-border bg-muted/10 p-4">
              <div class="mb-3 flex flex-wrap gap-2">
                <button type="button" data-chapter-save class="${BTN_PRI}">Save chapter</button>
                <button type="button" data-chapter-spawn class="${BTN}">Spawn next occurrence</button>
              </div>
              <form data-chapter-form class="grid gap-3 lg:grid-cols-2">
                <label class="block text-sm lg:col-span-2"><span class="mb-1 block text-muted-foreground">Title</span>
                  <input data-f="title" type="text" class="${INPUT}"></label>
                <label class="block text-sm"><span class="mb-1 block text-muted-foreground">Slug</span>
                  <input data-f="slug" type="text" class="${INPUT}" pattern="[a-z0-9_-]+"></label>
                <label class="block text-sm"><span class="mb-1 block text-muted-foreground">Series</span>
                  <input data-f="series" type="text" class="${INPUT}" placeholder="my-series"></label>
                <label class="block text-sm lg:col-span-2"><span class="mb-1 block text-muted-foreground">Description</span>
                  <textarea data-f="description" class="${TEXTAREA}"></textarea></label>
                <label class="block text-sm"><span class="mb-1 block text-muted-foreground">City</span>
                  <input data-f="city" type="text" class="${INPUT}"></label>
                <label class="block text-sm"><span class="mb-1 block text-muted-foreground">Venue</span>
                  <input data-f="venue" type="text" class="${INPUT}"></label>
                <label class="block text-sm"><span class="mb-1 block text-muted-foreground">Chat group</span>
                  <input data-f="default_chat_group" type="text" class="${INPUT}"></label>
                <label class="block text-sm"><span class="mb-1 block text-muted-foreground">Capacity</span>
                  <input data-f="capacity" type="number" min="0" class="${INPUT}"></label>
                <label class="flex items-center gap-2 text-sm lg:col-span-2">
                  <input data-f="recurrence.enabled" type="checkbox" class="rounded border-input">
                  <span>Repeating meetup (spawn next from schedule)</span></label>
                <label class="block text-sm"><span class="mb-1 block text-muted-foreground">Frequency</span>
                  <select data-f="recurrence.frequency" class="${INPUT}">${FREQ.map((f) => `<option value="${f}">${f}</option>`).join('')}</select></label>
                <label class="block text-sm" data-weekday-wrap><span class="mb-1 block text-muted-foreground">Weekday (weekly)</span>
                  <select data-f="recurrence.weekday" class="${INPUT}">
                    ${['monday','tuesday','wednesday','thursday','friday','saturday','sunday'].map((d) => `<option value="${d}">${d}</option>`).join('')}
                  </select></label>
                <label class="block text-sm" data-dom-wrap><span class="mb-1 block text-muted-foreground">Day of month (1–28)</span>
                  <input data-f="recurrence.day_of_month" type="number" min="1" max="28" class="${INPUT}"></label>
                <label class="block text-sm"><span class="mb-1 block text-muted-foreground">Time (24h)</span>
                  <input data-f="recurrence.time" type="text" class="${INPUT}" placeholder="18:00"></label>
                <label class="block text-sm"><span class="mb-1 block text-muted-foreground">Timezone</span>
                  <input data-f="recurrence.timezone" type="text" class="${INPUT}" placeholder="Australia/Brisbane or AEST"></label>
              </form>
              <p class="mt-2 text-xs text-muted-foreground">Saving a chapter with repeat enabled auto-creates the first event if none exist yet. Frontend lists <strong>events</strong> — chapters are the template.</p>
              <div class="mt-4">
                <h4 class="mb-2 text-xs font-bold uppercase tracking-wide text-muted-foreground">Chapter events</h4>
                <ul data-chapter-events class="space-y-2 text-sm"></ul>
              </div>
              <div class="mt-6 rounded-lg border border-dashed border-border p-4">
                <h4 class="mb-2 text-xs font-bold uppercase tracking-wide text-muted-foreground">Add one-off event</h4>
                <div class="grid gap-3 lg:grid-cols-2">
                  <label class="block text-sm"><span class="mb-1 block text-muted-foreground">Event slug</span>
                    <input data-ev="slug" type="text" class="${INPUT}" placeholder="summer-meetup"></label>
                  <label class="block text-sm"><span class="mb-1 block text-muted-foreground">Title</span>
                    <input data-ev="title" type="text" class="${INPUT}"></label>
                  <label class="block text-sm lg:col-span-2"><span class="mb-1 block text-muted-foreground">Date label</span>
                    <input data-ev="date_label" type="text" class="${INPUT}" placeholder="Saturday, July 12 · 6pm"></label>
                  <label class="block text-sm lg:col-span-2"><span class="mb-1 block text-muted-foreground">Starts at (ISO, optional)</span>
                    <input data-ev="starts_at" type="text" class="${INPUT}" placeholder="2026-07-12T08:00:00+00:00"></label>
                </div>
                <button type="button" data-event-save class="${BTN_PRI} mt-3">Save event → frontend</button>
              </div>
            </section>
          </div>

          <p data-status class="min-h-[1rem] text-xs text-muted-foreground"></p>
        </div>`;

      this.querySelector('[data-tab="rsvp"]').addEventListener('click', () => this.setTab('rsvp'));
      this.querySelector('[data-tab="chapters"]').addEventListener('click', () => this.setTab('chapters'));
      this.querySelector('[data-refresh]').addEventListener('click', () => this.load().catch((e) => this.status(e.message)));
      this.querySelector('[data-export]').addEventListener('click', () => this.exportCsv().catch((e) => this.status(e.message)));
      this.querySelector('[data-close]').addEventListener('click', () => this.setOpen(false).catch((e) => this.status(e.message)));
      this.querySelector('[data-open]').addEventListener('click', () => this.setOpen(true).catch((e) => this.status(e.message)));
      this.querySelector('[data-chapter-save]').addEventListener('click', () => this.saveChapter().catch((e) => this.status(e.message)));
      this.querySelector('[data-chapter-spawn]').addEventListener('click', () => this.spawnChapter().catch((e) => this.status(e.message)));
      this.querySelector('[data-chapter-new]').addEventListener('click', () => this.newChapter());
      this.querySelector('[data-event-save]').addEventListener('click', () => this.saveEvent().catch((e) => this.status(e.message)));
      const freqEl = this.querySelector('[data-f="recurrence.frequency"]');
      if (freqEl) freqEl.addEventListener('change', () => this.syncRecurrenceFields());
      this.load().catch((e) => this.status(e.message));
    }

    status(msg) {
      const el = this.querySelector('[data-status]');
      if (el) el.textContent = msg || '';
    }

    setTab(tab) {
      this._tab = tab;
      const rsvp = this.querySelector('[data-panel-rsvp]');
      const chapters = this.querySelector('[data-panel-chapters]');
      const btnRsvp = this.querySelector('[data-tab="rsvp"]');
      const btnCh = this.querySelector('[data-tab="chapters"]');
      if (rsvp) rsvp.classList.toggle('hidden', tab !== 'rsvp');
      if (rsvp) rsvp.classList.toggle('grid', tab === 'rsvp');
      if (chapters) chapters.classList.toggle('hidden', tab !== 'chapters');
      if (chapters) chapters.classList.toggle('grid', tab === 'chapters');
      if (btnRsvp) {
        btnRsvp.className = tab === 'rsvp' ? BTN_PRI : BTN;
      }
      if (btnCh) {
        btnCh.className = tab === 'chapters' ? BTN_PRI : BTN;
      }
      if (tab === 'chapters') {
        this.loadChapters().catch((e) => this.status(e.message));
      }
    }

    renderStats(stats) {
      const el = this.querySelector('[data-stats]');
      if (!el) return;
      el.innerHTML = `
        <span><strong>${esc(stats.events || 0)}</strong> events</span>
        <span><strong>${esc(stats.chapters || 0)}</strong> chapters</span>
        <span><strong>${esc(stats.open || 0)}</strong> open</span>
        <span><strong>${esc(stats.rsvps || 0)}</strong> RSVPs</span>
        <span><strong>${esc(stats.headcount || 0)}</strong> headcount</span>`;
    }

    renderEvents(events) {
      const el = this.querySelector('[data-events]');
      if (!el) return;
      if (!events.length) {
        el.innerHTML = '<li class="text-muted-foreground">No events in <code>user/data/eventz/events/</code>.</li>';
        return;
      }
      el.innerHTML = events.map((ev) => {
        const slug = ev.slug || '';
        const open = ev.rsvp_open !== false;
        const rsvp = ev.rsvp || {};
        const active = slug === this._slug ? ' border-primary bg-primary/10' : ' border-border';
        const chapter = ev.chapter ? ` · ${esc(ev.chapter)}` : '';
        return `<li><button type="button" data-slug="${esc(slug)}" class="w-full rounded-md border${active} px-3 py-2 text-left hover:bg-accent/40">
          <div class="font-semibold">${esc(ev.title || slug)}</div>
          <div class="mt-1 text-xs text-muted-foreground">${esc(ev.city || '')}${chapter} · ${esc(ev.date_label || 'Date TBA')}</div>
          <div class="mt-1 text-xs">${esc(String(rsvp.rsvps || 0))} RSVPs · ${open ? '<span class="text-emerald-400">open</span>' : '<span class="text-rose-400">closed</span>'}</div>
        </button></li>`;
      }).join('');
      el.querySelectorAll('[data-slug]').forEach((btn) => {
        btn.addEventListener('click', () => {
          this._slug = btn.getAttribute('data-slug') || '';
          this.loadRsvps().catch((e) => this.status(e.message));
          this.renderEvents(events);
        });
      });
    }

    renderRsvps(payload) {
      const el = this.querySelector('[data-rsvps]');
      if (!el) return;
      if (!this._slug) {
        el.innerHTML = '<li class="text-muted-foreground">Select an event.</li>';
        return;
      }
      const entries = (payload && payload.entries) || [];
      if (!entries.length) {
        el.innerHTML = `<li class="text-muted-foreground">No RSVPs yet for <code>${esc(this._slug)}</code>.</li>`;
        return;
      }
      el.innerHTML = entries.map((entry) => `
        <li class="rounded-md border border-border bg-background/40 px-3 py-2">
          <div class="font-semibold">${esc(entry.name || '')} · ${esc(entry.email || '')}</div>
          <div class="mt-1 text-xs text-muted-foreground">Guests: ${esc(String(entry.guests || 1))}${entry.note ? ' · ' + esc(entry.note) : ''}</div>
        </li>`).join('');
    }

    field(name) {
      return this.querySelector(`[data-f="${name}"]`);
    }

    fillChapterForm(chapter) {
      this._chapter = chapter || {};
      const c = this._chapter;
      const rec = c.recurrence || {};
      const map = {
        title: c.title,
        slug: c.slug,
        series: c.series,
        description: c.description,
        city: c.city,
        venue: c.venue,
        default_chat_group: c.default_chat_group,
        capacity: c.capacity,
        'recurrence.frequency': rec.frequency || 'monthly',
        'recurrence.weekday': rec.weekday || 'thursday',
        'recurrence.day_of_month': rec.day_of_month ?? 15,
        'recurrence.time': rec.time || '18:00',
        'recurrence.timezone': rec.timezone || 'UTC',
      };
      Object.keys(map).forEach((k) => {
        const el = this.field(k);
        if (el) el.value = map[k] != null ? String(map[k]) : '';
      });
      const en = this.field('recurrence.enabled');
      if (en) en.checked = !!rec.enabled;
      this.syncRecurrenceFields();
    }

    syncRecurrenceFields() {
      const freq = (this.field('recurrence.frequency')?.value || 'monthly').trim();
      const weekly = freq === 'weekly';
      const domWrap = this.querySelector('[data-dom-wrap]');
      const weekdayWrap = this.querySelector('[data-weekday-wrap]');
      if (domWrap) domWrap.classList.toggle('hidden', weekly);
      if (weekdayWrap) weekdayWrap.classList.toggle('hidden', !weekly);
    }

    collectChapterForm() {
      const recEnabled = this.field('recurrence.enabled');
      return {
        slug: (this.field('slug')?.value || '').trim(),
        title: (this.field('title')?.value || '').trim(),
        series: (this.field('series')?.value || '').trim(),
        description: (this.field('description')?.value || '').trim(),
        city: (this.field('city')?.value || '').trim(),
        venue: (this.field('venue')?.value || '').trim(),
        default_chat_group: (this.field('default_chat_group')?.value || '').trim(),
        capacity: Number(this.field('capacity')?.value || 40),
        recurrence: {
          enabled: !!(recEnabled && recEnabled.checked),
          frequency: (this.field('recurrence.frequency')?.value || 'monthly').trim(),
          interval: 1,
          weekday: (this.field('recurrence.weekday')?.value || 'thursday').trim(),
          day_of_month: Number(this.field('recurrence.day_of_month')?.value || 15),
          time: (this.field('recurrence.time')?.value || '18:00').trim(),
          timezone: (this.field('recurrence.timezone')?.value || 'UTC').trim(),
          duration_hours: 2,
        },
      };
    }

    renderChapterList(chapters) {
      const el = this.querySelector('[data-chapters]');
      if (!el) return;
      if (!chapters.length) {
        el.innerHTML = '<li class="text-muted-foreground">No chapters — add one under <code>user/data/eventz/chapters/</code>.</li>';
        return;
      }
      el.innerHTML = chapters.map((ch) => {
        const slug = ch.slug || '';
        const active = slug === this._chapterSlug ? ' border-primary bg-primary/10' : ' border-border';
        const rec = ch.recurrence || {};
        return `<li><button type="button" data-ch="${esc(slug)}" class="w-full rounded-md border${active} px-3 py-2 text-left hover:bg-accent/40">
          <div class="font-semibold">${esc(ch.title || slug)}</div>
          <div class="mt-1 text-xs text-muted-foreground">${esc(ch.city || '')} · ${esc(String(ch.event_count || 0))} events</div>
          <div class="mt-1 text-xs">${rec.enabled ? esc(rec.frequency || 'monthly') + ' repeat' : 'manual dates'}</div>
        </button></li>`;
      }).join('');
      el.querySelectorAll('[data-ch]').forEach((btn) => {
        btn.addEventListener('click', () => {
          this._chapterSlug = btn.getAttribute('data-ch') || '';
          this.loadChapterDetail().catch((e) => this.status(e.message));
          this.renderChapterList(chapters);
        });
      });
    }

    renderChapterEvents(events) {
      const el = this.querySelector('[data-chapter-events]');
      if (!el) return;
      if (!events.length) {
        el.innerHTML = '<li class="text-muted-foreground">No occurrences yet — use Spawn next occurrence.</li>';
        return;
      }
      el.innerHTML = events.map((ev) => `
        <li class="rounded-md border border-border bg-background/40 px-3 py-2">
          <div class="font-semibold">${esc(ev.title || ev.slug)}</div>
          <div class="mt-1 text-xs text-muted-foreground"><code>${esc(ev.slug)}</code> · ${esc(ev.date_label || ev.starts_at || 'TBA')}</div>
        </li>`).join('');
    }

    async loadRsvps() {
      if (!this._slug) {
        this.renderRsvps(null);
        return;
      }
      const data = await api('/eventz/admin/rsvps/' + encodeURIComponent(this._slug));
      this.renderRsvps(data);
    }

    async load() {
      this.status('Loading…');
      const stats = await api('/eventz/admin/stats');
      this.renderStats(stats);
      const list = await api('/eventz/admin/events');
      const events = list.events || [];
      if (!this._slug && events[0]) this._slug = events[0].slug || '';
      this.renderEvents(events);
      await this.loadRsvps();
      this.status('');
    }

    async loadChapters() {
      const list = await api('/eventz/admin/chapters');
      const chapters = list.chapters || [];
      if (!this._chapterSlug && chapters[0]) this._chapterSlug = chapters[0].slug || '';
      this.renderChapterList(chapters);
      if (this._chapterSlug) await this.loadChapterDetail();
    }

    async loadChapterDetail() {
      if (!this._chapterSlug) return;
      const data = await api('/eventz/admin/chapters/' + encodeURIComponent(this._chapterSlug));
      this.fillChapterForm(data.chapter || {});
      this.renderChapterEvents(data.events || []);
    }

    async saveChapter() {
      this.status('Saving chapter…');
      const payload = this.collectChapterForm();
      const result = await api('/eventz/admin/chapters', { method: 'POST', body: JSON.stringify(payload) });
      this._chapterSlug = payload.slug;
      await this.load();
      await this.loadChapters();
      const spawned = (result.spawn && result.spawn.created) || [];
      if (spawned.length) {
        this.status(`Chapter saved + first event created (${spawned[0].slug}). Hard-refresh /eventz.`);
      } else {
        this.status('Chapter saved. Use Spawn if you need another occurrence.');
      }
    }

    async spawnChapter() {
      const slug = (this.field('slug')?.value || this._chapterSlug || '').trim();
      if (!slug) return;
      this.status('Spawning next occurrence…');
      const result = await api('/eventz/admin/chapters/' + encodeURIComponent(slug) + '/spawn', {
        method: 'POST',
        body: JSON.stringify({ count: 1 }),
      });
      await this.load();
      await this.loadChapterDetail();
      const n = (result.created || []).length;
      this.status(n ? `Created ${n} occurrence(s).` : 'Nothing spawned (maybe slug exists or recurrence off).');
    }

    evField(key) {
      return this.querySelector(`[data-ev="${key}"]`);
    }

    async saveEvent() {
      const chapterSlug = (this.field('slug')?.value || this._chapterSlug || '').trim();
      const slug = (this.evField('slug')?.value || '').trim();
      const title = (this.evField('title')?.value || '').trim();
      if (!slug || !title) {
        throw new Error('Event slug and title required.');
      }
      const series = (this.field('series')?.value || '').trim();
      this.status('Saving event…');
      const saved = await api('/eventz/admin/event', {
        method: 'POST',
        body: JSON.stringify({
          slug,
          title,
          chapter: chapterSlug,
          series,
          date_label: (this.evField('date_label')?.value || '').trim(),
          starts_at: (this.evField('starts_at')?.value || '').trim(),
          rsvp_open: true,
          city: (this.field('city')?.value || '').trim(),
          venue: (this.field('venue')?.value || '').trim(),
          chat_group: (this.field('default_chat_group')?.value || '').trim(),
          wire: true,
        }),
      });
      await this.load();
      await this.loadChapterDetail();
      const mm = saved?.wire?.messenger;
      let msg = 'Event saved — check /eventz or /meetup (hard refresh).';
      if (mm?.ok && mm.group) {
        msg += ` Messenger group “${mm.group}” ${mm.action === 'created' ? 'created' : 'ready'}.`;
      } else if (mm && !mm.ok && mm.reason) {
        msg += ` Messenger wire: ${mm.reason}.`;
      }
      this.status(msg);
    }

    newChapter() {
      this._chapterSlug = '';
      this.fillChapterForm({
        slug: '',
        title: '',
        series: '',
        recurrence: { enabled: true, frequency: 'monthly', day_of_month: 15, time: '18:00', timezone: 'UTC' },
      });
      this.renderChapterEvents([]);
      this.status('New chapter — fill slug + title, then Save.');
    }

    async exportCsv() {
      if (!this._slug) return;
      const data = await api('/eventz/admin/rsvps/' + encodeURIComponent(this._slug) + '/csv');
      if (!data.csv) return;
      const blob = new Blob([data.csv], { type: 'text/csv;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = data.filename || this._slug + '-rsvps.csv';
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
      this.status('CSV exported.');
    }

    async setOpen(open) {
      if (!this._slug) return;
      await api('/eventz/admin/event/' + encodeURIComponent(this._slug) + '/rsvp-open', {
        method: 'POST',
        body: JSON.stringify({ open }),
      });
      await this.load();
      this.status(open ? 'RSVP reopened.' : 'RSVP closed.');
    }
  }

  if (!customElements.get(TAG)) customElements.define(TAG, EventzAdminPage);
})();
