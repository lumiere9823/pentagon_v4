# Phase 06 — Sprint 2 BA & Technical Plan: Media Library

**Document Version:** 1.0.0  
**Date:** 2026-10-17  
**Status:** Gate A Review (Planning Only — No Application Code Modified)  
**Author:** Principal Software Architect, Senior Laravel/Vue Engineer, Technical Business Analyst  
**Repository:** `D:\pentagon`  
**Current Branch:** `master`  
**Baseline Commit:** `7cd7c11` (Sprint 1 Responsive Styling Accepted)  
**Document Schema:** `version: 1` (Preserved)  
**Target Deployment:** Nginx + PHP-FPM on Low-Resource Linux VPS (~2GB RAM, 2 vCPUs)  

---

## 1. Executive Summary & Objective

Sprint 2 delivers the **Media Library Engine** for the Page Builder.

Currently, the Page Builder includes an `Image` component (`app/Domains/DocumentEngine/Registry/ComponentRegistry.php`, `ImageRenderer.vue`), but editors must manually type or paste external image URLs into a generic text input (`InspectorPanel.vue`). There is no file upload mechanism, no media repository, no image browsing UI, and no storage tracking per website.

### Objectives for Sprint 2
1. **Website-Isolated Asset Management:** Allow website Owners and Editors to upload, browse, preview, search, and manage images scoped strictly to their website.
2. **Seamless Visual Editor Integration:** Connect the Media Library to the `Image` component inspector via a responsive modal/picker without altering `DocumentSchema v1` or breaking legacy direct URLs.
3. **Hardened Low-Resource Security:** Implement server-side binary MIME sniffing (`finfo`), safe filename sanitization, zero execution vectors (prohibit SVG initially), and static asset delivery via Nginx symlink to eliminate PHP-FPM memory consumption during image serving.
4. **Reference-Aware Lifecycle:** Prevent accidental deletion of images actively referenced in page documents.

---

## 2. Current-State Discovery & Repository Findings

A comprehensive inspection of `D:\pentagon` at commit `7cd7c11` establishes the following baseline:

| Domain | Current Implementation Status in Repository | Evidence / File Reference |
|---|---|---|
| **Image Component Definition** | `Image` exists in canonical registry with props `src` (required string, max 2048, `isUrl: true`), `alt` (string, max 255), `caption` (string, max 255), `aspectRatio` (enum: `auto`, `16:9`, `4:3`, `1:1`). | `app/Domains/DocumentEngine/Registry/ComponentRegistry.php:194-223` |
| **Image Component Validation** | `DocumentValidator.php` validates `src` using `HtmlSanitizer::isSafeUrl()`. Relative URLs (e.g. `/storage/media/...`) and absolute `http/https` URLs are allowed. Non-safe protocols (`javascript:`, `data:`, control chars) are rejected. | `app/Domains/DocumentEngine/Sanitization/HtmlSanitizer.php:34-68` |
| **Image Renderer** | `ImageRenderer.vue` renders `<img :src="src" :alt="alt" ... loading="lazy" />`. If `src` is missing or fails to load (`@error`), it displays a fallback placeholder with `ImageIcon`. | `resources/js/components/editor/canvas/renderers/ImageRenderer.vue` |
| **Inspector Controls** | `InspectorPanel.vue` renders a single text input with `LinkIcon` for any schema where `isUrl: true`. There is no media picker, upload button, or library integration. | `resources/js/components/editor/InspectorPanel.vue:567-593` |
| **Filesystem Configuration** | `config/filesystems.php` configures `local` (`storage/app/private`) and `public` (`storage/app/public` with URL `/storage`). `storage:link` links `public/storage` $\to$ `storage/app/public`. | `config/filesystems.php:33-48` |
| **Database & Models** | Migrations exist for `users`, `cache`, `jobs`, `passkeys`, `websites`, `website_memberships`, `pages`. No `media_assets` table or model exists. | `database/migrations/`, `app/Models/` |
| **Authorization Model** | Multi-tenant isolation is enforced at `Website` level. Roles: `owner`, `editor`, `viewer`. `WebsiteMembership` links users to websites. `PagePolicy` grants write actions to `[owner, editor]` and read to `[viewer]`. Cross-website scoping mismatches abort with 404. | `app/Models/WebsiteMembership.php`, `app/Policies/PagePolicy.php` |
| **PHP Environment** | PHP 8.3 with extensions `fileinfo`, `gd`, `exif`, `mbstring`, `pdo_pgsql`, `openssl`. No heavy imaging libraries (`intervention/image` or `spatie/laravel-medialibrary`) are installed. | CLI inspection `php -m`, `composer.json` |

---

## 3. Business Analysis Discovery & Functional Requirements

### 3.1 User Workflows

```
User in Editor (Image Selected)
       │
       ▼
[Click "Choose from Library"] ──────► Opens Media Library Modal
                                              │
                ┌─────────────────────────────┴─────────────────────────────┐
                ▼                                                           ▼
       [Tab: Browse Library]                                       [Tab: Upload New]
                │                                                           │
        • Search & Filter                                           • Drag & Drop Zone
        • Select Asset Card                                         • Client pre-validation
        • Preview Details (Size, Dims)                              • Upload Progress Bar
                │                                                           │
                └─────────────────────────────┬─────────────────────────────┘
                                              ▼
                                    [Click "Select Image"]
                                              │
                                              ▼
                             • Sets `src = asset.url`
                             • Auto-fills `alt` if empty
                             • Preserves aspectRatio / caption
                             • Closes Modal (Zero page reload)
```

### 3.2 Feature Specifications

#### A. Media Upload Workflow
1. **Trigger Points:** 
   - Primary: Modal opened from the Visual Editor Inspector when editing an `Image` component.
   - Secondary: Standalone Media Library view in Website Management (deferred to future sprint, initial focus is editor modal).
2. **File Restrictions:**
   - Supported Formats: `image/jpeg` (`.jpg`, `.jpeg`), `image/png` (`.png`), `image/webp` (`.webp`), `image/gif` (`.gif`).
   - Prohibited Formats: SVG (`.svg` — rejected due to embedded script/XSS risks), executable files (`.php`, `.phtml`, `.html`, `.exe`, `.js`), archives, videos.
   - Max File Size: **5 MB** (5,242,880 bytes).
   - Dimension Bounds: Maximum 4096 $\times$ 4096 pixels, minimum 16 $\times$ 16 pixels.
   - Batch Limit: Single-file upload initially to respect 2GB RAM budget (expandable to multi-file in a later iteration).
3. **Upload UX & Interaction:**
   - Drag-and-drop target with click-to-browse fallback.
   - Immediate client-side validation (file size, extension) before transmitting bytes.
   - Visual progress bar / upload state with cancel capability.
   - Clear error notifications for network failures, oversized files, or MIME rejections.

#### B. Media Browsing and Selection Workflow
1. **Grid Interface:**
   - Responsive thumbnail grid (square aspect ratio, object-cover).
   - Displays file thumbnail, original filename, dimensions (e.g. `1920x1080`), and byte size formatted (e.g. `1.2 MB`).
2. **Search & Pagination:**
   - Search input filtering by `original_filename` or `alt_text` (debounced 300ms).
   - Pagination: 24 items per page (standard grid $4 \times 6$ or $3 \times 8$).
   - Filter by MIME type category (All, JPEG, PNG, WEBP).
3. **Selection Details Pane:**
   - Clicking an image displays a right-hand sidebar/footer details card showing high-res preview, full filename, dimensions, file size, uploaded date, and editable `alt_text`.
4. **Editor State Preservation:**
   - Opening and closing the Media Library is entirely client-side; no route navigation or full page reload occurs.
   - The editor document, active selection, and undo/redo history remain 100% intact.

#### C. Image Component Integration & Schema Preservation
1. **Property Mapping:**
   - When an asset is selected, the modal emits the chosen asset to `InspectorPanel.vue`.
   - `src` is updated to the asset's relative public URL (e.g. `/storage/media/1/550e8400-e29b-41d4-a716-446655440000.webp`).
   - If the component's `alt` prop is currently blank, it is automatically populated with the asset's `alt_text`.
   - Existing presentation properties (`aspectRatio`, responsive styles, `display`) are untouched.
2. **Dual-Mode Input:**
   - Editors retain the ability to manually enter or override an external image URL (e.g. Unsplash, external CDN) directly in the text field. The Media Library serves as an enhanced picker, not an exclusionary constraint.

#### D. Media Lifecycle & Reference Integrity
1. **Deletion Policy:**
   - Deleting a media asset actively used in published or draft pages causes broken images (HTTP 404).
   - **Reference Check Rule:** Prior to executing a media asset deletion, the server scans the current website's pages (`pages.document_json`) for occurrences of the asset's storage URL.
   - If referenced: The deletion request is **blocked** with HTTP 422 (`media_in_use`), returning the list of page titles currently referencing the asset.
   - If unreferenced: Soft delete the database record (`deleted_at`), and delete the physical file from disk (or retain for 30-day grace period; see Decision D-022).

---

## 4. Security, Authorization & Tenancy

### 4.1 Tenancy & Isolation
- **Website Scoping:** Media assets belong strictly to a single `Website` (`website_id`).
- **Storage Path Isolation:** Files are partitioned on disk by website ID:  
  `storage/app/public/media/{website_id}/{uuid}.{ext}`
- **IDOR Protection:** Every endpoint resolves media through the website scope:  
  `$website->mediaAssets()->findOrFail($mediaId)`. Any access attempt with a mismatched `website_id` aborts with `404 Not Found`.

### 4.2 Role-Based Access Control (RBAC)
Reuses the existing `WebsiteMembership` role hierarchy:

| Operation | `OWNER` | `EDITOR` | `VIEWER` | Policy Rule |
|---|:---:|:---:|:---:|---|
| **Browse / View Media** | Allowed | Allowed | Allowed | `viewAny(User, Website)`: `$website->hasMember($user)` |
| **Upload Media** | Allowed | Allowed | **DENIED** | `create(User, Website)`: `$website->hasMember($user, ['owner', 'editor'])` |
| **Update Metadata (Alt Text)** | Allowed | Allowed | **DENIED** | `update(User, MediaAsset)`: `$website->hasMember($user, ['owner', 'editor'])` |
| **Delete Media** | Allowed | Allowed | **DENIED** | `delete(User, MediaAsset)`: `$website->hasMember($user, ['owner', 'editor'])` |

### 4.3 Upload & File Storage Hardening
1. **Binary MIME Sniffing:**
   - Client-provided headers (`Content-Type`) and file extensions are completely ignored for validation.
   - Validation uses PHP `finfo_file(FILEINFO_MIME_TYPE, ...)` to inspect binary magic bytes.
   - Allowed MIME types: `image/jpeg`, `image/png`, `image/webp`, `image/gif`.
2. **SVG Prohibition (Initial Release):**
   - SVG is XML and permits embedded `<script>`, external resource loading, and DOM clobbering vectors.
   - Prohibiting SVG eliminates an entire class of XSS vectors without requiring heavy XML sanitization libraries.
3. **Safe Storage Naming:**
   - The user's original filename is never used on disk.
   - The stored filename is generated via `Str::uuid7()` or `Str::uuid4()` with the canonical extension determined by MIME detection:  
     `$storageName = Str::uuid()->toString() . '.' . $extension;`
   - Path traversal characters (`../`, `..\\`, null bytes) are impossible.
4. **Static File Serving (Zero PHP Execution):**
   - Files are stored in `storage/app/public/media/{website_id}/` and served via Nginx through the `public/storage` symlink.
   - Nginx configuration must disable script execution in `/storage/` (e.g. `location ^~ /storage/ { ... location ~ \.php$ { deny all; } }`).
   - Image requests bypass PHP-FPM completely, consuming 0MB of PHP worker memory.

---

## 5. Storage Architecture & 2GB RAM Constraints

### 5.1 Filesystem Layout

```
storage/
└── app/
    ├── private/               # Internal non-public documents
    └── public/                # Publicly accessible via symlink
        └── media/
            ├── 1/             # Website ID 1
            │   ├── e3b0c442-98fc-1c14-9af3-4c7a6e1f0001.webp
            │   └── e3b0c442-98fc-1c14-9af3-4c7a6e1f0002.jpg
            └── 2/             # Website ID 2
                └── f4c1d553-09ad-2d25-0ba4-5d8b7f2a0001.png
```

### 5.2 Low-Resource Optimizations
1. **No Synchronous Heavy Resizing:**
   - Full-image re-encoding with GD/Imagick for multiple variants (e.g. 10 thumbnail sizes) causes severe CPU and memory spikes on a 2GB VPS.
   - In Sprint 2, the original image is stored as-is (within 5MB / 4096px bounds).
   - Dimensions (`width`, `height`) are read using `getimagesize()`, which parses file headers and consumes <100KB of RAM.
2. **Low Peak Memory Consumption:**
   - An upload request of 5MB consumes ~6–10MB of PHP worker memory.
   - With 2 PHP-FPM workers, total peak RAM for simultaneous uploads remains well within 64MB.
3. **Reverse Proxy & PHP Settings:**
   - Nginx: `client_max_body_size 10M;`
   - PHP-FPM: `upload_max_filesize = 8M; post_max_size = 10M; memory_limit = 128M;`

---

## 6. Data Model & Database Design

### 6.1 Table Schema: `media_assets`

```sql
CREATE TABLE media_assets (
    id BIGSERIAL PRIMARY KEY,
    website_id BIGINT NOT NULL REFERENCES websites(id) ON DELETE CASCADE,
    storage_disk VARCHAR(32) NOT NULL DEFAULT 'public',
    storage_path VARCHAR(512) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes BIGINT NOT NULL,
    width INT NULL,
    height INT NULL,
    alt_text VARCHAR(255) NULL,
    uploaded_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP WITH TIME ZONE NULL
);

-- Index for fast paginated listing by website ordered by most recent
CREATE INDEX idx_media_assets_website_created ON media_assets(website_id, created_at DESC) 
WHERE deleted_at IS NULL;

-- Index for search filtering by filename within a website
CREATE INDEX idx_media_assets_website_search ON media_assets(website_id, original_filename) 
WHERE deleted_at IS NULL;
```

### 6.2 Eloquent Model: `App\Models\MediaAsset`
- Relationships:
  - `website(): BelongsTo<Website, MediaAsset>`
  - `uploader(): BelongsTo<User, MediaAsset>`
- Computed Accessors:
  - `url`: Returns relative public URL `/storage/{storage_path}` or `Storage::disk($this->storage_disk)->url($this->storage_path)`.
  - `formatted_size`: Returns human-readable size (e.g. `1.4 MB`).

---

## 7. Document Schema Compatibility & Image Reference Strategy

### 7.1 Evaluation of Image Reference Options

| Evaluation Criterion | Option A: Direct URL in `src` (Recommended) | Option B: Store `mediaAssetId` Only | Option C: Hybrid (`src` + `mediaAssetId`) |
|---|---|---|---|
| **Document Schema Version** | **Preserves `schemaVersion: 1`** with zero modifications. | Requires Schema bump or breaking migration. | Requires Schema v1 prop addition in `propSchemas`. |
| **Backward Compatibility** | **100% compatible.** Existing documents with direct URLs render identically. | Incompatible with legacy direct URLs without synthetic records. | Compatible, but introduces redundant source of truth. |
| **Renderer Complexity** | **Zero changes** to `ImageRenderer.vue` (`<img :src="src" />`). | Requires runtime URL resolution query or store lookup. | Minimal, but must resolve precedence between `id` and `src`. |
| **Phase 08 Static Publisher** | Direct string URL can be extracted and written into static HTML immediately. | Requires DB join for every image node during static build. | Direct string URL used; `mediaAssetId` ignored by publisher. |
| **Portability** | Moving website to new domain only requires updating asset prefix. | Requires re-resolving media mapping in new environment. | Dual handling. |

### 7.2 Architectural Recommendation: Option A (Direct URL with Reference Tracking)
- The `Image` component maintains its existing canonical prop signature:
  - `src: string` (Relative path `/storage/media/...` or absolute URL)
  - `alt: string`
  - `caption: string`
  - `aspectRatio: string`
- The Media Library acts as an **Authoritative Asset Selector**: selecting an asset copies its relative public URL into `src` and populates `alt`.
- Server-side reference checks for deletion scan `pages.document_json` for matching `storage_path` substrings, guaranteeing reference safety without requiring an artificial schema change.

---

## 8. API & Route Design

All media routes reside in `routes/web.php` within the `['auth', 'verified']` middleware group:

```php
Route::middleware(['auth', 'verified'])->group(function () {
    // Media Library Endpoints (Scoped by Website)
    Route::get('websites/{website}/media', [MediaAssetController::class, 'index'])
        ->name('websites.media.index');
    Route::post('websites/{website}/media', [MediaAssetController::class, 'store'])
        ->name('websites.media.store');
    Route::put('websites/{website}/media/{mediaAsset}', [MediaAssetController::class, 'update'])
        ->name('websites.media.update');
    Route::delete('websites/{website}/media/{mediaAsset}', [MediaAssetController::class, 'destroy'])
        ->name('websites.media.destroy');
});
```

### 8.1 API Request & Response Contracts

#### 1. `GET /websites/{website}/media`
- **Query Parameters:**
  - `page`: integer (default: 1)
  - `per_page`: integer (default: 24, max: 48)
  - `search`: string (optional, searches `original_filename` and `alt_text`)
  - `type`: string (optional: `image/jpeg`, `image/png`, `image/webp`, `image/gif`)
- **Response Shape (200 OK):**
```json
{
  "data": [
    {
      "id": 42,
      "original_filename": "hero-banner.jpg",
      "url": "/storage/media/1/a1b2c3d4-e5f6-7890-1234-56789abcdef0.jpg",
      "mime_type": "image/jpeg",
      "size_bytes": 1048576,
      "formatted_size": "1.00 MB",
      "width": 1920,
      "height": 1080,
      "alt_text": "Modern office workspace",
      "created_at": "2026-10-17T12:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 24,
    "total": 52
  }
}
```

#### 2. `POST /websites/{website}/media`
- **Request:** `multipart/form-data`
  - `file`: Required, binary file (max 5120 KB, mime: `image/jpeg,image/png,image/webp,image/gif`).
  - `alt_text`: Optional string, max 255 chars.
- **Response Shape (201 Created):**
  - Returns single asset object matching index item.
- **Error Response (422 Unprocessable Content):**
```json
{
  "message": "The file must be an image of type: jpeg, png, webp, gif.",
  "errors": {
    "file": ["The file must be an image of type: jpeg, png, webp, gif."]
  }
}
```

#### 3. `DELETE /websites/{website}/media/{mediaAsset}`
- **Reference Check Enforcement:**
  - If asset is referenced in any page of the website: Returns `422 Unprocessable Content`:
```json
{
  "message": "Cannot delete media asset because it is currently used on 2 page(s).",
  "error_code": "media_in_use",
  "referencing_pages": [
    { "id": 10, "title": "Home Page", "slug": "home" },
    { "id": 12, "title": "About Us", "slug": "about-us" }
  ]
}
```
  - If unreferenced: Executes soft delete, returns `200 OK` (`{ "success": true }`).

---

## 9. UI / Frontend Component Design

### 9.1 Component Hierarchy

```
resources/js/components/media/
├── MediaLibraryModal.vue         # Main dialog container (Tabs: Browse & Upload)
├── MediaUploadDropzone.vue       # Drag & drop upload area with progress
├── MediaGrid.vue                 # 24-item thumbnail grid with pagination
├── MediaCard.vue                 # Single image card (thumbnail, badges)
└── MediaDetailsPanel.vue         # Selected item metadata, preview, & delete
```

### 9.2 Inspector Integration (`InspectorPanel.vue`)
- For `Image` component when inspecting property `src`:
  - Provide an inline button: `<Button variant="outline" size="sm" @click="openMediaLibrary">Choose from Library</Button>`.
  - Maintain the existing text input for direct manual URL entry.
  - When image is selected from modal, update `src` and auto-fill `alt` (if empty).
  - Modal handles selection cancellation without modifying document state.

---

## 10. Ordered Implementation Breakdown (Gate B Sprints)

The implementation is broken into 5 sequential, test-driven tasks:

```
Task 1: Database Migration & Model (MediaAsset)
                    │
                    ▼
Task 2: Service Layer & Upload Controller with Binary MIME Validation
                    │
                    ▼
Task 3: Reference Integrity Checker & Deletion Protection
                    │
                    ▼
Task 4: Frontend Media Library Components (Modal, Grid, Upload Dropzone)
                    │
                    ▼
Task 5: InspectorPanel Integration & End-to-End Quality Gates
```

### Task 1 — Data Model & Migration
- Create migration `create_media_assets_table`.
- Create `App\Models\MediaAsset` with casts, relations, and computed accessors.
- Create factory `Database\Factories\MediaAssetFactory`.
- Tests: Model relationship tests, soft delete tests, website scoping.

### Task 2 — Backend Upload & Storage Service
- Implement `App\Domains\Media\Services\MediaStorageService`:
  - Safe file storage using UUID naming in `media/{website_id}/`.
  - Dimension extraction via `getimagesize()`.
  - Binary MIME verification (`finfo`).
- Create `StoreMediaRequest` with strict validation rules.
- Create `MediaAssetPolicy` enforcing RBAC (`owner`, `editor` write; `viewer` read-only).
- Implement `MediaAssetController::store` and `MediaAssetController::index`.
- Tests: Pest feature tests for valid uploads, spoofed MIME rejections, oversized files, and website scoping.

### Task 3 — Metadata Update & Reference-Protected Deletion
- Implement `MediaAssetController::update` (alt text).
- Implement `MediaUsageScanner`: Scans `pages.document_json` for asset storage paths.
- Implement `MediaAssetController::destroy` with reference guard.
- Tests: Pest feature tests for blocked deletion when referenced, allowed deletion when unreferenced, and IDOR prevention.

### Task 4 — Frontend Media Library Components
- Create `MediaLibraryModal.vue`, `MediaUploadDropzone.vue`, `MediaGrid.vue`, `MediaCard.vue`, `MediaDetailsPanel.vue`.
- Implement client-side upload handling with progress indicator.
- Implement search, pagination, and empty/loading states.
- Tests: Vitest tests for component mounting, search debouncing, and selection emission.

### Task 5 — Inspector Integration & Final Quality Gates
- Update `InspectorPanel.vue` to integrate "Choose from Library" action on `Image.src`.
- Wire selection event to update `src` and `alt`.
- Verify viewer read-only behavior (`readOnly: true` disables modal selection and uploads).
- Run full quality gates: Pint, Pest, Biome, vue-tsc, Vitest, Vite build.

---

## 11. Test Matrix & Acceptance Criteria

| Category | Test Scenario | Expected Outcome | Verification Tool |
|---|---|---|:---:|
| **Security & MIME** | Upload valid JPEG, PNG, WebP, GIF | Successfully stored, metadata extracted, 201 Created | Pest Feature Test |
| **Security & MIME** | Upload PHP file disguised as `.jpg` | Rejected with 422 Unprocessable Content | Pest Feature Test |
| **Security & MIME** | Upload SVG file | Rejected with 422 Unprocessable Content | Pest Feature Test |
| **Limits** | Upload file > 5 MB | Rejected with 422 (max size exceeded) | Pest Feature Test |
| **Limits** | Upload image with dimensions > 4096px | Rejected with 422 (max dimension exceeded) | Pest Feature Test |
| **Tenancy / IDOR** | User from Website A attempts to access/delete Media of Website B | 404 Not Found (zero information leakage) | Pest Feature Test |
| **RBAC** | Viewer attempts to upload or delete media | 403 Forbidden | Pest Feature Test |
| **Reference Guard** | Attempt to delete image referenced in a page document | 422 Unprocessable Content with `media_in_use` code | Pest Feature Test |
| **Reference Guard** | Delete unreferenced image | 200 OK, soft deleted | Pest Feature Test |
| **UI Integration** | Select image from Media Library modal in Inspector | `Image.src` updated to relative storage URL | Vitest Component Test |
| **UI Integration** | Legacy document with external direct URL | Renders without modification, direct URL preserved | Vitest & Pest Test |
| **Schema Integrity** | Save document after image selection | Validates cleanly under `DocumentValidator` (`schemaVersion: 1`) | Pest Unit Test |

---

## 12. Architectural Decision Register (Gate A Proposals)

The following four decisions are proposed in `page_builder_project_docs/DECISIONS.md`:

1. **`D-019`:** Media Asset Storage on Laravel Public Disk (`storage/app/public/media/{website_id}`)
2. **`D-020`:** Canonical Image URL Persistence (`src`) with Document Schema v1 Compatibility
3. **`D-021`:** Strict Raster-Only Media Validation and Initial SVG Prohibition
4. **`D-022`:** Reference Guard and Soft Deletion for Media Asset Lifecycle

---

## 13. Gate A Verdict & Recommendation

**Verdict:** **`READY FOR GATE B`**

All requirements, architectural trade-offs, security controls, low-resource deployment constraints, and data contracts have been fully investigated against the verified repository state. No application code has been modified. The sprint is ready for implementation authorization.

---

## 14. Gate B Implementation Summary & Verification Notes

### 14.1 Implementation Status
- **Date Completed:** 2026-10-17
- **Database Migration:** `2026_10_17_150000_create_media_assets_table.php` (applied and reversible).
- **Model & Relationships:** `App\Models\MediaAsset` with SoftDeletes, computed URL (`/storage/...`), human-readable formatted size, and `Website::mediaAssets()` relationship.
- **Backend Services:**
  - `MediaStorageService`: Binary `finfo` magic-byte inspection, header decoding and dimension checks via `getimagesize()` (<100KB RAM overhead), max 5MB, max 4096px bounds, SVG rejection, server-controlled UUID filenames, safe disk/DB failure rollback.
  - `MediaUsageScanner`: Multi-source scanning (pages `document_json`, `page_revisions`, template storage), exact URL path matching, zero substring false positives.
- **Authorization & Scoping:**
  - `MediaAssetPolicy`: Owner and Editor can upload, update alt text, and delete; Viewer has read-only access (403 on write attempts).
  - Cross-website IDOR access strictly aborts with HTTP 404 (zero information leakage).
- **Controllers & Endpoints:**
  - `GET /websites/{website}/media`: Paginated 24/page, search filtering by filename and alt text, MIME type filter, stable ordering (`created_at desc, id desc`).
  - `POST /websites/{website}/media`: Single-file upload, 5MB limit, 201 Created response.
  - `PUT /websites/{website}/media/{mediaAsset}`: Update alt text, 200 OK.
  - `DELETE /websites/{website}/media/{mediaAsset}`: Usage scan guard; blocks with HTTP 422 `media_in_use` if referenced on pages; soft deletes DB record and safely unlinks file if unreferenced.
- **Frontend Components (`resources/js/components/media/`):**
  - `MediaCard.vue`: Thumbnail preview, dimensions, size badge, error fallback.
  - `MediaGrid.vue`: Responsive thumbnail grid, loading skeleton, empty state, pagination controls.
  - `MediaUploadDropzone.vue`: Drag-and-drop zone, file input, client pre-validation (5MB limit, raster only, SVG rejection), upload progress bar.
  - `MediaDetailsPanel.vue`: High-res preview, metadata display, copy public URL button, editable alt text with save button, delete button with confirmation and referencing pages feedback.
  - `MediaLibraryModal.vue`: Radix/shadcn Dialog container with Browse and Upload tabs.
- **Inspector Integration:**
  - `InspectorPanel.vue`: "Choose from Library" button on `Image.src` input.
  - Auto-fills `alt` only when currently empty or unset (preserving existing alt text as required by D-020).
  - Preserves unrelated props and responsive overrides.
  - Preserves Document Schema v1 with zero schema bump.

### 14.2 Deployment Configuration & Security Assumptions
1. **Nginx Public Storage Direct Serving:**
   - In production, Nginx directly serves files from `/storage/` via the symlink `public/storage -> storage/app/public`.
   - Security rule required in server configuration: Disable PHP script execution inside `/storage/`:
     ```nginx
     location ^~ /storage/ {
         # Prevent executing any scripts within user uploads
         location ~ \.php$ {
             deny all;
         }
     }
     ```
2. **Reverse Proxy Upload Limits:**
   - Ensure Nginx `client_max_body_size` is set to at least `10M;` (to accommodate 5MB image uploads with multipart headers).
   - Ensure PHP-FPM `upload_max_filesize = 8M;` and `post_max_size = 10M;`.
3. **Storage Symlink:**
   - Verify `php artisan storage:link` has executed during deployment.

### 14.3 Quality Gates Verification
- **Pint:** `php vendor/bin/pint --test` (PASSED: 0 errors).
- **Backend Tests:** `php artisan test` (121 passed, 548 assertions, 0 failures).
- **Biome Linter/Formatter:** `npm run check` (PASSED: 0 warnings, 0 lint errors).
- **TypeScript Types:** `npm run types:check` (PASSED: 0 errors).
- **Frontend Tests:** `npm run test:frontend` (8 test files passed, 71 passed, 0 failures).
- **Production Build:** `npm run build` (PASSED: built in 6.16s).

