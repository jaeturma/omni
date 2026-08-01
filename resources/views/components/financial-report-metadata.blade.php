@props(['metadata'])
<section class="mb-5 border-b border-slate-300 pb-4 text-sm text-slate-700">
    @if($metadata['confidential_label'])
        <p class="mb-2 text-center text-xs font-bold uppercase tracking-widest text-red-700">{{ $metadata['confidential_label'] }}</p>
    @endif
    <p class="text-base font-semibold text-slate-950">{{ $metadata['business_name'] }}</p>
    <p>{{ $metadata['report_name'] }} · {{ $metadata['period'] }} · {{ $metadata['basis'] }}</p>
    <p>Generated {{ $metadata['generated_at'] }} by {{ $metadata['generated_by'] }}</p>
</section>
