<?php

namespace App\Http\Controllers\Laas;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\LandUse;
use App\Models\Laas\LaasApplication;
use App\Models\Laas\LaasDocument;
use App\Models\Lga;
use App\Services\Laas\LaasNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The applicant's own view of their applications — spec steps (a) and (b), plus
 * the status page they return to for every later stage.
 */
class LaasApplicationController extends Controller
{
    /** Uploads the applicant may attach, and whether one is required to submit. */
    public const DOC_TYPES = [
        'id_card'  => ['label' => 'Means of identification', 'required' => true],
        'passport' => ['label' => 'Passport photograph',     'required' => true],
        'sketch'   => ['label' => 'Sketch / site plan',      'required' => false],
        'other'    => ['label' => 'Supporting document',     'required' => false],
    ];

    public function __construct(private LaasNotificationService $notifications)
    {
    }

    /**
     * The application form (spec a). Resumes the applicant's open draft if they
     * left one behind.
     */
    public function form()
    {
        $applicant = Auth::guard('laas')->user();

        $draft = LaasApplication::where('laas_applicant_id', $applicant->id)
            ->where('stage', LaasApplication::STAGE_DRAFT)
            ->orderByDesc('id')
            ->first();

        return view('laas.apply', [
            'draft'     => $draft,
            'applicant' => $applicant,
            'landUses'  => LandUse::orderBy('landuse')->get(['id', 'landuse']),
            'lgas'      => Lga::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'docTypes'  => self::DOC_TYPES,
        ]);
    }

    /**
     * Autosave. Accepts a partial form and never validates hard — losing a
     * half-typed application to a validation error is exactly what this exists
     * to prevent. The real validation happens in store().
     */
    public function saveDraft(Request $request): JsonResponse
    {
        $applicant = Auth::guard('laas')->user();

        $draft = LaasApplication::where('laas_applicant_id', $applicant->id)
            ->where('stage', LaasApplication::STAGE_DRAFT)
            ->orderByDesc('id')
            ->first();

        if (!$draft) {
            $draft = new LaasApplication([
                'reference_no'      => LaasApplication::nextReference(),
                'laas_applicant_id' => $applicant->id,
                'stage'             => LaasApplication::STAGE_DRAFT,
            ]);
        }

        $draft->fill($this->draftableInput($request));
        $draft->save();

        return response()->json([
            'success'      => true,
            'id'           => $draft->id,
            'reference_no' => $draft->reference_no,
            'saved_at'     => now()->format('H:i:s'),
        ]);
    }

    /**
     * Submit (spec b): freeze the applicant snapshot, stamp the reference, and
     * fire the "received, processing has started" text.
     */
    public function store(Request $request)
    {
        $applicant = Auth::guard('laas')->user();

        $data = $request->validate([
            'applicant_type'          => ['required', 'string', 'max:50'],
            'applicant_name'          => ['required', 'string', 'max:200'],
            'applicant_phone'         => ['required', 'string', 'max:30'],
            'applicant_email'         => ['nullable', 'email', 'max:150'],
            'applicant_address'       => ['required', 'string', 'max:500'],
            'applicant_nin'           => ['nullable', 'string', 'max:30'],
            'land_use'                => ['required', 'string', 'max:50'],
            'purpose_id'              => ['nullable', 'integer'],
            'lga_id'                  => ['required', 'integer'],
            'district_id'             => ['nullable', 'integer'],
            'location'                => ['required', 'string', 'max:500'],
            'plot_no'                 => ['nullable', 'string', 'max:100'],
            'approx_size'             => ['nullable', 'string', 'max:100'],
            'existing_allocation_ref' => ['nullable', 'string', 'max:100'],
            'applicant_remarks'       => ['nullable', 'string', 'max:2000'],
        ]);

        $application = DB::connection('sqlsrv')->transaction(function () use ($applicant, $data) {
            $application = LaasApplication::where('laas_applicant_id', $applicant->id)
                ->where('stage', LaasApplication::STAGE_DRAFT)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$application) {
                $application = new LaasApplication([
                    'reference_no'      => LaasApplication::nextReference(),
                    'laas_applicant_id' => $applicant->id,
                ]);
            }

            $application->fill($data);
            $application->stage        = LaasApplication::STAGE_SUBMITTED;
            $application->submitted_at = now();
            $application->save();

            return $application;
        });

        // Outside the transaction on purpose: the gateway is a network call and
        // must not hold a database lock open, nor roll a saved application back
        // if it is slow or down.
        $this->notifications->record($application, LaasApplication::STAGE_SUBMITTED, [
            'title'      => 'Application received',
            'body'       => 'Your application has been received and processing has started.',
            'actor_type' => 'applicant',
            'actor_id'   => $applicant->id,
            'actor_name' => $applicant->name,
        ]);

        return redirect()->route('laas.application.show', $application->reference_no)
            ->with('status', "Application {$application->reference_no} submitted. You will be updated by SMS at each stage.");
    }

    /** The status page: stage tracker, event timeline, documents. */
    public function show(string $reference)
    {
        $application = $this->findOwned($reference);

        return view('laas.application', [
            'application' => $application,
            'events'      => $application->events()
                                ->where('visible_to_applicant', true)
                                ->orderBy('id')
                                ->get(),
            'documents'   => $application->documents()->orderBy('id')->get(),
            'lga'         => $application->lga_id ? Lga::find($application->lga_id) : null,
            'district'    => $application->district_id ? District::find($application->district_id) : null,
            'docTypes'    => self::DOC_TYPES,
        ]);
    }

    public function uploadDocument(Request $request, string $reference)
    {
        $application = $this->findOwned($reference);

        $request->validate([
            'doc_type' => ['required', 'string', 'in:' . implode(',', array_keys(self::DOC_TYPES))],
            'file'     => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $file = $request->file('file');

        // The `local` disk, not `public`: identification documents must not be
        // reachable by guessing a URL under /storage. They come back out only
        // through downloadDocument() below, which checks ownership first.
        $path = $file->store("laas/{$application->id}", 'local');

        LaasDocument::create([
            'laas_application_id' => $application->id,
            'source'              => LaasDocument::SOURCE_APPLICANT,
            'doc_type'            => $request->input('doc_type'),
            'original_name'       => $file->getClientOriginalName(),
            'path'                => $path,
            'mime'                => $file->getClientMimeType(),
            'size'                => $file->getSize(),
            'uploaded_by'         => $application->laas_applicant_id,
            'uploaded_at'         => now(),
        ]);

        return back()->with('status', 'Document uploaded.');
    }

    public function downloadDocument(string $reference, int $documentId)
    {
        $application = $this->findOwned($reference);

        $document = LaasDocument::where('id', $documentId)
            ->where('laas_application_id', $application->id)
            ->firstOrFail();

        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download(
            $document->path,
            $document->original_name ?: basename($document->path)
        );
    }

    /**
     * Resolve a reference to an application the signed-in applicant owns.
     * 404 rather than 403 on someone else's reference — a wrong-owner 403 would
     * confirm that the reference exists.
     */
    private function findOwned(string $reference): LaasApplication
    {
        $applicant = Auth::guard('laas')->user();

        return LaasApplication::where('reference_no', $reference)
            ->where('laas_applicant_id', $applicant->id)
            ->firstOrFail();
    }

    /** The subset of the form an autosave is allowed to write. */
    private function draftableInput(Request $request): array
    {
        return array_filter(
            $request->only([
                'applicant_type', 'applicant_name', 'applicant_phone', 'applicant_email',
                'applicant_address', 'applicant_nin', 'land_use', 'purpose_id', 'lga_id',
                'district_id', 'location', 'plot_no', 'approx_size',
                'existing_allocation_ref', 'applicant_remarks',
            ]),
            fn ($value) => $value !== null && $value !== ''
        );
    }
}
