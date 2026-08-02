<?php

namespace App\Http\Controllers;

use App\Models\BackupRun;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BackupRunController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', BackupRun::class);

        return view('backup-runs.index', ['runs' => BackupRun::query()->with('initiator:id,name')->latest('started_at')->paginate(30)]);
    }
}
