<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaxReviewCommentRequest;
use App\Models\TaxPeriod;
use App\Models\TaxReviewComment;
use App\Services\TaxDashboardReviewPack;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxDashboardController extends Controller
{
    public function __construct(private TaxDashboardReviewPack $reviewPack) {}

    public function index(): RedirectResponse
    {
        Gate::authorize('viewAny', TaxPeriod::class);

        return to_route('tax-dashboard.show', TaxPeriod::query()->latest('period_end')->firstOrFail());
    }

    public function show(TaxPeriod $taxPeriod): View
    {
        Gate::authorize('view', $taxPeriod);

        return view('tax-dashboard.show', $this->reviewPack->build($taxPeriod) + ['periods' => TaxPeriod::query()->latest('period_end')->get(['id', 'label'])]);
    }

    public function print(TaxPeriod $taxPeriod): View
    {
        Gate::authorize('generate', $taxPeriod);

        return view('tax-dashboard.review-pack', $this->reviewPack->build($taxPeriod));
    }

    public function download(TaxPeriod $taxPeriod): StreamedResponse
    {
        Gate::authorize('download', $taxPeriod);
        $html = view('tax-dashboard.review-pack', $this->reviewPack->build($taxPeriod, true))->render();

        return response()->streamDownload(fn () => print ($html), 'tax-review-pack-'.$taxPeriod->label.'.html', ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function storeComment(StoreTaxReviewCommentRequest $request, TaxPeriod $taxPeriod): RedirectResponse
    {
        $data = $request->validated();
        if ($data['status'] === 'resolved') {
            TaxReviewComment::query()->whereBelongsTo($taxPeriod)->findOrFail($data['comment_id'])->update(['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => $request->user()->id]);
        } else {
            $taxPeriod->reviewComments()->create(['comment' => $data['comment'], 'status' => 'open', 'created_by' => $request->user()->id]);
        }

        return back()->with('success', 'Tax review comment updated.');
    }
}
