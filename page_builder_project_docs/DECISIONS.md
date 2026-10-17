# Decision Log

Ghi quyết định đã được xác nhận. Các ý trong ADR ở trạng thái Proposed chưa phải quyết định cuối cùng.

| Date | ID | Decision | Context / alternatives | Consequences | Owner |
|---|---|---|---|---|---|
| 2026-10-16 | D-001 | Modular Monolith Architecture | Deploy single package on resource-constrained servers | Clean domain isolation; low operational overhead | User / Architect |
| 2026-10-16 | D-002 | PostgreSQL 18 with JSONB for Documents | High performance relational integrity + JSONB indexing | Relational tables for meta/audit; validated JSONB trees | User / Architect |
| 2026-10-16 | D-003 | Laravel 13 + Vue 3 + Inertia + TypeScript + Tailwind v4 | Official supported starter kit, SPA feel with server routing | Fast DX, type safety, low bundle size | User / Architect |
| 2026-10-16 | D-004 | Database `page_builder_dev` on Local PostgreSQL | Isolated database for project development | Zero impact on other local databases; clean baseline | User / Architect |
| 2026-10-16 | D-005 | Safe Health Check Endpoint (`/api/health`) | Minimal status payload, no secrets or SQL leaks | Resilient monitoring without exposing internal attack surface | User / Architect |
| 2026-10-16 | D-006 | Owner Invariant & Transactional Membership Sync | Creating a website must atomically generate an owner membership | Guaranteed owner invariant; role escalation prevented via API rules | User / Architect |
| 2026-10-16 | D-007 | Composite Unique Slug for Pages `(website_id, slug)` | Allow identical page slugs across different websites | Matches real-world CMS requirements; enforced at DB constraint level | User / Architect |
| 2026-10-16 | D-008 | Strict 404 IDOR Abort on Route Scoping Mismatch | Return 404 instead of 403 on cross-website resource mismatches | Conceals resource existence across website boundaries; prevents enumeration | User / Architect |
| 2026-10-17 | D-009 | Document JSON Schema v1 Specification | Unified root node tree structure with strict schemaVersion | Versioned documents, backward compatibility, clear migration pathway | User / Architect |
| 2026-10-17 | D-010 | 12 Core Component Allowlist with Canonical Aliases | Restrict tree nodes to 12 allowed types with alias support (Rich Text, Card Grid) | Prevents arbitrary component injection; robust editor interop | User / Architect |
| 2026-10-17 | D-011 | Server-Side Recursive Validator & Hard Limits | Enforce 512KB payload, 10 tree depth, 250 nodes, 50 children per node | Prevents ReDoS, stack overflow, DB bloat on 2GB RAM instances | User / Architect |
| 2026-10-17 | D-012 | Symfony HTML Sanitizer for RichText XSS Prevention | Use standard W3C parser `symfony/html-sanitizer` over fragile regex | Bulletproof XSS mitigation (strips scripts, event handlers, javascript:/data: URIs) | User / Architect |
| 2026-10-17 | D-013 | Separation of Page Metadata Route (`/edit`) and Visual Editor (`/builder`) | Keep `/edit` for page metadata form and create dedicated full-screen `/builder` (alias `/editor`) | Preserves Phase 02 metadata flow; provides dedicated SaaS canvas layout | User / Architect |
| 2026-10-17 | D-014 | Viewport Switcher Architecture (Desktop 1280px, Tablet 768px, Mobile 375px) | Centralized presets config in `editor.ts` with ARIA radiogroup | Smooth frame transitions without modifying document schema or DB | User / Architect |
| 2026-10-17 | D-015 | Dynamic API Component Library Loading with Boundary Validation | Consume `GET /api/components/registry` dynamically with loading skeleton and error retry | Zero hardcoded list in frontend; single source of truth in backend registry | User / Architect |
| 2026-10-17 | D-016 | Native HTML5 Drag-and-Drop with Accessible Click-to-Insert | Avoid heavy DnD dependencies; use browser native DnD API + click-to-insert | Zero bundle bloat (<1KB); guaranteed accessibility for keyboard users | User / Architect |
| 2026-10-17 | D-017 | Bounded Immutable Snapshot Stack for Undo/Redo | Document size <=250 nodes (20-50KB); deep-clone stack capped at 30 entries | Zero mutation/inversion bugs, <0.1ms cloning time, <1MB RAM client memory | User / Architect |
| 2026-10-17 | D-018 | Responsive Styling Contract and Schema v1 Compatibility | Add optional `styles: { tablet?: {...}, mobile?: {...} }` with strict presentational allowlist | Preserves Schema v1 without version bump; deterministic cascade; backend validated | User / Architect |
| 2026-10-17 | D-019 | Media Asset Storage on Public Disk with Website Partitioning | Local storage in storage/app/public/media/{website_id}/ served by Nginx symlink | Zero PHP memory overhead for image serving; strict tenant isolation on disk; low RAM footprint | User / Architect |
| 2026-10-17 | D-020 | Canonical Image URL Persistence (`src`) with Schema v1 Compatibility | Store relative URL (/storage/media/...) in existing Image.src prop; no schema version bump | Preserves Document Schema v1; 100% backward compatibility; zero changes to ImageRenderer | User / Architect |
| 2026-10-17 | D-021 | Strict Raster-Only Media Validation and Initial SVG Prohibition | Accept JPEG, PNG, WebP, GIF up to 5MB / 4096px; reject SVG, scripts, and spoofed extensions via finfo | Completely mitigates SVG XSS / XML attacks; lightweight binary verification without GD re-encoding spikes | User / Architect |
| 2026-10-17 | D-022 | Reference Guard and Soft Deletion for Media Asset Lifecycle | Check pages.document_json for image URL references before allowing deletion | Prevents 404 broken images on published/draft pages; soft-deletes DB record upon deletion | User / Architect |

## D-018 — Responsive Styling Contract and Document Schema v1 Compatibility
- **Date:** 2026-10-17
- **Status:** Proposed
- **Context:** Visual page builder requires viewport-specific styling overrides (Tablet 768px, Mobile 375px) while maintaining the 2GB server budget, preserving 100% backward compatibility with existing Schema v1 documents, and avoiding an unnecessary schema version bump.
- **Options considered:**
  1. *Schema Version Bump (v2):* Breaking change requiring DB migrations, revision transformations, and Dual-Schema validator support. Overkill for an optional presentational feature.
  2. *CSS String Injections (`customCss`):* Arbitrary CSS text area per component. High security risk, impossible to validate on server, unmaintainable.
  3. *Optional Structured Node Dictionary (`styles: { tablet?: {...}, mobile?: {...} }`):* Recommended. Maintains `schemaVersion: 1`, strictly typed against canonical component registry schemas, rejects structural and content overrides, enables deterministic cascade (Desktop -> Tablet -> Mobile).
- **Decision:** (Proposed for approval at Sprint 1 Gate A)
  1. Adopt optional `styles` dictionary on `DocumentNode`. Desktop values remain the root of the cascade in `props`.
  2. Permitted viewports in `styles` are strictly `tablet` and `mobile` (rejected if `desktop` or arbitrary keys are submitted).
  3. Mobile inherits Tablet when Mobile has no override (`mobile ?? tablet ?? desktop`). For published static CSS parity, the compiler evaluates the effective value at each breakpoint and emits standard mobile-first Tailwind utilities: base class for Mobile, `md:` for Tablet (if different from Mobile), and `lg:` for Desktop (if different from Tablet).
  4. Columns and CardGrid constraints: Retain canonical `[2, 3, 4]` constraint for both base `props` and responsive overrides; `1` is not permitted in the data contract (renderers already auto-collapse to 1 column at narrow screens).
  5. Component `display`: Not stored in base `props` (all 12 components are implicitly visible by default). Permitted in `styles` as `'visible' | 'hidden'`. Centralized handling in `NodeRenderer.vue` prevents disappearing unselectable elements in editor mode.
  6. PHP JSON Decoding: Safe handling of `{}` vs `[]` by pruning empty arrays without error; non-empty list arrays are rejected with `invalid_styles_format`.
  7. Node Duplication: `cloneNodeWithNewIds()` updated to preserve `styles` with fresh IDs for child nodes.
- **Consequences:** Existing documents without `styles` remain 100% valid. Zero data loss when normalising. Exact parity between editor canvas simulation and published CSS.
- **Evidence / links:** `PHASE_06_BA_TECHNICAL_PLAN.md` Section 3; `app/Domains/DocumentEngine/Validation/DocumentValidator.php`.

## D-019 — Media Asset Storage on Public Disk with Website Partitioning
- **Date:** 2026-10-17
- **Status:** Proposed — Pending Approval
- **Context:** The Page Builder requires image storage on a low-resource server (~2GB RAM, 2 vCPUs) running Nginx and PHP-FPM. Serving images through PHP-FPM controller streaming consumes scarce worker processes and memory.
- **Options considered:**
  1. *Private disk with streaming controller (`Storage::disk('private')->response(...)`):* Protects assets behind auth, but forces every image load through PHP-FPM workers, exhausting memory and server connections under traffic.
  2. *Cloud Object Storage (S3 / Cloudflare R2):* Offloads storage, but introduces external dependencies, network latency, API credentials, and contradicts the self-contained low-resource architecture.
  3. *Public local disk with Nginx symlink (`storage/app/public/media/{website_id}/`):* Recommended. Nginx serves images directly with zero PHP execution; directories are partitioned per website.
- **Decision:** (Proposed for approval at Sprint 2 Gate A)
  1. Store media assets on Laravel's `public` disk under `media/{website_id}/{uuid}.{ext}`.
  2. Leverage the standard `php artisan storage:link` symlink to `public/storage`.
  3. Disable PHP script execution within the `/storage/` directory at the Nginx configuration level.
- **Consequences:** Near-zero CPU/RAM overhead for static asset delivery; clean multi-tenant partition on disk; public URLs are guess-resistant due to UUIDv4/UUIDv7 naming.
- **Evidence / links:** `PHASE_06_SPRINT_2_BA_TECHNICAL_PLAN.md` Section 4 & 5; `config/filesystems.php`.

## D-020 — Canonical Image URL Persistence (`src`) with Document Schema v1 Compatibility
- **Date:** 2026-10-17
- **Status:** Proposed — Pending Approval
- **Context:** The `Image` component in `DocumentSchema v1` expects a `src` prop (`isUrl: true`, max 2048). Introducing a Media Library requires deciding whether to store database foreign keys (`mediaAssetId`), composite objects, or standard URLs.
- **Options considered:**
  1. *Schema Version Bump (v2) with `mediaAssetId`:* Storing asset IDs breaks existing documents with external URLs and requires schema migration, dual-engine validation, and dynamic DB lookups on every page render.
  2. *Hybrid props (`mediaAssetId` + `src`):* Creates redundant dual sources of truth and synchronisation bugs if an asset is replaced or updated.
  3. *Preserve Canonical `src` Prop (Relative Public URL):* Recommended. Store the relative storage URL (e.g. `/storage/media/1/{uuid}.jpg`) in the existing `src` prop. External URLs remain fully supported.
- **Decision:** (Proposed for approval at Sprint 2 Gate A)
  1. Retain `DocumentSchema v1` with zero modifications to `ComponentRegistry` or `DocumentValidator`.
  2. Media Library modal writes the selected asset's relative public URL into `src` and auto-populates `alt` if currently empty.
  3. Existing external image URLs (e.g. Unsplash, external CDNs) remain valid and directly editable in the Inspector.
- **Consequences:** 100% backward and forward compatibility; zero changes to `ImageRenderer.vue` or `HtmlSanitizer.php`; static site generation in Phase 08 can consume URLs directly.
- **Evidence / links:** `PHASE_06_SPRINT_2_BA_TECHNICAL_PLAN.md` Section 7; `app/Domains/DocumentEngine/Registry/ComponentRegistry.php`.

## D-021 — Strict Raster-Only Media Validation and Initial SVG Prohibition
- **Date:** 2026-10-17
- **Status:** Proposed — Pending Approval
- **Context:** User uploads introduce security vectors including executable payloads disguised as images, XML/SVG-based Cross-Site Scripting (XSS), server-side memory exhaustion (decompression bombs), and ReDoS.
- **Options considered:**
  1. *Permit SVG with XML Sanitization (e.g. `enshrined/svg-sanitize`):* Allows vector graphics, but XML sanitizers are notoriously complex, have known bypasses, and add third-party dependency bloat.
  2. *Full GD/Imagick Synchronous Re-encoding:* Strips malicious payloads by re-saving every image, but spikes RAM/CPU on a 2GB VPS, causing 502/504 errors on concurrent uploads.
  3. *Strict Binary Magic-Byte Sniffing (`finfo`) for Raster Formats Only:* Recommended. Allow only JPEG, PNG, WebP, GIF; completely prohibit SVG; cap file size at 5MB and dimensions at 4096px; read dimensions via lightweight header parsing (`getimagesize()`).
- **Decision:** (Proposed for approval at Sprint 2 Gate A)
  1. Enforce server-side MIME detection using PHP `finfo_file` (magic bytes). Reject uploaded extension/header claims.
  2. Prohibit SVG (`image/svg+xml`) in initial release to eliminate XML/XSS attack vectors.
  3. Enforce maximum file size of 5 MB (5,242,880 bytes).
  4. Enforce dimension bounds (max 4096px, min 16px) using `getimagesize()` (header parse only, <100KB RAM).
  5. Store files under cryptographically random UUID filenames, discarding original user-provided filenames on disk.
- **Consequences:** High security posture with zero external libraries; zero peak memory spikes; no XSS vectors via SVG.
- **Evidence / links:** `PHASE_06_SPRINT_2_BA_TECHNICAL_PLAN.md` Section 4.3 & 5.2.

## D-022 — Reference Guard and Soft Deletion for Media Asset Lifecycle
- **Date:** 2026-10-17
- **Status:** Proposed — Pending Approval
- **Context:** Deleting an image asset that is currently used in published or draft pages results in broken UI (HTTP 404). Editors need protection against accidental deletion of in-use media.
- **Options considered:**
  1. *Cascade / Unrestricted Deletion:* Immediately remove file and DB record regardless of usage. Results in broken live pages and poor user experience.
  2. *Strict Reference Blocking:* Scan website pages (`pages.document_json`) for asset storage path occurrences. If referenced, block deletion with HTTP 422 and return referencing page titles. If unreferenced, soft-delete record.
- **Decision:** (Proposed for approval at Sprint 2 Gate A)
  1. Implement `MediaUsageScanner` service that searches `pages.document_json` for matching asset storage paths within the same website.
  2. Deletion endpoint checks usage before proceeding: if referenced, aborts with HTTP 422 (`media_in_use`) and a list of referencing pages.
  3. If unreferenced, perform soft delete (`deleted_at`) on the `MediaAsset` record within a database transaction with row locking (`lockForUpdate()`). Physical unlinking is deferred to an idempotent scheduled command (`media:prune-unreferenced`) after a configurable grace period (default 24 hours) with a final authoritative usage re-scan before file deletion.
- **Consequences:** Accidental breaking of live page layouts is prevented; TOCTOU race conditions between concurrent page saves and asset deletions are eliminated; clear error messaging guides users if media is referenced or unparseable; physical files are safely retained during the grace period; and physical deletion occurs only after final usage confirmation.
- **Evidence / links:** `PHASE_06_SPRINT_2_BA_TECHNICAL_PLAN.md` Section 3.2.D & 8.1; `app/Console/Commands/PruneUnreferencedMediaCommand.php`.

## Template
### D-XXX — Short title
- **Date:**
- **Status:** Proposed / Accepted / Rejected / Superseded
- **Context:**
- **Options considered:**
- **Decision:**
- **Consequences:**
- **Evidence / links:**

