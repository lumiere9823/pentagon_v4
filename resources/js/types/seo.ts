export interface PageSeoMetadata {
    meta_title?: string | null;
    meta_description?: string | null;
    canonical_url?: string | null;
    og_title?: string | null;
    og_description?: string | null;
    og_image_url?: string | null;
    twitter_card?: 'summary' | 'summary_large_image';
    noindex?: boolean;
    nofollow?: boolean;
    schema_type?: 'WebPage' | 'Article' | 'Organization';
    structured_data_json?: Record<string, unknown> | null;
}
