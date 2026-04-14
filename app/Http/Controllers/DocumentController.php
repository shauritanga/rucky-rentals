<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Services\DocumentCleanupService;
use App\Support\MockRentalData;
use App\Traits\LogsAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DocumentController extends Controller
{
    use LogsAudit;

    public function __construct(private DocumentCleanupService $cleanupService) {}

    public function index(Request $request)
    {
        $user = $request->user();
        if (MockRentalData::shouldUse() && $user?->role !== 'manager') {
            return Inertia::render('Documents/Index', [
                'documents' => MockRentalData::documents(),
                'units' => MockRentalData::units(),
                'tenants' => MockRentalData::tenants(),
            ]);
        }

        $documentsQuery = Document::with(['unit', 'tenant'])->orderByDesc('created_at');
        $unitsQuery = Unit::orderBy('unit_number');
        $tenantsQuery = Tenant::orderBy('name');

        // Scope documents to property when in property-view mode (via unit's property_id)
        if ($this->shouldScopeToProperty($request)) {
            $propertyId = $this->effectivePropertyId($request);
            if ($propertyId === null) {
                $documentsQuery->whereRaw('1 = 0');
                $unitsQuery->whereRaw('1 = 0');
                $tenantsQuery->whereRaw('1 = 0');
            } else {
                $documentsQuery->whereHas('unit', fn($q) => $q->where('property_id', $propertyId))
                    ->orWhereNull('unit_id'); // documents not linked to a unit
                $unitsQuery->where('property_id', $propertyId);
                $tenantsQuery->where('property_id', $propertyId);
            }
        }

        $documents = $documentsQuery->get();
        $units     = $unitsQuery->get();
        $tenants   = $tenantsQuery->get();
        return Inertia::render('Documents/Index', compact('documents', 'units', 'tenants'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'file'        => 'required|file|max:20480',
            'document_type' => 'required|in:lease_agreement,invoice,deposit,handover,national_id',
            'unit_ref'    => 'nullable|string',
            'tenant_id'   => 'nullable|exists:tenants,id|required_if:document_type,lease_agreement',
            'description' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $path = $file->store('documents', 'public');
        $ext  = strtolower($file->getClientOriginalExtension());
        $type = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 'img'
            : ($ext === 'pdf' ? 'pdf'
                : (in_array($ext, ['doc', 'docx']) ? 'word' : 'other'));

        $unit = $this->resolveUnitByReference($request, $request->unit_ref);

        $legacyTag = $this->legacyTagForDocumentType((string) $request->document_type);
        $document = Document::create([
            'name'        => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_path'   => $path,
            'file_type'   => $type,
            'file_size'   => $this->formatSize($file->getSize()),
            'tag'         => $legacyTag,
            'document_type' => $request->document_type,
            'unit_ref'    => $request->unit_ref,
            'unit_id'     => $unit?->id,
            'tenant_id'   => $request->tenant_id ?: null,
            'description' => $request->description,
            'uploaded_by' => $request->user()?->name ?? 'System',
        ]);

        // Automatic retention cleanup after each upload.
        $this->cleanupService->purgeExpiredInvoices();
        if (!empty($document->tenant_id) && $document->document_type === 'lease_agreement') {
            $this->cleanupService->enforceLeaseAgreementLimitForTenant((int) $document->tenant_id, 2);
        }

        $propertyId   = $unit?->property_id ?? $request->user()?->property_id;
        $propertyName = Property::where('id', $propertyId)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Document uploaded',
            resource: sprintf('%s (%s)', $document->name, $request->document_type),
            propertyName: $propertyName,
            category: 'document',
            propertyId: $propertyId ? (int) $propertyId : null,
        );

        return back()->with('success', 'Document uploaded.');
    }

    public function update(Request $request, Document $document)
    {
        $document->update($request->only(['name']));

        $propertyId   = $document->unit?->property_id ?? $request->user()?->property_id;
        $propertyName = Property::where('id', $propertyId)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Document renamed',
            resource: $document->name,
            propertyName: $propertyName,
            category: 'document',
            propertyId: $propertyId ? (int) $propertyId : null,
        );

        return back()->with('success', 'Document renamed.');
    }

    public function destroy(Document $document)
    {
        $propertyId   = $document->unit?->property_id ?? request()->user()?->property_id;
        $propertyName = Property::where('id', $propertyId)->value('name');
        $name         = $document->name;

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        $this->logAudit(
            request: request(),
            action: 'Document deleted',
            resource: $name,
            propertyName: $propertyName,
            category: 'document',
            propertyId: $propertyId ? (int) $propertyId : null,
        );

        return back()->with('success', 'Document deleted.');
    }

    private function formatSize(int $bytes): string
    {
        return $bytes >= 1048576
            ? round($bytes / 1048576, 1) . ' MB'
            : round($bytes / 1024) . ' KB';
    }

    private function legacyTagForDocumentType(string $documentType): string
    {
        return match ($documentType) {
            'lease_agreement' => 'lease',
            'invoice' => 'other',
            'deposit' => 'id',
            'national_id' => 'id',
            'handover' => 'notice',
            default => 'other',
        };
    }
}
