<?php

it('records a complete evidence based final production readiness decision', function (): void {
    $review = file_get_contents(base_path('docs/reviews/PHASE-10-PRODUCTION-READINESS.md'));

    expect($review)->toContain('Decision: **Not ready for production**')
        ->toContain('## 2. Security findings')
        ->toContain('## 3. Audit findings')
        ->toContain('## 4. Backup and recovery findings')
        ->toContain('## 5. Privacy findings')
        ->toContain('## 6. Monitoring findings')
        ->toContain('## 7. Performance findings')
        ->toContain('## 8. Deployment findings')
        ->toContain('## 9. UAT findings')
        ->toContain('## 10. Cutover reconciliation')
        ->toContain('## 11. Financial and tax readiness')
        ->toContain('## 12. Critical and high gaps')
        ->toContain('## 13. Accepted risks')
        ->toContain('## 14. Owner actions')
        ->toContain('## 15. Go-live recommendation')
        ->toContain('## 16. Post-go-live review schedule')
        ->toContain('A fresh MySQL migration and deterministic seed now pass')
        ->toContain('WP-10-08 UAT execution evidence and owner sign-off are absent')
        ->toContain('Real opening balances and the WP-10-09 cutover report are not approved or activated')
        ->toContain('None recorded.');
});
