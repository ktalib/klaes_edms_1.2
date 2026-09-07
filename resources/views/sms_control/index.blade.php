@extends('layouts.app')

@section('page-title', 'SMS Control Centre')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header')

  <div
    x-data="smsControl()"
    x-init="load()"
    x-cloak
    class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6"
  >
    <div>
      <h1 class="text-2xl font-semibold text-slate-800">SMS Control Centre</h1>
      <p class="text-sm text-slate-500 mt-1">
        Switch each message on or off, edit its wording, and see what it costs before you enable it.
      </p>
    </div>

    {{--
      Gateway strip. This is deliberately the first thing on the page: a missing
      credential, an empty wallet and a switched-off message all look identical
      from the outside — nothing arrives — so the page says which one it is
      rather than showing toggles that would silently do nothing.
    --}}
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
      <div class="flex flex-wrap items-center gap-6">
        <div>
          <div class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Gateway</div>
          <div class="text-sm text-slate-800 mt-1">Bulk-SMS.ng &middot; promotional route</div>
        </div>
        <div>
          <div class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Sender ID</div>
          <div class="text-sm text-slate-800 mt-1" x-text="state.gateway?.sender || '—'"></div>
        </div>
        <div>
          <div class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Wallet</div>
          <div class="text-sm mt-1"
               :class="state.gateway?.balance ? 'text-slate-800' : 'text-amber-700'"
               x-text="state.gateway?.balance || 'unknown'"></div>
        </div>
        <div>
          <div class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Today</div>
          <div class="text-sm text-slate-800 mt-1" x-text="todaySummary()"></div>
        </div>

        <div class="ml-auto flex items-center gap-3">
          <span class="text-sm font-medium text-slate-700">All messages</span>
          <label class="relative inline-flex cursor-pointer items-center">
            <input type="checkbox" class="peer sr-only"
                   :checked="state.master"
                   @change="saveMaster($event.target.checked)">
            <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white"></div>
          </label>
        </div>
      </div>

      <template x-if="state.gateway && state.gateway.problem">
        <div class="mt-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800"
             x-text="state.gateway.problem"></div>
      </template>

      <template x-if="!state.master">
        <div class="mt-4 rounded-xl bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-600">
          The master switch is off, so nothing is sent no matter how the individual messages below are set.
        </div>
      </template>
    </div>

    <template x-for="(messages, group) in state.groups" :key="group">
      <div class="space-y-4">
        <h2 class="text-sm font-semibold tracking-wide text-slate-500 uppercase" x-text="group"></h2>

        <template x-for="m in messages" :key="m.key">
          <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">

            <div class="flex items-start gap-4">
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <h3 class="text-base font-semibold text-slate-800" x-text="m.label"></h3>
                  <span class="text-[11px] px-2 py-0.5 rounded-full"
                        :class="m.audience === 'officer' ? 'bg-violet-50 text-violet-700' : 'bg-sky-50 text-sky-700'"
                        x-text="m.audience === 'officer' ? 'to staff' : 'to applicant'"></span>
                  {{-- The name the recipient sees the message come from. --}}
                  <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 font-mono"
                        x-text="m.sender_id"></span>
                </div>
                <p class="text-sm text-slate-500 mt-1" x-text="m.recipient"></p>
                <template x-if="m.always_on">
                  <p class="text-xs text-slate-500 mt-1">
                    <span class="font-medium">Always on.</span>
                    This is the code people need in order to sign in, so it cannot be switched off here.
                    Edit the wording freely; to pause phone verification entirely, set
                    <code class="bg-slate-100 px-1 rounded">PHONE_VERIFICATION_ENABLED=false</code>.
                  </p>
                </template>
              </div>

              {{--
                An always-on message (the sign-in code) shows its state but
                cannot be switched off here -- doing so would leave nobody able
                to sign in and turn it back on. The endpoint refuses it too.
              --}}
              <label class="relative inline-flex items-center mt-1"
                     :class="m.always_on ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'"
                     :title="m.always_on ? 'Always on — this is the code people need to sign in' : ''">
                <input type="checkbox" class="peer sr-only"
                       :checked="m.enabled"
                       :disabled="m.always_on === true"
                       @change="m.enabled = $event.target.checked; save(m)">
                <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white"></div>
              </label>
            </div>

            {{-- Staff attendance wording lives in code, so those cards show no editor. --}}
            <template x-if="!m.toggle_only">
              <div class="mt-4 space-y-3">
                <div>
                  <label class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Wording</label>
                  <textarea rows="3"
                            class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 focus:border-blue-400 focus:outline-none"
                            x-model="m.template"
                            @input="repreview(m)"></textarea>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                  <span class="text-xs text-slate-400">Insert:</span>
                  <template x-for="t in m.tokens" :key="t">
                    <button type="button"
                            class="text-[11px] px-2 py-1 rounded-md bg-slate-100 text-slate-600 hover:bg-slate-200"
                            @click="m.template = (m.template || '') + ' [' + t + ']'; repreview(m)"
                            x-text="'[' + t + ']'"></button>
                  </template>
                </div>

                {{--
                  Live preview with the page count beside it. These wordings are
                  fixed by the Ministry and most run past 160 characters, so the
                  page shows what that costs rather than hiding it — an editor who
                  trims a sentence should be able to see the page drop.
                --}}
                <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
                  <div class="text-sm text-slate-700 whitespace-pre-wrap" x-text="m.preview || '—'"></div>
                  <div class="mt-2 text-xs" :class="m.pages > 1 ? 'text-amber-700' : 'text-slate-400'">
                    <span x-text="m.characters"></span> characters &middot;
                    <span x-text="m.pages"></span> <span x-text="m.pages === 1 ? 'page' : 'pages'"></span>
                    <template x-if="m.pages > 1"><span> — this message bills as <span x-text="m.pages"></span> SMS per send</span></template>
                  </div>
                </div>

                {{--
                  Batch wording. Shown only where one message can cover several
                  files -- a commissioning batch for one applicant. [FileNo] then
                  carries a range, so this form stays two pages whether it is
                  about 2 files or 200.
                --}}
                <template x-if="m.has_plural">
                  <div class="space-y-2 border-t border-slate-100 pt-3">
                    <label class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                      Batch wording <span class="normal-case font-normal text-slate-400">— used when one message covers several files</span>
                    </label>
                    <textarea rows="3"
                              class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 focus:border-blue-400 focus:outline-none"
                              x-model="m.plural_template"
                              @input="repreviewPlural(m)"></textarea>
                    <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
                      <div class="text-sm text-slate-700 whitespace-pre-wrap" x-text="m.plural_preview || '—'"></div>
                      <div class="mt-2 text-xs" :class="m.plural_pages > 1 ? 'text-amber-700' : 'text-slate-400'">
                        <span x-text="m.plural_characters"></span> characters &middot;
                        <span x-text="m.plural_pages"></span> <span x-text="m.plural_pages === 1 ? 'page' : 'pages'"></span>
                      </div>
                    </div>
                  </div>
                </template>

                <div class="flex flex-wrap items-center gap-3">
                  <button type="button"
                          class="inline-flex items-center justify-center rounded-full bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700"
                          @click="save(m)">Save</button>

                  <button type="button"
                          class="text-sm text-slate-500 hover:text-slate-700"
                          x-show="!m.is_default"
                          @click="m.template = ''; save(m)">Reset to the shipped wording</button>

                  {{--
                    Sender ID. A fixed two-way choice, never free text: an
                    unregistered sender is accepted and billed by the gateway and
                    then delivered to nobody, which looks exactly like every
                    other delivery failure.
                  --}}
                  <label class="flex items-center gap-2 text-sm text-slate-600">
                    <span>Sent as</span>
                    <select class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            x-model="m.sender_group"
                            @change="save(m)">
                      <template x-for="(id, group) in state.senders" :key="group">
                        <option :value="group" x-text="id"></option>
                      </template>
                    </select>
                  </label>

                  <div class="ml-auto flex items-center gap-2">
                    <input type="text" placeholder="08031234567"
                           class="w-40 rounded-xl border border-slate-200 px-3 py-2 text-sm"
                           x-model="m.testPhone">
                    <button type="button"
                            class="rounded-full border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                            @click="sendTest(m)">Send test</button>
                  </div>
                </div>
              </div>
            </template>

            {{--
              The last few sends. 'skipped' is shown as prominently as 'failed'
              on purpose: "no phone number on file" is the commonest reason a
              message never arrives, and it is not a failure anyone would
              otherwise go looking for.
            --}}
            <template x-if="m.recent && m.recent.length">
              <div class="mt-4 border-t border-slate-100 pt-3">
                <div class="text-xs font-semibold tracking-wide text-slate-400 uppercase mb-2">Recent</div>
                <div class="space-y-1">
                  <template x-for="r in m.recent" :key="r.id">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                      <span class="px-2 py-0.5 rounded-full font-medium"
                            :class="{
                              'bg-emerald-50 text-emerald-700': r.status === 'sent',
                              'bg-rose-50 text-rose-700': r.status === 'failed',
                              'bg-amber-50 text-amber-700': r.status === 'skipped',
                              'bg-slate-100 text-slate-600': r.status === 'pending'
                            }"
                            x-text="r.status"></span>
                      <span class="text-slate-500" x-text="r.at"></span>
                      <span class="text-slate-400" x-text="r.file_number || r.phone || ''"></span>
                      <span class="text-slate-400" x-text="r.reason || ''"></span>
                    </div>
                  </template>
                </div>
              </div>
            </template>

          </div>
        </template>
      </div>
    </template>
  </div>
</div>

<script>
/*
 | Vanilla Alpine, no build step — the layout already loads Alpine 3 and Tailwind
 | 2 from a CDN, so only classes that already appear elsewhere in this app are
 | guaranteed to exist in that build.
 |
 | The preview shown while typing is rendered here, client-side, and is a
 | deliberate approximation: the authoritative render is KlaesSmsDispatcher's,
 | and the page re-reads it from the server after every save. The two agree on
 | the rule that matters — a token nobody supplies is emptied rather than left as
 | a literal "[Foo]" in somebody's inbox.
 */
function smsControl() {
  return {
    state: { master: false, groups: {}, gateway: null, today: {} },

    /*
     | Rendered straight in by Blade rather than read off a data-* attribute.
     |
     | The attribute version used `this.$el`, which in Alpine 3 is the element
     | of the CURRENTLY EVALUATING expression -- the root div under x-init, but
     | the button under @click. So load() worked and every save and test send
     | fetched the string "undefined". This script is inline in the same Blade
     | file, so there is no reason for the indirection at all.
     */
    urls: {
      state: '{{ route('sms-control.api.state') }}',
      updateBase: '{{ url('/sms-control/api/messages') }}',
      test: '{{ route('sms-control.api.test') }}',
    },

    async load() {
      const res = await fetch(this.urls.state, { headers: { 'Accept': 'application/json' } });
      const body = await res.json();

      if (!body.success) { return; }

      // Sample values come from the server so the typing preview matches the one
      // the server renders after a save.
      Object.values(body.data.groups).forEach(list => list.forEach(m => {
        m.testPhone = '';
        m.sample = m.sample || {};
      }));

      this.state = body.data;
    },

    todaySummary() {
      const t = this.state.today || {};
      const parts = Object.keys(t).map(k => t[k] + ' ' + k);
      return parts.length ? parts.join(', ') : 'nothing yet';
    },

    /* A local approximation of the server's substitute(), for live typing. */
    repreview(m) {
      let body = m.template || '';
      body = body.replace(/\[[^\]\[]{1,40}\]/g, '');
      body = body.replace(/[ \t]{2,}/g, ' ').replace(/\s+([.,])/g, '$1').trim();
      m.preview = body;
      m.characters = body.length;
      m.pages = body.length ? Math.max(1, Math.ceil(body.length / 160)) : 0;
    },

    /* Same approximation as repreview(), for the batch box. */
    repreviewPlural(m) {
      let body = m.plural_template || '';
      body = body.replace(/\[[^\]\[]{1,40}\]/g, '');
      body = body.replace(/[ \t]{2,}/g, ' ').replace(/\s+([.,])/g, '$1').trim();
      m.plural_preview = body;
      m.plural_characters = body.length;
      m.plural_pages = body.length ? Math.max(1, Math.ceil(body.length / 160)) : 0;
    },

    async save(m) {
      const res = await fetch(this.urls.updateBase + '/' + m.key, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({
          enabled: m.enabled,
          template: m.template || '',
          plural_template: m.plural_template || '',
          sender: m.sender_group || null,
        }),
      });

      const body = await res.json();

      if (!body.success) {
        Swal.fire({ icon: 'error', title: 'Not saved', text: body.message || 'Could not save this message.' });
        // Re-read, so the page never shows a switch in a state the server rejected.
        await this.load();
        return;
      }

      // Re-read rather than trusting the local copy: the server renders the
      // authoritative preview and recomputes the page count.
      await this.load();
    },

    async saveMaster(enabled) {
      await fetch(this.urls.updateBase + '/master', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({ enabled: enabled, template: '' }),
      });

      await this.load();
    },

    async sendTest(m) {
      if (!m.testPhone) {
        Swal.fire({ icon: 'info', title: 'Which number?', text: 'Type a number to send the test to.' });
        return;
      }

      const res = await fetch(this.urls.test, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({ key: m.key, phone: m.testPhone }),
      });

      const body = await res.json();

      Swal.fire({
        icon: body.success ? 'success' : 'error',
        title: body.success ? 'Sent' : 'Not sent',
        text: body.message,
      });

      await this.load();
    },
  };
}
</script>
@endsection
