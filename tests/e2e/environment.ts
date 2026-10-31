import { resolve } from 'node:path';

export const e2eDatabasePath = resolve(
    'storage/framework/testing/pentagon-e2e.sqlite',
);

export const e2eReleasePath = resolve('storage/app/private/releases');

export const e2eEnvironment: Record<string, string> = {
    APP_ENV: 'testing',
    APP_DEBUG: 'false',
    APP_KEY: 'base64:AQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQE=',
    APP_URL: 'http://127.0.0.1:8173',
    BCRYPT_ROUNDS: '4',
    CACHE_STORE: 'array',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: e2eDatabasePath,
    FILESYSTEM_DISK: 'local',
    LOG_CHANNEL: 'single',
    LOG_LEVEL: 'error',
    MAIL_MAILER: 'array',
    QUEUE_CONNECTION: 'sync',
    SESSION_DRIVER: 'file',
};
