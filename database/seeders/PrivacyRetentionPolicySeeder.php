<?php

namespace Database\Seeders;

use App\Models\RetentionPolicy;
use Illuminate\Database\Seeder;

class PrivacyRetentionPolicySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['record_type' => 'financial_transactions', 'classification' => 'restricted', 'retention_months' => 120, 'retention_trigger' => 'fiscal_year_end', 'disposition' => 'review_for_disposal', 'legal_basis' => 'Tax, accounting, contractual, and legal recordkeeping requirements.'],
            ['record_type' => 'tax_records', 'classification' => 'restricted', 'retention_months' => 120, 'retention_trigger' => 'filing_or_due_date', 'disposition' => 'review_for_disposal', 'legal_basis' => 'BIR substantiation and applicable statutory recordkeeping requirements.'],
            ['record_type' => 'audit_logs', 'classification' => 'restricted', 'retention_months' => null, 'retention_trigger' => 'event_date', 'disposition' => 'retain_permanently', 'legal_basis' => 'Permanent integrity and accountability record for financial administration.'],
            ['record_type' => 'private_attachments', 'classification' => 'restricted', 'retention_months' => 120, 'retention_trigger' => 'related_record_close', 'disposition' => 'review_for_disposal', 'legal_basis' => 'Supporting evidence follows the related financial or tax record retention period.'],
            ['record_type' => 'backups', 'classification' => 'restricted', 'retention_months' => 13, 'retention_trigger' => 'backup_date', 'disposition' => 'review_for_disposal', 'legal_basis' => 'Operational recovery schedule, subject to backup-class retention rules.'],
            ['record_type' => 'application_logs', 'classification' => 'restricted', 'retention_months' => 3, 'retention_trigger' => 'log_date', 'disposition' => 'review_for_disposal', 'legal_basis' => 'Security and operational diagnosis with data-minimization controls.'],
        ] as $policy) {
            RetentionPolicy::query()->updateOrCreate(['record_type' => $policy['record_type']], $policy + ['active' => true, 'reviewed_at' => now()->toDateString()]);
        }
    }
}
