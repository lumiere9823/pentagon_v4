export interface MediaAssetItem {
    id: number;
    original_filename: string;
    url: string;
    mime_type: string;
    size_bytes: number;
    formatted_size: string;
    width: number | null;
    height: number | null;
    alt_text: string | null;
    created_at: string | null;
}

export interface MediaPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface MediaListResponse {
    data: MediaAssetItem[];
    meta: MediaPaginationMeta;
}
