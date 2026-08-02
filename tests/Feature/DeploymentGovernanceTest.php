<?php

use Illuminate\Support\Facades\Artisan;

it('keeps production secrets out of tracked environment templates', function (): void {
    $ignore = file_get_contents(base_path('.gitignore'));
    $template = file_get_contents(base_path('.env.production.example'));

    expect($ignore)->toContain("\n.env\n")->toContain("\n.env.production\n")
        ->and($template)->toContain('APP_ENV=production')->toContain('APP_DEBUG=false')
        ->toContain('APP_URL=https://')->toContain('SESSION_SECURE_COOKIE=true')
        ->and(preg_match('/^APP_KEY=\S+/m', $template))->toBe(0)
        ->and(preg_match('/^DB_PASSWORD=\S+/m', $template))->toBe(0)
        ->and(preg_match('/^BACKUP_ENCRYPTION_KEY=\S+/m', $template))->toBe(0);
});

it('publishes traceable release metadata through the framework about command', function (): void {
    config()->set('app.version', 'v10.7.0-test');
    config()->set('app.deployed_at', '2026-08-02T19:00:00+08:00');

    Artisan::call('about', ['--only' => 'deployment', '--json' => true]);
    $output = Artisan::output();

    expect($output)->toContain('v10.7.0-test')->toContain('2026-08-02T19:00:00+08:00');
});

it('registers the governed backup schedule', function (): void {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toContain('backup:run --class=daily')
        ->toContain('backup:run --class=weekly')
        ->toContain('backup:run --class=monthly');
});

it('compiles all production views before activation', function (): void {
    expect(Artisan::call('view:cache'))->toBe(0);
});

it('documents the guarded repeatable deployment and rollback gates', function (): void {
    $runbook = file_get_contents(base_path('docs/operations/PRODUCTION_DEPLOYMENT.md'));

    expect($runbook)->toContain('explicit owner approval')
        ->toContain('backup:run --class=pre_deployment')
        ->toContain('composer install --no-dev --optimize-autoloader')
        ->toContain('npm ci && npm run build')
        ->toContain('php artisan migrate --force')
        ->toContain('Do not automatically run `migrate:rollback`')
        ->toContain('APP_VERSION=<immutable-git-tag-or-commit>')
        ->toContain('php artisan schedule:list');
});
