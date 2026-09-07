<?php

namespace App\Http\Controllers;

use App\Models\SmsDispatchLog;
use App\Models\SmsSetting;
use App\Services\BulkSmsNgService;
use App\Services\Sms\ApplicantPhoneResolver;
use App\Services\Sms\KlaesSmsDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The SMS Control Centre.
 *
 * Every transactional SMS switch used to be an .env key, which meant the Ministry
 * could not turn a message on without someone editing a file on the server and
 * restarting it -- and the next code upload silently lost the setting, because
 * .env is gitignored and does not travel. This page moves the switches, and the
 * wordings, into the database.
 *
 * It also answers the questions that decide whether switching one on is a good
 * idea: what the message will cost per send, what the wallet holds, and what the
 * last few sends actually did.
 */
class SmsControlController extends Controller
{
    public function __construct(private KlaesSmsDispatcher $dispatcher)
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authoriseControl($request);

        return view('sms_control.index', [
            'PageTitle' => 'SMS Control Centre',
            'PageDescription' => 'Switch each transactional message on or off, edit its wording, and see what it costs.',
        ]);
    }

    /**
     * Everything the page renders, in one call.
     *
     * Built server-side rather than in the blade so the page can refresh itself
     * after a save without a reload, and so the preview the officer reads is
     * rendered by exactly the code that will render the real message.
     */
    public function state(Request $request): JsonResponse
    {
        $this->authoriseControl($request);

        $groups = [];

        foreach (SmsSetting::catalogue() as $key => $definition) {
            $template = SmsSetting::templateFor($key);
            $preview = $this->dispatcher->preview($key);

            $groups[$definition['group'] ?? 'Other'][] = [
                'key' => $key,
                'label' => $definition['label'] ?? $key,
                'audience' => $definition['audience'] ?? 'applicant',
                'recipient' => $definition['recipient'] ?? null,
                'enabled' => SmsSetting::isEnabled($key),
                'template' => $template,
                // A message whose wording lives in code (staff attendance) can be
                // switched but not edited, so the page hides its editor.
                'toggle_only' => (bool) ($definition['toggle_only'] ?? false),
                // Shown with its switch locked. See SmsSetting::KEY_PHONE_OTP.
                'always_on' => SmsSetting::isAlwaysOn($key),
                // Which name this message goes out under, and the choices.
                'sender_group' => SmsSetting::senderGroupFor($key),
                'sender_id' => SmsSetting::senderFor($key),
                'tokens' => array_values((array) ($definition['tokens'] ?? [])),
                'preview' => $preview['body'],
                'characters' => $preview['characters'],
                'pages' => $preview['pages'],
                'is_default' => $template === ($definition['template'] ?? null),
                'recent' => $this->recentFor($key),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'master' => SmsSetting::isEnabled(SmsSetting::KEY_MASTER),
                'senders' => SmsSetting::senderOptions(),
                'gateway' => $this->gatewayStatus(),
                'groups' => $groups,
                'today' => $this->todayCounts(),
            ],
        ]);
    }

    /**
     * Save one message's switch and, optionally, its wording.
     *
     * A blank template CLEARS the override and returns the message to the wording
     * shipped in config/klaes_sms.php -- which is what you want when a later code
     * upload corrects the Ministry's wording and a stale override would keep
     * hiding it.
     */
    public function updateMessage(Request $request, string $key): JsonResponse
    {
        $this->authoriseControl($request);

        if ($key !== SmsSetting::KEY_MASTER && !SmsSetting::isKnownKey($key)) {
            return response()->json(['success' => false, 'message' => 'Unknown message.'], 404);
        }

        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'template' => 'nullable|string|max:1000',
            // The sender GROUP, not a free-text ID: allowing anything here would
            // let somebody send under a name the vendor has not registered, and
            // an unregistered sender is accepted, billed and never delivered.
            'sender' => 'nullable|string|in:' . implode(',', array_keys(SmsSetting::senderOptions())),
        ]);

        /*
         | An always-on message may have its wording edited but never its switch.
         | Enforced here as well as in the page, because the page is only a
         | courtesy -- this endpoint is reachable directly, and switching off the
         | sign-in code would lock everybody out of the system that turns it back
         | on. The gate itself is paused with PHONE_VERIFICATION_ENABLED=false.
         */
        if (SmsSetting::isAlwaysOn($key) && !$validated['enabled']) {
            return response()->json([
                'success' => false,
                'message' => 'This message cannot be switched off — it is the code people need in order to sign in. '
                    . 'To pause phone verification entirely, set PHONE_VERIFICATION_ENABLED=false.',
            ], 422);
        }

        $template = trim((string) ($validated['template'] ?? ''));

        if ($template !== '') {
            $unknown = $this->unknownTokens($key, $template);

            if (!empty($unknown)) {
                /*
                 | A token nothing supplies renders as a literal "[Foo]" in
                 | somebody's inbox, so this is refused rather than silently
                 | stripped -- the officer needs to know they mistyped it.
                 */
                return response()->json([
                    'success' => false,
                    'message' => 'This message does not provide ' . implode(', ', $unknown)
                        . '. Available placeholders: ' . implode(', ', $this->tokensFor($key)) . '.',
                ], 422);
            }
        }

        SmsSetting::updateOrCreate(
            ['message_key' => $key],
            [
                'enabled' => (bool) $validated['enabled'],
                'template' => $template !== '' ? $template : null,
                // Null returns the message to the group it ships under, the
                // same way a blank template returns it to the shipped wording.
                'sender' => $validated['sender'] ?? null,
                'updated_by' => Auth::user()->name ?? null,
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Send one message to a nominated number, so it can be read on a handset
     * before it is switched on for the public.
     *
     * Rendered with the message's sample values, and sent even when the message
     * is switched off -- testing before enabling is the whole point.
     */
    public function sendTest(Request $request): JsonResponse
    {
        $this->authoriseControl($request);

        $validated = $request->validate([
            'key' => 'required|string',
            'phone' => 'required|string|max:30',
        ]);

        $key = $validated['key'];

        if (!SmsSetting::isKnownKey($key)) {
            return response()->json(['success' => false, 'message' => 'Unknown message.'], 404);
        }

        $normalised = BulkSmsNgService::normalizeNumber($validated['phone']);

        if ($normalised === null) {
            return response()->json([
                'success' => false,
                'message' => '"' . $validated['phone'] . '" is not a usable Nigerian mobile number.',
            ], 422);
        }

        $body = $this->dispatcher->preview($key)['body'];

        if ($body === null) {
            return response()->json([
                'success' => false,
                'message' => 'This message has no wording to send. Its text lives in code, not here.',
            ], 422);
        }

        // Straight to the gateway, not through the dispatcher: a test must work
        // while the message is still switched off, and must not claim the event's
        // dedupe slot or be counted as a real send.
        $gateway = new BulkSmsNgService();
        $sent = $gateway->send($normalised, $body);

        SmsDispatchLog::create([
            'message_key' => $key,
            'phone' => $normalised,
            'message' => $body,
            'status' => $sent ? SmsDispatchLog::STATUS_SENT : SmsDispatchLog::STATUS_FAILED,
            'gateway_code' => $gateway->lastStatusCode(),
            'failure_reason' => $sent ? null : $gateway->lastFailureReason(),
            'attempts' => 1,
            'subject_type' => 'test',
            // No dedupe key: an officer may quite reasonably send the same test
            // twice, and the index is filtered so NULLs do not collide.
            'dedupe_key' => null,
            'event_at' => now(config('klaes_sms.timezone', 'Africa/Lagos'))->toDateTimeString(),
            'created_by' => Auth::user()->name ?? null,
        ]);

        return response()->json([
            'success' => $sent,
            'message' => $sent
                ? 'Accepted by the gateway. Accepted is not delivered: a DND-blocked handset receives nothing, and code 609 means it is held until 08:30.'
                : ($gateway->lastFailureReason() ?: 'The gateway refused the message.'),
            'code' => $gateway->lastStatusCode(),
        ]);
    }

    /** The dispatch log, newest first, optionally narrowed to one message. */
    public function log(Request $request): JsonResponse
    {
        $this->authoriseControl($request);

        $query = SmsDispatchLog::query()->orderByDesc('id');

        if ($request->filled('key')) {
            $query->where('message_key', $request->input('key'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('file_number')) {
            $query->where('file_number', 'like', '%' . $request->input('file_number') . '%');
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(50),
        ]);
    }

    /**
     * Which number a file would be texted on -- the endpoint every capture form
     * calls to prefill its phone field.
     *
     * Returns the shared-number verdict alongside the number, because a number on
     * a thousand files is an indexing clerk and the officer needs to be told that
     * before they let a message go to it.
     */
    public function resolvePhone(Request $request, ApplicantPhoneResolver $phones): JsonResponse
    {
        $validated = $request->validate([
            'file_number' => 'required|string|max:100',
        ]);

        $verdict = $phones->forFileNumber($validated['file_number']);

        return response()->json([
            'success' => true,
            'data' => [
                // The storage form (0803...) is what the officer expects to read
                // and what the form field should hold; the gateway normalises it
                // again on the way out.
                'phone' => $verdict['raw'] ?: $verdict['phone'],
                'source' => $verdict['source'],
                'shared' => $verdict['shared'],
                'shared_count' => $verdict['shared_count'],
                'warning' => $verdict['shared']
                    ? 'This number is on file against ' . $verdict['shared_count']
                        . ' files, so it may belong to an agent or an indexing officer rather than this applicant. Confirm it before it is used.'
                    : null,
            ],
        ]);
    }

    /**
     * Only staff who administer the system may change what the Ministry sends to
     * the public. 'System Settings' is the existing role that already gates the
     * other system-administration screens.
     *
     * Roles here are the app's own -- users.assign_role resolved through
     * User::assignedRoleNames(), with a super-admin short circuit -- not Spatie's
     * trait, which this User model does not use.
     */
    private function authoriseControl(Request $request): void
    {
        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        $roles = method_exists($user, 'assignedRoleNames')
            ? array_map('strtolower', (array) $user->assignedRoleNames())
            : [];

        $allowed = array_intersect($roles, [
            'system settings',
            'super admin',
            // The seeded role name really is misspelled in this database.
            'supper admin',
        ]);

        if (empty($allowed) && !$user->can('manage general settings')) {
            abort(403, 'You do not have permission to manage SMS settings.');
        }
    }

    /** The last few sends for one message, for its card. */
    private function recentFor(string $key): array
    {
        try {
            return SmsDispatchLog::where('message_key', $key)
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'status', 'phone', 'file_number', 'gateway_code', 'failure_reason', 'created_at'])
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'status' => $row->status,
                    'phone' => $row->phone,
                    'file_number' => $row->file_number,
                    'code' => $row->gateway_code,
                    'reason' => $row->failure_reason,
                    'at' => optional($row->created_at)->format('d/m/Y H:i'),
                ])
                ->all();
        } catch (\Throwable $e) {
            // The table may not be deployed yet. An empty list is the honest
            // answer, and reportTables() in the doctor command says why.
            return [];
        }
    }

    /**
     * Whether this server can send at all, in plain language.
     *
     * The three ways it cannot are indistinguishable from the outside -- nothing
     * arrives -- so the page names which one it is rather than showing a toggle
     * that will silently do nothing.
     */
    private function gatewayStatus(): array
    {
        $email = config('services.bulk_sms_ng.email');
        $password = config('services.bulk_sms_ng.password');

        if (!$email || !$password) {
            return [
                'ready' => false,
                'sender' => config('services.bulk_sms_ng.sender'),
                'balance' => null,
                'problem' => 'BULK_SMS_NG_EMAIL and BULK_SMS_NG_PASSWORD are not set on this server. '
                    . '.env is not carried across by a code upload, so they have to be set again after a deployment.',
            ];
        }

        $balance = null;

        try {
            $balance = (new BulkSmsNgService())->balance();
        } catch (\Throwable $e) {
            $balance = null;
        }

        return [
            'ready' => true,
            'sender' => config('services.bulk_sms_ng.sender'),
            'account' => $email,
            'balance' => $balance,
            'problem' => $balance === null
                ? 'The wallet balance could not be read. The credentials may be wrong, or this server may not be able to reach the gateway.'
                : null,
        ];
    }

    /** Today's traffic, by status. */
    private function todayCounts(): array
    {
        try {
            return SmsDispatchLog::query()
                ->whereDate('created_at', now()->toDateString())
                ->selectRaw('status, COUNT(*) AS total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Placeholders this message supplies, as they appear in a template. */
    private function tokensFor(string $key): array
    {
        return array_map(
            fn ($token) => '[' . $token . ']',
            (array) (SmsSetting::definition($key)['tokens'] ?? [])
        );
    }

    /** Placeholders used in a template that this message does not supply. */
    private function unknownTokens(string $key, string $template): array
    {
        preg_match_all('/\[[^\]\[]{1,40}\]/', $template, $matches);

        return array_values(array_unique(array_diff($matches[0] ?? [], $this->tokensFor($key))));
    }
}
