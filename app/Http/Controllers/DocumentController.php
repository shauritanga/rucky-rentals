<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DocumentController extends Controller
{
    public function index()
    {
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
        $type = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'img'
              : ($ext === 'pdf' ? 'pdf'
              : (in_array($ext, ['doc','docx']) ? 'word' : 'other'));

        $unit = $request->unit_ref ? Unit::where('unit_number', $request->unit_ref)->first() : null;

        Document::create([
            'name'        => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_path'   => $path,
            'file_type'   => $type,
            'file_size'   => $this->formatSize($file->getSize()),
            'tag'         => $request->tag,
            'unit_ref'    => $request->unit_ref,
            'unit_id'     => $unit?->id,
            'description' => $request->description,
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function update(Request $request, Document $document)
    {
        $document->update($request->only(['name']));
        return back()->with('success', 'Document renamed.');
    }

    public function destroy(Document $document)
    {
        $document->delete();
        return back()->with('success', 'Document deleted.');
    }

    private function formatSize(int $bytes): string
    {
        return $bytes >= 1048576
            ? round($bytes / 1048576, 1) . ' MB'
            : round($bytes / 1024) . ' KB';
    }
}
