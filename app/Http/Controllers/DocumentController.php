<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Property;
use App\Models\Unit;
use App\Support\MockRentalData;
use App\Traits\LogsAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DocumentController extends Controller
{
    use LogsAudit;
    public function index()
    {
        if (MockRentalData::shouldUse()) {
            return Inertia::render('Documents/Index', [
                'documents' => MockRentalData::documents(),
                'units' => MockRentalData::units(),
            ]);
        }

        $documents = Document::with('unit')->orderByDesc('created_at')->get();
        $units     = Unit::orderBy('unit_number')->get();
        return Inertia::render('Documents/Index', compact('documents', 'units'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'file'        => 'required|file|max:20480',
            'tag'         => 'required|in:lease,id,notice,other',
            'unit_ref'    => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $path = $file->store('documents', 'public');
        $ext  = strtolower($file->getClientOriginalExtension());
        $type = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 'img'
            : ($ext === 'pdf' ? 'pdf'
                : (in_array($ext, ['doc', 'docx']) ? 'word' : 'other'));

        $unit = $request->unit_ref ? Unit::where('unit_number', $request->unit_ref)->first() : null;

        $document = Document::create([
            'name'        => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_path'   => $path,
            'file_type'   => $type,
            'file_size'   => $this->formatSize($file->getSize()),
            'tag'         => $request->tag,
            'unit_ref'    => $request->unit_ref,
            'unit_id'     => $unit?->id,
            'description' => $request->description,
            'uploaded_by' => $request->user()?->name ?? 'System',
        ]);

        $propertyId   = $unit?->property_id ?? $request->user()?->property_id;
        $propertyName = Property::where('id', $propertyId)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Document uploaded',
            resource: sprintf('%s (%s)', $document->name, $request->tag),
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
}
