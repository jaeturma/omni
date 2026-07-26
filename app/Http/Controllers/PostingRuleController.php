<?php

namespace App\Http\Controllers;

use App\Actions\SavePostingRule;
use App\Enums\PostingSourceType;
use App\Http\Requests\PostingRulePreviewRequest;
use App\Http\Requests\PostingRuleRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\PostingRule;
use App\Models\Warehouse;
use App\Services\ResolvePostingRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PostingRuleController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PostingRule::class);
        $rules = PostingRule::query()->with(['debitAccount:id,code,name', 'creditAccount:id,code,name'])
            ->when($request->filled('source_type'), fn ($query) => $query->where('source_type', $request->string('source_type')))
            ->latest()->paginate(25)->withQueryString();

        return view('posting-rules.index', ['rules' => $rules, 'sourceTypes' => PostingSourceType::cases()]);
    }

    public function create(): View
    {
        Gate::authorize('create', PostingRule::class);

        return view('posting-rules.form', $this->formData());
    }

    public function store(PostingRuleRequest $request, SavePostingRule $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user()->id);

        return redirect()->route('posting-rules.index')->with('success', 'Posting rule created.');
    }

    public function edit(PostingRule $postingRule): View
    {
        Gate::authorize('update', $postingRule);

        return view('posting-rules.form', $this->formData() + ['postingRule' => $postingRule]);
    }

    public function update(PostingRuleRequest $request, PostingRule $postingRule, SavePostingRule $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user()->id, $postingRule);

        return redirect()->route('posting-rules.index')->with('success', 'Posting rule updated.');
    }

    public function status(PostingRule $postingRule): RedirectResponse
    {
        $ability = $postingRule->is_active ? 'deactivate' : 'activate';
        Gate::authorize($ability, $postingRule);
        $userId = request()->user()->id;
        $postingRule->update($postingRule->is_active
            ? ['is_active' => false, 'deactivated_at' => now(), 'deactivated_by' => $userId, 'updated_by' => $userId]
            : ['is_active' => true, 'activated_at' => now(), 'activated_by' => $userId, 'deactivated_at' => null, 'deactivated_by' => null, 'updated_by' => $userId]);

        return back()->with('success', 'Posting rule status updated.');
    }

    public function preview(PostingRulePreviewRequest $request, ResolvePostingRule $resolver): View
    {
        $validated = $request->validated();
        $dimensions = collect($validated)->only(PostingRule::DIMENSIONS)->filter(fn ($value) => $value !== null)->all();
        $preview = $resolver->preview(
            PostingSourceType::from($validated['source_type']),
            $validated['posting_date'],
            $validated['amount'],
            $dimensions,
        );

        return view('posting-rules.preview', ['preview' => $preview, 'journalCount' => JournalEntry::query()->count()]);
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'sourceTypes' => PostingSourceType::cases(),
            'accounts' => Account::query()->where('is_active', true)->where('is_postable', true)->ordered()->get(['id', 'code', 'name']),
            'productCategories' => Category::query()->where('type', 'product')->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'serviceCategories' => Category::query()->where('type', 'service')->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'financialAccounts' => FinancialAccount::query()->where('active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'warehouses' => Warehouse::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']),
        ];
    }
}
