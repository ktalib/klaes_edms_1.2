<?php

namespace App\Http\Controllers;

use App\Services\ScannerService;
use App\Models\FileIndexing;
use App\Models\PageTyping;
use App\Models\Scanning;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FilearchiveController extends Controller
{
    private function getModuleTheme(string $module): ?array
    {
        $normalized = strtolower(trim($module));
        $themes = [
            'kangis' => [
                'title' => 'KANGIS',
                'bg' => 'bg-gradient-to-r from-yellow-500 via-amber-400 to-yellow-600',
                'icon' => 'map',
                'text_muted' => 'text-yellow-100',
            ],
            'sltr' => [
                'title' => 'SLTR',
                'bg' => 'bg-gradient-to-r from-blue-600 via-blue-500 to-blue-700',
                'icon' => 'book-open',
                'text_muted' => 'text-blue-100',
            ],
            'dciv' => [
                'title' => 'DCIV',
                'bg' => 'bg-gradient-to-r from-purple-600 via-purple-500 to-purple-700',
                'icon' => 'shield',
                'text_muted' => 'text-purple-100',
            ],
            'cadastral' => [
                'title' => 'CADASTRAL',
                'bg' => 'bg-gradient-to-r from-emerald-600 via-emerald-500 to-emerald-700',
                'icon' => 'layers',
                'text_muted' => 'text-emerald-100',
            ],
        ];

        return $themes[$normalized] ?? null;
    }

    public function index(Request $request)
    {
        $module = $request->get('url', '');
        $moduleTheme = $this->getModuleTheme($module);
        
        $PageTitle = $moduleTheme ? $moduleTheme['title'] . ' Digital Archive' : 'File Digital Archive';
        $PageDescription = 'Access and manage digitally archived files';
        $isStorageDemoMode = $this->isStorageDemoMode($module);

        if ($isStorageDemoMode) {
            $completedFiles = $this->buildStorageDemoPaginator($request, $module);

            $stats = [
                'total_archived' => $completedFiles->total(),
                'recently_added' => $completedFiles->total(),
                'total_pages' => $this->countStoragePages($module),
                'storage_used' => $this->calculateStorageFolderSize($module),
            ];

            $popularPageTypes = collect();

            $currentYear = (int) date('Y');
            $yearOptions = collect(range($currentYear, 1981));
            $registryOptions = collect([$this->getRegistryFolderName($module)]);

            return view('filearchive.index', compact(
                'PageTitle',
                'PageDescription',
                'completedFiles',
                'stats',
                'popularPageTypes',
                'module',
                'yearOptions',
                'registryOptions'
            ));
        }

        $completedFiles = FileIndexing::query()
            ->select(['id', 'file_number', 'file_title', 'land_use_type', 'district', 'updated_at', 'plot_number', 'location', 'registry'])
            ->with([
                'fileTracking' => function ($query) {
                    $query->select(['id', 'file_indexing_id', 'status', 'assignment_status']);
                },
                'pagetypings' => function ($query) {
                    $query->select(['id', 'file_indexing_id', 'page_number', 'page_type', 'page_subtype', 'page_code', 'definition_code', 'definition', 'serial_number', 'typed_by', 'cover_type_id', 'created_at', 'updated_at', 'file_path', 'scanning_id'])
                        ->with([
                            'typedBy:id,first_name,last_name',
                            'pageType:id,PageType',
                            'pageSubType:id,PageSubType',
                            'coverType:Id,Name',
                            'scanning:id,file_indexing_id,document_path,display_order,original_filename,document_type,created_at'
                        ])
                        ->orderByRaw('ISNULL(definition, 0) asc')
                        ->orderBy('page_number');
                },
                'scannings' => function ($query) {
                    $query->select(['id', 'file_indexing_id', 'document_path', 'display_order', 'original_filename', 'document_type', 'created_at']);
                },
                // Get the cover type from the first page
                'firstPageTyping' => function ($query) {
                    $query->select(['id', 'file_indexing_id', 'page_number', 'page_code', 'cover_type_id', 'page_type', 'page_subtype', 'file_path', 'scanning_id'])
                        ->with([
                            'coverType:Id,Name',
                            'pageType:id,PageType',
                            'pageSubType:id,PageSubType',
                            'scanning:id,file_indexing_id,document_path,display_order,original_filename,document_type,created_at'
                        ])
                        ->where('page_number', 1)
                        ->orWhere(function ($q) {
                            $q->whereRaw('page_number = (SELECT MIN(page_number) FROM pagetypings pt2 WHERE pt2.file_indexing_id = pagetypings.file_indexing_id)');
                        });
                }
            ])
            ->withCount(['pagetypings', 'scannings'])
            ->orderBy('updated_at', 'desc');

        if (!$isStorageDemoMode) {
            // Default behavior: only files where ALL scans are typed
            $completedFiles->whereHas('pagetypings')
                ->whereHas('scannings')
                ->whereColumn(
                    DB::raw('(SELECT COUNT(*) FROM pagetypings WHERE pagetypings.file_indexing_id = file_indexings.id)'),
                    '>=',
                    DB::raw('(SELECT COUNT(*) FROM scannings WHERE scannings.file_indexing_id = file_indexings.id)')
                );
        }

        // Apply registry/module filter (e.g. ?url=kangis => only KANGIS Registry files)
        if ($module !== '') {
            $completedFiles->where('registry', 'like', '%' . $module . '%');
        }

        // Apply search filters if provided
        if ($request->filled('search')) {
            $search = $request->get('search');
            $field = $request->get('field', 'all');

            $completedFiles->where(function ($query) use ($search, $field) {
                if ($field === 'all' || $field === 'fileName') {
                    $query->orWhere('file_title', 'like', "%{$search}%");
                }
                if ($field === 'all' || $field === 'fileNumber') {
                    $query->orWhere('file_number', 'like', "%{$search}%");
                }
                if ($field === 'all' || $field === 'type') {
                    $query->orWhere('land_use_type', 'like', "%{$search}%");
                }
                if ($field === 'all' || $field === 'page') {
                    $query->orWhereHas('pagetypings', function ($q) use ($search) {
                        $q->where('page_type', 'like', "%{$search}%")
                            ->orWhere('page_subtype', 'like', "%{$search}%");
                    });
                }
            });
        }

        // Apply category filter
        if ($request->filled('category') && $request->get('category') !== 'all') {
            $category = $request->get('category');
            switch ($category) {
                case 'land':
                    $completedFiles->whereIn('land_use_type', ['Residential', 'Commercial', 'Industrial']);
                    break;
                case 'legal':
                    $completedFiles->whereHas('pagetypings', function ($q) {
                        $q->whereIn('page_type', ['Deed', 'Certificate', 'Legal Document']);
                    });
                    break;
                case 'admin':
                    $completedFiles->whereHas('pagetypings', function ($q) {
                        $q->whereIn('page_type', ['Application Form', 'Letter', 'Administrative']);
                    });
                    break;
            }
        }

        // Apply cover type filter
        if ($request->filled('cover_type') && $request->get('cover_type') !== 'all') {
            $coverType = $request->get('cover_type');
            $completedFiles->whereHas('firstPageTyping.coverType', function ($q) use ($coverType) {
                if ($coverType === 'front') {
                    $q->where('Name', 'like', '%front%');
                } elseif ($coverType === 'back') {
                    $q->where('Name', 'like', '%back%');
                }
            });
        }

        // Apply year filter (matches year in file_number, e.g. RES-2026-7)
        if ($request->filled('year') && $request->get('year') !== 'all') {
            $year = $request->get('year');
            $completedFiles->where('file_number', 'like', "%-{$year}-%");
        }

        // Apply registry filter
        if ($request->filled('registry') && $request->get('registry') !== 'all') {
            $completedFiles->where('registry', $request->get('registry'));
        }

        $completedFiles = $completedFiles->paginate(12)->appends($request->query());

        if ($isStorageDemoMode) {
            $completedFiles = $this->enrichStorageMetadataForPaginator($completedFiles, $module);
        }

        $isStorageDemoFallback = false;
        if ($isStorageDemoMode && $completedFiles->total() === 0) {
            $completedFiles = $this->buildStorageDemoPaginator($request, $module);
            $isStorageDemoFallback = true;
        }

        // Calculate statistics (scoped to module when filtered)
        $statsBase = FileIndexing::query();
        if (!$isStorageDemoMode) {
            $statsBase->whereHas('pagetypings')
                ->whereHas('scannings')
                ->whereColumn(
                    DB::raw('(SELECT COUNT(*) FROM pagetypings WHERE pagetypings.file_indexing_id = file_indexings.id)'),
                    '>=',
                    DB::raw('(SELECT COUNT(*) FROM scannings WHERE scannings.file_indexing_id = file_indexings.id)')
                );
        }
        if ($module !== '') {
            $statsBase = $statsBase->where('registry', 'like', '%' . $module . '%');
        }
        $stats = [
            'total_archived' => (clone $statsBase)->count(),
            'recently_added' => (clone $statsBase)
                ->where('updated_at', '>=', now()->subDays(30))->count(),
            'total_pages' => $module !== ''
                ? PageTyping::whereHas('fileIndexing', fn ($q) => $q->where('registry', 'like', '%' . $module . '%'))->count()
                : PageTyping::count(),
            'storage_used' => $this->calculateStorageUsed($module),
        ];

        if ($isStorageDemoFallback) {
            $stats['total_archived'] = $completedFiles->total();
            $stats['recently_added'] = $completedFiles->total();
            $stats['total_pages'] = $completedFiles->total();
            $stats['storage_used'] = $this->calculateStorageFolderSize($module);
        }

        // Get popular page types for filters
        $popularPageTypes = PageTyping::select('page_type', DB::raw('count(*) as count'))
            ->groupBy('page_type')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        // Get available years (from file number) and registries for filter dropdowns
        $currentYear = (int) date('Y');
        $yearOptions = collect(range($currentYear, 1981));

        $registryOptions = FileIndexing::select('registry')
            ->whereNotNull('registry')
            ->where('registry', '!=', '')
            ->distinct()
            ->orderBy('registry')
            ->pluck('registry');

        return view('filearchive.index', compact(
            'PageTitle',
            'PageDescription',
            'completedFiles',
            'stats',
            'popularPageTypes',
            'module',
            'moduleTheme',
            'yearOptions',
            'registryOptions'
        ));
    }

    /**
     * Get file details for modal display
     */
    public function getFileDetails($id)
    {
        $file = FileIndexing::with([
            'pagetypings.typedBy:id,first_name,last_name',
            'pagetypings.coverType',
            'pagetypings.pageType',
            'pagetypings.pageSubType',
            'scannings'
        ])
            ->withCount(['pagetypings', 'scannings'])
            ->findOrFail($id);

        // Transform pagetypings to include proper relationship data
        $transformedPageTypings = [];
        foreach ($file->pagetypings as $pageTyping) {
            $pageSubTypeObj = $pageTyping->pageSubType;
            $transformedSubtype = $pageSubTypeObj ? [
                'id' => $pageSubTypeObj->id,
                'name' => $pageSubTypeObj->PageSubType,
                'PageSubType' => $pageSubTypeObj->PageSubType,
                'code' => $pageSubTypeObj->code
            ] : $pageTyping->page_subtype;

            $transformedPageTypings[] = [
                'id' => $pageTyping->id,
                'page_number' => $pageTyping->page_number,
                'page_type' => $pageTyping->pageType ? [
                    'id' => $pageTyping->pageType->id,
                    'name' => $pageTyping->pageType->PageType,
                    'PageType' => $pageTyping->pageType->PageType,
                    'code' => $pageTyping->pageType->code
                ] : $pageTyping->page_type,
                'page_subtype' => $transformedSubtype,
                'serial_number' => $pageTyping->serial_number,
                'page_code' => $pageTyping->page_code,
                'definition' => $pageTyping->definition,
                'definition_code' => $pageTyping->definition_code,
                'cover_type' => $pageTyping->coverType ? [
                    'id' => $pageTyping->coverType->Id,
                    'name' => $pageTyping->coverType->Name,
                    'code' => $pageTyping->coverType->code
                ] : null,
                'typed_by' => $pageTyping->typedBy ? [
                    'id' => $pageTyping->typedBy->id,
                    'name' => $pageTyping->typedBy->first_name . ' ' . $pageTyping->typedBy->last_name,
                    'first_name' => $pageTyping->typedBy->first_name,
                    'last_name' => $pageTyping->typedBy->last_name
                ] : null,
                'created_at' => $pageTyping->created_at,
                'updated_at' => $pageTyping->updated_at
            ];
        }

        // Create response data with transformed pagetypings
        $responseData = $file->toArray();
        $responseData['pagetypings'] = $transformedPageTypings;

        return response()->json([
            'success' => true,
            'file' => $responseData
        ]);
    }

    /**
     * Get document pages for viewer
     */
    public function getDocumentPages(Request $request, $id)
    {
        if (str_starts_with((string) $id, 'storage-demo-')) {
            $decodedPath = $this->decodeStorageDemoPath((string) $id, $module);
            if (!$decodedPath) {
                return response()->json([
                    'success' => false,
                    'message' => 'File could not be resolved.'
                ], 404);
            }
            return response()->json($this->buildStorageDocumentPagesPayload($decodedPath, (string) $id, $module));
        }

        $file = FileIndexing::with([
            'pagetypings' => function ($query) {
                $query->orderByRaw('ISNULL(definition, 0) asc')
                    ->orderBy('page_number')
                    ->with([
                        'coverType',
                        'typedBy:id,first_name,last_name',
                        'pageType',
                        'pageSubType',
                        'scanning:id,file_indexing_id,document_path,display_order,original_filename,document_type,created_at'
                    ]);
            }
        ])->findOrFail($id);

        $module = $request->get('url', '');
        if ($this->isStorageDemoMode($module) && $file->pagetypings->isEmpty()) {
            $folderPath = $this->resolveStorageFolderByFileNumber((string) $file->file_number, $module);
            if ($folderPath) {
                return response()->json($this->buildStorageDocumentPagesPayload(
                    $folderPath,
                    (string) $id,
                    $module,
                    (string) $file->file_number,
                    (string) ($file->file_title ?: $file->file_number)
                ));
            }
        }

        $pathPrefixes = [
            'storage/app/public/',
            'app/public/',
            'public/',
            'storage/'
        ];

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'];

        $resolveMedia = function (?string $rawPath) use ($pathPrefixes, $imageExtensions) {
            if (!$rawPath) {
                return null;
            }

            $rawPath = str_replace('\\', '/', $rawPath);
            $trimmedRawPath = ltrim($rawPath, '/');

            $normalizedPath = $trimmedRawPath;
            foreach ($pathPrefixes as $prefix) {
                if (stripos($normalizedPath, $prefix) === 0) {
                    $normalizedPath = substr($normalizedPath, strlen($prefix));
                    break;
                }
            }
            $normalizedPath = ltrim($normalizedPath, '/');

            $extension = null;
            if ($normalizedPath) {
                $extension = strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION));
            } elseif ($trimmedRawPath) {
                $extension = strtolower(pathinfo($trimmedRawPath, PATHINFO_EXTENSION));
            }

            $viewerUrl = null;
            if ($normalizedPath && Storage::disk('public')->exists($normalizedPath)) {
                $viewerUrl = Storage::disk('public')->url($normalizedPath);
            }

            if (!$viewerUrl) {
                $publicFile = public_path($trimmedRawPath);
                if ($trimmedRawPath && file_exists($publicFile)) {
                    $viewerUrl = asset($trimmedRawPath);
                }
            }

            if (!$viewerUrl) {
                return null;
            }

            $mediaType = 'document';
            $thumbnailUrl = null;
            if ($extension && in_array($extension, $imageExtensions)) {
                $mediaType = 'image';
                $thumbnailUrl = $viewerUrl;
            } elseif ($extension === 'pdf') {
                $mediaType = 'pdf';
            }

            return [
                'viewer_url' => $viewerUrl,
                'thumbnail_url' => $thumbnailUrl,
                'media_type' => $mediaType,
                'extension' => $extension,
                'normalized_path' => $normalizedPath,
            ];
        };

        $pages = $file->pagetypings->map(function ($pageTyping) use ($resolveMedia) {
            $mediaSources = [
                ['path' => optional($pageTyping->scanning)->document_path, 'source' => 'scanning'],
                ['path' => $pageTyping->file_path, 'source' => 'pagetypings']
            ];

            $media = null;
            $mediaSource = null;
            foreach ($mediaSources as $candidate) {
                $media = $resolveMedia($candidate['path'] ?? null);
                if ($media) {
                    $mediaSource = $candidate['source'];
                    break;
                }
            }

            if (!$media) {
                $media = [
                    'viewer_url' => null,
                    'thumbnail_url' => null,
                    'media_type' => 'document',
                    'extension' => null,
                    'normalized_path' => null,
                ];
            }

            if ($media['media_type'] === 'document') {
                if (method_exists($pageTyping, 'isImagePage') && $pageTyping->isImagePage()) {
                    $media['media_type'] = 'image';
                    if (!$media['thumbnail_url'] && $media['viewer_url']) {
                        $media['thumbnail_url'] = $media['viewer_url'];
                    }
                } elseif (method_exists($pageTyping, 'isPdfPage') && $pageTyping->isPdfPage()) {
                    $media['media_type'] = 'pdf';
                }
            }

            return [
                'page_number' => $pageTyping->page_number,
                'page_type' => $pageTyping->pageType ? [
                    'id' => $pageTyping->pageType->id,
                    'name' => $pageTyping->pageType->PageType,
                    'code' => $pageTyping->pageType->code
                ] : [
                    'id' => $pageTyping->page_type,
                    'name' => 'Unknown Type',
                    'code' => 'UNK'
                ],
                'page_subtype' => $pageTyping->pageSubType ? [
                    'id' => $pageTyping->pageSubType->id,
                    'name' => $pageTyping->pageSubType->PageSubType,
                    'code' => $pageTyping->pageSubType->code
                ] : ($pageTyping->page_subtype ? [
                        'id' => $pageTyping->page_subtype,
                        'name' => 'Unknown Subtype',
                        'code' => 'UNK'
                    ] : null),
                'page_code' => $pageTyping->page_code,
                'definition' => $pageTyping->definition,
                'definition_code' => $pageTyping->definition_code,
                'serial_number' => $pageTyping->serial_number,
                'cover_type' => $pageTyping->coverType ? [
                    'id' => $pageTyping->coverType->Id,
                    'name' => $pageTyping->coverType->Name,
                    'code' => $pageTyping->coverType->code
                ] : null,
                'typed_by' => $pageTyping->typedBy ? [
                    'name' => $pageTyping->typedBy->first_name . ' ' . $pageTyping->typedBy->last_name
                ] : null,
                'viewer_url' => $media['viewer_url'],
                'thumbnail_url' => $media['thumbnail_url'],
                'media_type' => $media['media_type'],
                'media_source' => $mediaSource,
                'pdf_page_number' => method_exists($pageTyping, 'getPdfPageNumber') ? $pageTyping->getPdfPageNumber() : null,
                'scanning_id' => optional($pageTyping->scanning)->id,
                'scanning_display_order' => optional($pageTyping->scanning)->display_order,
                'scanning_original_filename' => optional($pageTyping->scanning)->original_filename,
                'scanning_document_type' => optional($pageTyping->scanning)->document_type,
                'scanning_document_path' => optional($pageTyping->scanning)->document_path,
                'created_at' => $pageTyping->created_at->format('Y-m-d H:i:s')
            ];
        });

        return response()->json([
            'success' => true,
            'file' => [
                'id' => $file->id,
                'file_number' => $file->file_number,
                'file_title' => $file->file_title,
                'total_pages' => $file->pagetypings->count()
            ],
            'pages' => $pages
        ]);
    }

    /**
     * Search files with advanced filters
     */
    public function search(Request $request)
    {
        $query = FileIndexing::whereHas('pagetypings')
            ->whereHas('scannings')
            ->whereColumn(
                DB::raw('(SELECT COUNT(*) FROM pagetypings WHERE pagetypings.file_indexing_id = file_indexings.id)'),
                '>=',
                DB::raw('(SELECT COUNT(*) FROM scannings WHERE scannings.file_indexing_id = file_indexings.id)')
            )
            ->with(['pagetypings.typedBy', 'scannings', 'fileTracking' => function ($query) {
                    $query->select(['id', 'file_indexing_id', 'status', 'assignment_status']);
                }])
            ->withCount(['pagetypings', 'scannings']);

        // Apply registry/module filter
        $module = $request->get('url', '');
        $isStorageDemoMode = $this->isStorageDemoMode($module);

        if ($isStorageDemoMode) {
            $files = $this->buildStorageDemoPaginator($request, $module);

            return response()->json([
                'success' => true,
                'files' => $files,
                'html' => view('filearchive.partials.files_grid_content', compact('files'))->render()
            ]);
        }

        if ($module !== '') {
            $query->where('registry', 'like', '%' . $module . '%');
        }

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('file_title', 'like', "%{$search}%")
                    ->orWhere('file_number', 'like', "%{$search}%")
                    ->orWhereHas('pagetypings', function ($subQ) use ($search) {
                        $subQ->where('page_type', 'like', "%{$search}%");
                    });
            });
        }

        // Apply year filter (matches year in file_number, e.g. RES-2026-7)
        if ($request->filled('year') && $request->get('year') !== 'all') {
            $year = $request->get('year');
            $query->where('file_number', 'like', "%-{$year}-%");
        }

        // Apply registry filter
        if ($request->filled('registry') && $request->get('registry') !== 'all') {
            $query->where('registry', $request->get('registry'));
        }

        $files = $query->paginate(12);

        if ($isStorageDemoMode && $files->total() === 0) {
            $files = $this->buildStorageDemoPaginator($request, $module);
        } elseif ($isStorageDemoMode) {
            $files = $this->enrichStorageMetadataForPaginator($files, $module);
        }

        return response()->json([
            'success' => true,
            'files' => $files,
            'html' => view('filearchive.partials.files_grid_content', compact('files'))->render()
        ]);
    }

    /**
     * Calculate storage used by archived files
     */
    private function calculateStorageUsed(string $module = ''): string
    {
        // Prefer indexed byte sizes on scannings table for speed and accuracy.
        $query = Scanning::query();

        if ($module !== '') {
            $query->whereHas('fileIndexing', function ($q) use ($module) {
                $q->where('registry', 'like', '%' . $module . '%');
            });
        }

        // SQL Server SUM(int) returns int; cast to BIGINT to avoid overflow on large datasets.
        $totalBytes = (int) ($query
            ->selectRaw('SUM(CAST(ISNULL(file_size, 0) AS BIGINT)) as total_bytes')
            ->value('total_bytes') ?? 0);

        return $this->formatBytes($totalBytes);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format($value, $power === 0 ? 0 : 1) . ' ' . $units[$power];
    }

    private function isStorageDemoMode(string $module): bool
    {
        $allowed = ['kangis', 'sltr', 'dciv', 'cadastral'];
        return in_array(strtolower(trim($module)), $allowed, true);
    }

    private function getRegistryFolderName(string $module): string
    {
        $map = [
            'kangis' => 'KANGIS_Registry',
            'sltr' => 'SLTR_Registry',
            'dciv' => 'DCIV_Registry',
            'cadastral' => 'Cadastral_Registry',
        ];
        return $map[strtolower(trim($module))] ?? 'KANGIS_Registry';
    }

    private function buildStorageDemoPaginator(Request $request, string $module): LengthAwarePaginator
    {
        $disk = $this->getStorageDisk($module);
        $basePath = $this->getStorageBasePath($module);

        if (!$disk->exists($basePath)) {
            return new LengthAwarePaginator([], 0, 12, (int) $request->get('page', 1), [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        }

        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'];
        $search = trim((string) $request->get('search', ''));

        $entries = collect($disk->directories($basePath))
            ->filter(function (string $path) use ($allowedExtensions, $module) {
                return collect($this->getStorageDemoFiles($path, $allowedExtensions, $this->getStorageDisk($module)))->isNotEmpty();
            })
            ->when($search !== '', function ($collection) use ($search) {
                return $collection->filter(function (string $path) use ($search) {
                    return stripos(basename($path), $search) !== false;
                });
            })
            ->sortByDesc(function (string $path) use ($disk, $module) {
                $firstFile = collect($this->getStorageDemoFiles($path, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'], $disk))->first();
                return $firstFile ? $disk->lastModified($firstFile) : 0;
            })
            ->values();

        $perPage = 12;
        $currentPage = max(1, (int) $request->get('page', 1));
        $offset = ($currentPage - 1) * $perPage;

        $pageItems = $entries->slice($offset, $perPage)->map(function (string $path) use ($disk, $allowedExtensions, $module) {
            $files = collect($this->getStorageDemoFiles($path, $allowedExtensions, $disk));
            $firstFile = $files->first();
            $timestamp = $firstFile ? $disk->lastModified($firstFile) : 0;
            $encodedPath = rtrim(strtr(base64_encode($path), '+/', '-_'), '=');
            $previewPath = $this->pickPreviewFileFromFiles($files->all());

            return (object) [
                'id' => 'storage-demo-' . $encodedPath,
                'file_number' => strtoupper(basename($path)),
                'file_title' => str_replace(['_', '-'], ' ', basename($path)),
                'land_use_type' => null,
                'district' => $this->getRegistryFolderName($module),
                'updated_at' => $timestamp ? date('Y-m-d H:i:s', $timestamp) : now()->format('Y-m-d H:i:s'),
                'plot_number' => null,
                'location' => null,
                'registry' => $this->getRegistryFolderName($module),
                'pagetypings_count' => max(1, $files->count()),
                'scannings_count' => max(1, $files->count()),
                'fileTracking' => null,
                'pagetypings' => collect(),
                'scannings' => collect(),
                'firstPageTyping' => null,
                'storage_preview_path' => $previewPath,
                'storage_preview_url' => $previewPath ? $this->getStorageUrl($previewPath, $module) : null,
            ];
        });

        return new LengthAwarePaginator(
            $pageItems,
            $entries->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function decodeStorageDemoPath(string $id, string $module): ?string
    {
        $encoded = substr($id, strlen('storage-demo-'));
        if ($encoded === '') {
            return null;
        }

        $decoded = base64_decode(strtr($encoded, '-_', '+/') . str_repeat('=', (4 - strlen($encoded) % 4) % 4), true);
        if (!$decoded) {
            return null;
        }

        $decoded = trim(str_replace('\\', '/', $decoded), '/');
        $basePath = $this->getStorageBasePath($module);
        if (!str_starts_with(strtolower($decoded), strtolower($basePath))) {
            return null;
        }

        $disk = $this->getStorageDisk($module);
        if ($disk->exists($decoded) || $this->isStorageDemoDirectory($decoded, $module)) {
            return $decoded;
        }

        return null;
    }

    private function isStorageDemoDirectory(string $path, string $module): bool
    {
        $disk = $this->getStorageDisk($module);
        return !empty($disk->files($path)) || !empty($disk->directories($path));
    }

    private function calculateStorageFolderSize(string $module): string
    {
        $disk = $this->getStorageDisk($module);
        $basePath = $this->getStorageBasePath($module);

        if (!$disk->exists($basePath)) {
            return '0 B';
        }

        $totalBytes = collect($disk->allFiles($basePath))->sum(function (string $path) use ($disk, $module) {
            try {
                return (int) $disk->size($path);
            } catch (\Throwable $e) {
                return 0;
            }
        });

        return $this->formatBytes((int) $totalBytes);
    }

    private function getStorageDisk(string $module)
    {
        $registry = strtoupper(trim($module));
        $rootOverride = trim((string) env("{$registry}_STORAGE_ROOT", env('KANGIS_STORAGE_ROOT', '')));
        if ($rootOverride !== '') {
            return Storage::build([
                'driver' => 'local',
                'root' => $rootOverride,
                'url' => rtrim((string) env("{$registry}_STORAGE_URL_PREFIX", env('KANGIS_STORAGE_URL_PREFIX', '/storage')), '/'),
                'visibility' => 'public',
            ]);
        }

        return Storage::disk(env("{$registry}_STORAGE_DISK", env('KANGIS_STORAGE_DISK', 'public')));
    }

    private function getStorageBasePath(string $module): string
    {
        $registry = strtoupper(trim($module));
        $folderName = $this->getRegistryFolderName($module);
        
        $configuredBasePath = trim(str_replace('\\', '/', (string) env("{$registry}_REGISTRY_BASE_PATH", env('KANGIS_REGISTRY_BASE_PATH', ''))), '/');
        if ($configuredBasePath !== '') {
            return $configuredBasePath;
        }

        $rootOverride = trim((string) env("{$registry}_STORAGE_ROOT", env('KANGIS_STORAGE_ROOT', '')));
        if ($rootOverride !== '') {
            $disk = $this->getStorageDisk($module);
            $appPublicPath = "app/public/EDMS/UPLOAD/{$folderName}";
            if ($disk->exists($appPublicPath)) {
                return $appPublicPath;
            }
        }

        return "EDMS/UPLOAD/{$folderName}";
    }

    private function getStorageUrl(string $relativePath, string $module): string
    {
        $registry = strtoupper(trim($module));
        $prefix = rtrim((string) env("{$registry}_STORAGE_URL_PREFIX", env('KANGIS_STORAGE_URL_PREFIX', '/storage')), '/');
        $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (str_starts_with(strtolower($normalized), 'app/public/')) {
            $normalized = substr($normalized, strlen('app/public/'));
        }

        return $prefix . '/' . ltrim($normalized, '/');
    }

    private function getStorageDemoFiles(string $folderPath, array $allowedExtensions, $disk): array
    {
        return collect($disk->allFiles($folderPath))
            ->filter(function (string $path) use ($allowedExtensions) {
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                return in_array($extension, $allowedExtensions, true);
            })
            ->values()
            ->all();
    }

    private function pickPreviewFileFromFiles(array $files): ?string
    {
        if (empty($files)) {
            return null;
        }

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'];
        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($extension, $imageExtensions, true)) {
                return $file;
            }
        }

        return $files[0];
    }

    private function countStoragePages(string $module): int
    {
        $disk = $this->getStorageDisk($module);
        $basePath = $this->getStorageBasePath($module);

        if (!$disk->exists($basePath)) {
            return 0;
        }

        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'];

        return (int) collect($disk->directories($basePath))
            ->sum(function (string $folderPath) use ($allowedExtensions, $disk, $module) {
                return count($this->getStorageDemoFiles($folderPath, $allowedExtensions, $disk));
            });
    }

    private function enrichStorageMetadataForPaginator(LengthAwarePaginator $paginator, string $module): LengthAwarePaginator
    {
        foreach ($paginator->items() as $item) {
            $fileNumber = trim((string) ($item->file_number ?? ''));
            if ($fileNumber === '') {
                continue;
            }

            $folderPath = $this->resolveStorageFolderByFileNumber($fileNumber, $module);
            if (!$folderPath) {
                continue;
            }

            $files = collect($this->getStorageDemoFiles(
                $folderPath,
                ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'],
                $this->getStorageDisk($module)
            ));

            if ($files->isEmpty()) {
                continue;
            }

            $previewPath = $this->pickPreviewFileFromFiles($files->all());
            $item->storage_folder_path = $folderPath;
            $item->storage_page_count = $files->count();
            $item->storage_preview_path = $previewPath;
            $item->storage_preview_url = $previewPath ? $this->getStorageUrl($previewPath, $module) : null;
        }

        return $paginator;
    }

    private function resolveStorageFolderByFileNumber(string $fileNumber, string $module): ?string
    {
        $basePath = $this->getStorageBasePath($module);
        $disk = $this->getStorageDisk($module);
        $normalized = trim(str_replace('\\', '/', $fileNumber));

        $candidates = array_values(array_unique([
            trim($basePath . '/' . $normalized, '/'),
            trim($basePath . '/' . str_replace('-', ' ', $normalized), '/'),
            trim($basePath . '/' . str_replace(' ', '-', $normalized), '/'),
            trim($basePath . '/' . str_replace('/', '-', $normalized), '/'),
            trim($basePath . '/' . str_replace(['/', '-'], ' ', $normalized), '/'),
        ]));

        foreach ($candidates as $candidate) {
            if ($this->isStorageDemoDirectory($candidate, $module)) {
                return $candidate;
            }
        }

        $target = strtolower(preg_replace('/\s+/', ' ', str_replace(['-', '/'], ' ', $normalized)));
        foreach ($disk->directories($basePath) as $directory) {
            $name = basename(str_replace('\\', '/', $directory));
            $normalizedName = strtolower(preg_replace('/\s+/', ' ', str_replace(['-', '/'], ' ', $name)));
            if ($normalizedName === $target && $this->isStorageDemoDirectory($directory, $module)) {
                return $directory;
            }
        }

        return null;
    }

    private function buildStorageDocumentPagesPayload(string $path, string $id, string $module, ?string $overrideFileNumber = null, ?string $overrideTitle = null): array
    {
        $disk = $this->getStorageDisk($module);
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'];
        $documentExtensions = array_merge($imageExtensions, ['pdf']);

        $paths = collect();
        if ($this->isStorageDemoDirectory($path, $module)) {
            $paths = collect($disk->allFiles($path));
        } elseif ($disk->exists($path)) {
            $paths = collect([$path]);
        }

        $paths = $paths
            ->filter(function (string $filePath) use ($documentExtensions) {
                $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                return in_array($extension, $documentExtensions, true);
            })
            ->sortBy(function (string $filePath) {
                $filename = pathinfo($filePath, PATHINFO_FILENAME);
                preg_match('/^(\d+)/', $filename, $matches);
                $prefix = isset($matches[1]) ? (int) $matches[1] : PHP_INT_MAX;
                return sprintf('%010d-%s', $prefix, strtolower($filename));
            })
            ->values();

        if ($paths->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No files found for this record.',
                'file' => [
                    'id' => $id,
                    'file_number' => $overrideFileNumber ?: '-',
                    'file_title' => $overrideTitle ?: '-',
                    'total_pages' => 0,
                ],
                'pages' => []
            ];
        }

        $fileLabel = basename($this->isStorageDemoDirectory($path, $module) ? $path : dirname($path));
        $fileNumber = $overrideFileNumber ?: strtoupper($fileLabel);
        $fileTitle = $overrideTitle ?: str_replace(['_', '-'], ' ', $fileLabel);

        $pages = $paths->values()->map(function (string $filePath, int $index) use ($imageExtensions, $module) {
            $viewerUrl = $this->getStorageUrl($filePath, $module);
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $filename = pathinfo($filePath, PATHINFO_FILENAME);
            $mediaType = in_array($extension, $imageExtensions, true) ? 'image' : ($extension === 'pdf' ? 'pdf' : 'document');

            return [
                'page_number' => $index + 1,
                'page_type' => null,
                'page_subtype' => null,
                'page_code' => strtoupper($filename),
                'definition' => null,
                'definition_code' => null,
                'serial_number' => null,
                'cover_type' => null,
                'typed_by' => null,
                'viewer_url' => $viewerUrl,
                'thumbnail_url' => $mediaType === 'image' ? $viewerUrl : null,
                'media_type' => $mediaType,
                'media_source' => 'storage',
                'pdf_page_number' => null,
                'scanning_id' => null,
                'scanning_display_order' => null,
                'scanning_original_filename' => basename($filePath),
                'scanning_document_type' => strtoupper($extension),
                'scanning_document_path' => $filePath,
                'created_at' => now()->format('Y-m-d H:i:s')
            ];
        })->values();

        return [
            'success' => true,
            'file' => [
                'id' => $id,
                'file_number' => $fileNumber,
                'file_title' => $fileTitle,
                'total_pages' => $pages->count(),
            ],
            'pages' => $pages,
        ];
    }
}



