<x-app-layout title="Edit Account">
    <x-page-header title="Edit Account" description="Update account details while preserving protected system classifications." />
    <x-account-form :action="route('accounts.update', $account)" method="PUT" :account="$account" :account-classes="$accountClasses" :account-types="$accountTypes" :current-classifications="$currentClassifications" :cash-flow-classifications="$cashFlowClassifications" :can-manage-reporting-settings="$canManageReportingSettings" :parent-accounts="$parentAccounts" submit-label="Save account" />
</x-app-layout>
