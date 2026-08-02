<?php

it('provides complete unsigned UAT evidence and operating procedure templates', function (): void {
    $uat = file_get_contents(base_path('docs/operations/USER_ACCEPTANCE_TESTING.md'));
    $procedures = file_get_contents(base_path('docs/operations/OPERATING_PROCEDURES.md'));

    expect($uat)->toContain('UAT-01')->toContain('UAT-18')
        ->toContain('Expected result')->toContain('Actual result and evidence')
        ->toContain('Defect register')->toContain('Severity')
        ->toContain('Owner name/signature: ____________________')
        ->and($procedures)->toContain('## Daily transaction entry')
        ->toContain('## Daily cash review')->toContain('## Weekly receivable and payable review')
        ->toContain('## Monthly bank reconciliation')->toContain('## Monthly inventory review')
        ->toContain('## Month-end accounting review')->toContain('## Quarterly tax preparation')
        ->toContain('## Document attachment handling')->toContain('## Voiding, reversal, and correction')
        ->toContain('## User management')->toContain('## Backup verification')->toContain('## Incident reporting')
        ->toContain('## Production activation checklist');
});
