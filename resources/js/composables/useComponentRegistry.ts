import { ref } from 'vue';
import { configureResponsiveContract } from '@/lib/responsive';
import { matchesStylePrimitiveContract } from '@/lib/stylePrimitives';
import type {
    ComponentMetadata,
    ComponentRegistryResponse,
} from '@/types/document';

type RegistryFetcher = (
    input: RequestInfo | URL,
    init?: RequestInit,
) => Promise<Pick<Response, 'ok' | 'status' | 'statusText' | 'json'>>;

export function createComponentRegistrySession(
    fetcher: RegistryFetcher = fetch,
) {
    const components = ref<Record<string, ComponentMetadata>>({});
    const aliases = ref<Record<string, string>>({});
    const isLoading = ref(false);
    const errorMessage = ref<string | null>(null);
    let inFlight: Promise<void> | null = null;
    let disposed = false;

    const load = (): Promise<void> => {
        if (inFlight) return inFlight;
        if (Object.keys(components.value).length > 0) {
            return Promise.resolve();
        }

        isLoading.value = true;
        errorMessage.value = null;
        inFlight = (async () => {
            try {
                const response = await fetcher('/api/components/registry', {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) {
                    throw new Error(
                        `Failed to load registry: ${response.status} ${response.statusText}`,
                    );
                }

                const data =
                    (await response.json()) as ComponentRegistryResponse;
                if (
                    !data ||
                    typeof data.components !== 'object' ||
                    !matchesStylePrimitiveContract(data.stylePrimitives)
                ) {
                    throw new Error(
                        'Invalid registry contract received from server.',
                    );
                }
                if (disposed) return;

                components.value = data.components;
                aliases.value = data.aliases ?? {};
                if (data.responsive) {
                    configureResponsiveContract(
                        data.responsive,
                        data.components,
                    );
                }
            } catch (error) {
                if (!disposed) {
                    errorMessage.value =
                        error instanceof Error
                            ? error.message
                            : 'An unexpected error occurred while fetching the component registry.';
                }
            } finally {
                if (!disposed) isLoading.value = false;
                inFlight = null;
            }
        })();

        return inFlight;
    };

    const retry = async (): Promise<void> => {
        components.value = {};
        aliases.value = {};
        await load();
    };

    const dispose = () => {
        disposed = true;
    };

    const resolveMetadata = (type: string): ComponentMetadata | null => {
        const canonicalType = aliases.value[type.trim()] ?? type.trim();
        return components.value[canonicalType] ?? null;
    };

    return {
        components,
        aliases,
        isLoading,
        errorMessage,
        load,
        retry,
        dispose,
        resolveMetadata,
    };
}
