<?php

namespace App\Services\InternalTribunal;

use App\Models\InternalReport;
use App\Models\InternalReportEvidence;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InternalReportEvidenceService
{
    private const PRIVATE_DISK = 'local';
    private const STORAGE_DIR = 'internal_tribunal_evidence';

    public function storeEvidenceFile(InternalReport $report, UploadedFile $file, int $uploadedBy): InternalReportEvidence
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedFilename = Str::uuid()->toString() . '.' . $extension;
        $subDirectory = self::STORAGE_DIR . '/' . $report->id;
        $path = $file->storeAs($subDirectory, $storedFilename, self::PRIVATE_DISK);

        $realPath = $file->getRealPath();
        $hash = $realPath ? hash_file('sha256', $realPath) : null;

        return InternalReportEvidence::create([
            'internal_report_id' => $report->id,
            'uploaded_by' => $uploadedBy,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
            'sha256' => $hash ?: '',
            'is_confidential' => true,
        ]);
    }

    public function downloadEvidence(InternalReportEvidence $evidence, User $user): StreamedResponse
    {
        $report = $evidence->report;

        // Only original reporter or Super Admin can access evidence
        $isReporter = $report && (int) $report->reporter_user_id === (int) $user->id;
        $isAdmin = $user->isAdmin();

        if (!$isReporter && !$isAdmin) {
            abort(403, 'Unauthorized. Evidence is confidential.');
        }

        if (!Storage::disk(self::PRIVATE_DISK)->exists($evidence->file_path)) {
            abort(404, 'Evidence file not found on secure storage.');
        }

        return Storage::disk(self::PRIVATE_DISK)->download($evidence->file_path, $evidence->original_name);
    }
}
