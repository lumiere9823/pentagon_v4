import { execFileSync } from 'node:child_process';
import { mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname } from 'node:path';
import { e2eDatabasePath, e2eEnvironment, e2eReleasePath } from './environment';

export default function globalSetup() {
    mkdirSync(dirname(e2eDatabasePath), { recursive: true });
    writeFileSync(e2eDatabasePath, '');

    rmSync(e2eReleasePath, { recursive: true, force: true });
    mkdirSync(e2eReleasePath, { recursive: true });

    const env = { ...process.env, ...e2eEnvironment };

    execFileSync('php', ['artisan', 'migrate:fresh', '--force'], {
        env,
        stdio: 'inherit',
    });
    execFileSync(
        'php',
        [
            'artisan',
            'db:seed',
            '--class=Database\\Seeders\\E2ETestSeeder',
            '--force',
        ],
        { env, stdio: 'inherit' },
    );
}
