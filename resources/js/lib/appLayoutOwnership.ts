export interface AppLayouts<T> {
    app: T;
    auth: T;
    settings: T;
}

export function resolveAppLayout<T>(
    name: string,
    layouts: AppLayouts<T>,
): T | T[] | null {
    if (name === 'Welcome') return null;
    if (name.startsWith('auth/')) return layouts.auth;
    if (name.startsWith('settings/')) {
        return [layouts.app, layouts.settings];
    }

    return layouts.app;
}
