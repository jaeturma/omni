<?php

namespace App\Support;

class DataClassificationRegistry
{
    public const LEVELS = ['public', 'internal', 'confidential', 'restricted'];

    /** @return array<string, array{description: string, examples: list<string>, controls: list<string>}> */
    public function all(): array
    {
        return [
            'public' => ['description' => 'Approved information intended for public disclosure.', 'examples' => ['Application name', 'Published business contact details'], 'controls' => ['Owner approval before publication']],
            'internal' => ['description' => 'Routine operational information for authenticated staff.', 'examples' => ['Catalog data', 'Workflow status', 'Warehouse names'], 'controls' => ['Authenticated access', 'Role permissions']],
            'confidential' => ['description' => 'Personal or commercial information requiring limited business access.', 'examples' => ['Names', 'Addresses', 'Email addresses', 'Phone numbers', 'User information'], 'controls' => ['Need-to-know access', 'Masked lists and exports', 'Audit access']],
            'restricted' => ['description' => 'Financial, tax, credential, or evidence data with the highest impact.', 'examples' => ['TINs', 'Bank accounts', 'Financial transactions', 'Tax records', 'Attachments', 'Audit logs', 'Backups', 'Application logs'], 'controls' => ['Separate permissions', 'Private storage', 'Encryption where applicable', 'No casual deletion']],
        ];
    }
}
