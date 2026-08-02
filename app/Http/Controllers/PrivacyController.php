<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArchiveRecordRequest;
use App\Http\Requests\DataSubjectLookupRequest;
use App\Http\Requests\StoreRetentionPolicyRequest;
use App\Models\RetentionPolicy;
use App\Services\AuditLogger;
use App\Services\DataSubjectRegistry;
use App\Services\RecordArchiver;
use App\Support\DataClassificationRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivacyController extends Controller
{
    public function index(DataSubjectLookupRequest $request, DataClassificationRegistry $classifications, DataSubjectRegistry $subjects): View
    {
        Gate::authorize('viewAny', RetentionPolicy::class);
        $term = $request->validated('q');

        return view('privacy.index', ['classifications' => $classifications->all(),
            'policies' => RetentionPolicy::query()->with('reviewer:id,name')->orderBy('record_type')->get(),
            'results' => filled($term) ? $subjects->lookup((string) $term, Gate::allows('sensitive-data.view')) : collect(),
            'term' => $term]);
    }

    public function storePolicy(StoreRetentionPolicyRequest $request, AuditLogger $audit): RedirectResponse
    {
        $policy = RetentionPolicy::query()->updateOrCreate(['record_type' => $request->validated('record_type')], $request->validated() + ['reviewed_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $audit->log('retention_policy.updated', $policy, after: $policy->getAttributes());

        return back()->with('success', 'Retention policy saved. No records were automatically disposed.');
    }

    public function archive(ArchiveRecordRequest $request, string $type, int $id, RecordArchiver $archiver): RedirectResponse
    {
        $record = $archiver->resolve($type, $id);
        $archiver->archive($record, $request->user(), $request->string('reason')->toString());

        return back()->with('success', 'Record archived without deleting its history.');
    }

    public function export(DataSubjectLookupRequest $request, DataSubjectRegistry $subjects, AuditLogger $audit): StreamedResponse
    {
        $rows = $subjects->lookup((string) $request->validated('q'), true);
        $audit->log('privacy.data_subject_exported', metadata: ['query' => '[PROTECTED]', 'record_count' => $rows->count()]);

        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, ['Type', 'ID', 'Name', 'Email', 'Phone', 'Address', 'TIN']);
            foreach ($rows as $row) {
                fputcsv($stream, array_values($row));
            }
            fclose($stream);
        }, 'data-subject-export-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
