<x-app-layout title="Create Account">
    <x-page-header title="Create Account" description="Add a controlled account to the accounting hierarchy." />
    <x-account-form :action="route('accounts.store')" :account-classes="$accountClasses" :account-types="$accountTypes" :current-classifications="$currentClassifications" :cash-flow-classifications="$cashFlowClassifications" :can-manage-reporting-settings="$canManageReportingSettings" :parent-accounts="$parentAccounts" submit-label="Create account" />
</x-app-layout>
