<x-app-layout title="Edit Tax Compliance Rule">
    <x-page-header title="Edit Tax Compliance Rule" description="Changes to a previously used rule create a preserved successor version." />
    <div class="mb-4"><a href="{{ route('tax-rules.index') }}" class="text-sm text-blue-700 underline">Back to tax rules</a></div>
    <x-tax-rule-form :action="route('tax-rules.update', $rule)" method="PUT" :$rule />
</x-app-layout>
