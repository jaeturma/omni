<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuditLogIndexRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request): View
    {
        return view('audit-logs.index', [
            'logs' => $this->query($request)->with('actor:id,name')->paginate(50)->withQueryString(),
            'users' => User::query()->whereHas('auditLogs')->orderBy('name')->get(['id', 'name']),
            'modules' => AuditLog::query()->distinct()->orderBy('module')->pluck('module'),
            'showSensitive' => Gate::allows('viewSensitive', AuditLog::class),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        Gate::authorize('view', $auditLog);

        return view('audit-logs.show', ['log' => $auditLog->load('actor:id,name'), 'showSensitive' => Gate::allows('viewSensitive', AuditLog::class)]);
    }

    public function export(AuditLogIndexRequest $request): StreamedResponse
    {
        Gate::authorize('export', AuditLog::class);
        $showSensitive = Gate::allows('viewSensitive', AuditLog::class);

        return response()->streamDownload(function () use ($request, $showSensitive): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Occurred at', 'Event', 'Module', 'Actor', 'Subject type', 'Subject ID', 'Reason', 'Source action', 'Correlation ID', 'IP address', 'User agent']);
            $this->query($request)->with('actor:id,name')->chunkById(500, function ($logs) use ($output, $showSensitive): void {
                foreach ($logs as $log) {
                    fputcsv($output, [$log->occurred_at->toIso8601String(), $log->event, $log->module, $log->actor?->name, $log->subject_type, $log->subject_id, $log->reason, $log->source_action, $log->correlation_id, $showSensitive ? $log->ip_address : '[PROTECTED]', $showSensitive ? $log->user_agent : '[PROTECTED]']);
                }
            });
            fclose($output);
        }, 'audit-log-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return Builder<AuditLog> */
    private function query(AuditLogIndexRequest $request): Builder
    {
        $filters = $request->validated();

        return AuditLog::query()
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('occurred_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('occurred_at', '<=', $date))
            ->when($filters['user_id'] ?? null, fn (Builder $query, int $id) => $query->where('actor_id', $id))
            ->when($filters['module'] ?? null, fn (Builder $query, string $module) => $query->where('module', $module))
            ->when($filters['event'] ?? null, fn (Builder $query, string $event) => $query->where('event', 'like', '%'.$event.'%'))
            ->when($filters['subject_type'] ?? null, fn (Builder $query, string $type) => $query->where('subject_type', $type))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, string $id) => $query->where('subject_id', $id))
            ->latest('occurred_at')->latest('id');
    }
}
