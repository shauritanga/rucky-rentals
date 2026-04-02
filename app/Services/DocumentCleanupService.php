<?php

namespace App\Services;

use App\Models\Document;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentCleanupService
{
    public function purgeExpiredInvoices(?Carbon $asOf = null): int
    {
        $cutoff = ($asOf ?? now())->subYear();

        $expiredInvoices = Document::query()
            ->where('document_type', 'invoice')
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return $this->deleteDocuments($expiredInvoices);
    }

    public function enforceLeaseAgreementLimitForTenant(int $tenantId, int $keep = 2): int
    {
        $keep = max(0, $keep);

        $leaseDocs = Document::query()
            ->where('document_type', 'lease_agreement')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $toDelete = $leaseDocs->slice($keep);

        return $this->deleteDocuments($toDelete);
    }

    private function deleteDocuments($documents): int
    {
        if ($documents->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($documents) {
            foreach ($documents as $document) {
                if (!empty($document->file_path)) {
                    Storage::disk('public')->delete($document->file_path);
                }
                $document->delete();
            }
        });

        return $documents->count();
    }
}
