# Pentagon Page Builder — Tài liệu Kỹ thuật Hệ thống (System Reference)

> **Phạm vi:** đây là tài liệu duy nhất của dự án. Nó thay thế toàn bộ bộ tài liệu cũ (PRD, Architecture, Data Model, ADR, Phase Plan, Risk Register, Engineering Rules, Implementation Status, Decisions, Known Issues, Runbook, Resource Budget, các báo cáo audit/closure của từng phase).
>
> **Nguồn sự thật:** mọi mô tả dưới đây được đối chiếu trực tiếp với mã nguồn trong working tree (ngày 2026-10-24). Nếu tài liệu và code mâu thuẫn, **code là chuẩn**. Khi sửa code làm thay đổi hành vi được mô tả ở đây, hãy cập nhật file này trong cùng commit.
>
> **Quy ước:** tên class/hàm/route giữ nguyên tiếng Anh. Mã quyết định `D-0xx` tham chiếu tới Nhật ký quyết định ở [§20](#20-nhật-ký-quyết-định-kiến-trúc-d-001--d-047). Các điểm lệch giữa thiết kế và hiện thực được gom ở [§21](#21-hạn-chế-đã-biết-và-khoảng-trống).

---

## Mục lục

1. [Tổng quan sản phẩm](#1-tổng-quan-sản-phẩm)
2. [Kiến trúc tổng thể](#2-kiến-trúc-tổng-thể)
3. [Document Engine — mô hình tài liệu trang](#3-document-engine--mô-hình-tài-liệu-trang)
4. [Component Registry — 12 component lõi](#4-component-registry--12-component-lõi)
5. [Responsive Styling Engine (D-018)](#5-responsive-styling-engine-d-018)
6. [Page Builder (frontend editor)](#6-page-builder-frontend-editor)
7. [Lưu trữ draft, khoá lạc quan và revision](#7-lưu-trữ-draft-khoá-lạc-quan-và-revision)
8. [Media Library](#8-media-library)
9. [Templates](#9-templates)
10. [Publishing pipeline, release, preview và public delivery](#10-publishing-pipeline-release-preview-và-public-delivery)
11. [SEO, redirect, sitemap, robots](#11-seo-redirect-sitemap-robots)
12. [Forms (nội dung động)](#12-forms-nội-dung-động)
13. [Danh tính, phân quyền và HTTP layer](#13-danh-tính-phân-quyền-và-http-layer)
14. [Mô hình dữ liệu (PostgreSQL)](#14-mô-hình-dữ-liệu-postgresql)
15. [Mô hình đồng thời (concurrency) tổng hợp](#15-mô-hình-đồng-thời-concurrency-tổng-hợp)
16. [Lệnh bảo trì và lịch chạy](#16-lệnh-bảo-trì-và-lịch-chạy)
17. [Vận hành production: deploy, rollback, backup, runbook](#17-vận-hành-production-deploy-rollback-backup-runbook)
18. [Ngân sách tài nguyên (2 GB RAM / 2 vCPU)](#18-ngân-sách-tài-nguyên-2-gb-ram--2-vcpu)
19. [Kiểm thử và quality gates](#19-kiểm-thử-và-quality-gates)
20. [Nhật ký quyết định kiến trúc (D-001 → D-047)](#20-nhật-ký-quyết-định-kiến-trúc-d-001--d-047)
21. [Hạn chế đã biết và khoảng trống](#21-hạn-chế-đã-biết-và-khoảng-trống)
22. [Quy tắc kỹ thuật khi phát triển tiếp](#22-quy-tắc-kỹ-thuật-khi-phát-triển-tiếp)
23. [Phụ lục: bảng tra nhanh](#23-phụ-lục-bảng-tra-nhanh)

---

## 1. Tổng quan sản phẩm

### 1.1 Tầm nhìn

Pentagon là một **Page Builder Admin**. Người quản trị tạo và quản lý website/page bằng giao diện kéo-thả trực quan, không cần viết code cho các thao tác nội dung phổ biến. Trang public:

- được biên dịch thành **HTML tĩnh khi publish** (static-first),
- tải nhanh, chi phí vận hành thấp (chạy được trên VPS 2 GB RAM),
- an toàn (không chạy JavaScript do người dùng nhập, HTML được sanitize),
- **rollback được** ở cả hai cấp: bản nháp (draft) và bản public (release).

### 1.2 Vai trò người dùng

| Vai trò | Quyền chính |
|---|---|
| **Owner** | Toàn quyền website: sửa/xoá website, quản lý thành viên, mọi quyền của Editor. Owner chính (`websites.owner_id`) không thể bị đổi role hoặc bị xoá khỏi website. |
| **Editor** | Tạo/sửa/xoá page, chỉnh nội dung trong builder, autosave, publish, rollback, quản lý media và custom template. |
| **Viewer** | Chỉ đọc: mở builder ở chế độ read-only, preview, xem revision/release/media/template/submission; không được mutation dữ liệu. |

### 1.3 Phạm vi đã hiện thực (MVP)

1. Đăng nhập (Laravel Fortify: đăng ký, reset mật khẩu, xác minh email, 2FA, passkey) và RBAC theo website.
2. Quản lý website, page, slug, trạng thái (`draft` / `published` / `archived`).
3. Editor trực quan: thêm, xoá, sắp xếp, lồng 12 component trong giới hạn schema.
4. Inspector chỉnh nội dung và style, sinh động từ schema của registry.
5. Undo/redo (snapshot, tối đa 30 bước).
6. Xem trước theo viewport Desktop/Tablet/Mobile với style override theo viewport.
7. Autosave (debounce 2 giây) với trạng thái `saved / saving / dirty / error / conflict` và khoá lạc quan.
8. Revision bất biến và rollback bằng cách tạo revision mới.
9. Media library (chỉ ảnh raster), có bảo vệ tham chiếu và dọn dẹp an toàn khi chạy đồng thời.
10. Starter template (5 template hệ thống) và template tuỳ biến theo website.
11. Preview có xác thực và preview link có chữ ký, hết hạn sau 60 phút.
12. Publish ra HTML tĩnh: release bất biến, manifest SHA-256, kích hoạt nguyên tử, rollback release.
13. SEO: meta, canonical, OpenGraph/Twitter, JSON-LD, sitemap.xml, robots.txt, redirect 301 khi đổi slug.
14. Endpoint form submission (honeypot, rate limit, giới hạn payload).
15. Security headers, ma trận phân quyền, chaos test cho publish, health check, script deploy/rollback/backup.

### 1.4 Ngoài phạm vi (non-goals có chủ đích)

- Builder pixel-perfect tự do; chạy JavaScript/CSS tuỳ ý do người dùng nhập.
- Marketplace plugin; component do client tự định nghĩa.
- Cộng tác realtime nhiều người trên một canvas.
- A/B testing, personalization, commerce.
- SSR daemon (Node), microservices, Kubernetes, Redis, Elasticsearch, Octane. Chỉ thêm khi số liệu đo đạc chứng minh cần thiết.

---

## 2. Kiến trúc tổng thể

### 2.1 Stack

| Lớp | Công nghệ |
|---|---|
| Backend | PHP 8.4, **Laravel 13** (modular monolith), Laravel Fortify |
| Frontend | **Vue 3.5** + TypeScript, **Inertia v3** (`@inertiajs/vue3`), Tailwind CSS v4, shadcn-vue (trên `reka-ui`), `@lucide/vue`, `vue-sonner`, Laravel Wayfinder |
| Build | Vite qua `vite-plus` (`vp`) |
| Database | **PostgreSQL 18** (dev/prod; bắt buộc ở production), SQLite `:memory:` cho test |
| Web server | Nginx + PHP-FPM, systemd timer cho scheduler |
| Lưu trữ file | Disk `public` (`storage/app/public`) cho media; disk `local` (`storage/app/private`) cho release HTML |

### 2.2 Sơ đồ logic

```text
┌──────────────────────────── Admin browser ────────────────────────────┐
│ Inertia + Vue 3 SPA                                                    │
│  EditorShell ─┬─ ComponentLibrary (palette, kéo-thả HTML5)             │
│               ├─ EditorCanvas → NodeRenderer (đệ quy) → 12 renderers   │
│               ├─ InspectorPanel (sinh từ schema registry)              │
│               ├─ useDocumentState / useEditorHistory / useAutosave     │
│               └─ Modals: Media, Revisions, Conflict, SEO, Template,    │
│                          PublishSuccess                                │
└──────────────┬─────────────────────────────────────────────────────────┘
               │ fetch JSON (X-XSRF-TOKEN) + Inertia visits
┌──────────────▼──────────── Laravel modular monolith ───────────────────┐
│ HTTP: Controllers mỏng → FormRequest → Policy (IDOR 404)               │
│ Domains:                                                               │
│  DocumentEngine  (Registry, Validator, HtmlSanitizer, Limits)          │
│  Pages           (PagePersistenceService: autosave, revisions)         │
│  Media           (Storage, UsageScanner, ConcurrencyLock)              │
│  Templates       (TemplateInstantiationService)                        │
│  Publishing      (PublishingService, StagingService, HtmlRenderer,     │
│                   ManifestVerifier, PublishingLock)                    │
│  SEO             (PageSeoManager, RedirectManager, SitemapGenerator)   │
│  Forms           (FormSubmissionService)                               │
└──────┬───────────────────────┬──────────────────────────┬──────────────┘
       │                       │                          │
  PostgreSQL 18         storage/app/public/media   storage/app/private/releases
  (jsonb documents,     (ảnh, Nginx phục vụ        (index.html + manifest.json,
   advisory locks)       trực tiếp)                  PHP phục vụ kèm ETag)

Public visitor ──► GET /sites/{website}/{page}  → PublicPageController → index.html của release ACTIVE
               ──► GET /sites/{website}/sitemap.xml | robots.txt
               ──► POST /sites/{website}/forms/{form}  (động, no-store)
               ──► GET /storage/media/...  (Nginx, không qua PHP)
```

### 2.3 Nguyên tắc kiến trúc

1. **Modular monolith.** Một ứng dụng, phân miền ở `app/Domains/*`. Controller mỏng, logic nằm trong domain service. Domain service không cần binding: `AppServiceProvider::register()` để trống, container tự resolve.
2. **Server là nguồn sự thật.** Document JSON từ client không bao giờ được tin; mọi đường ghi đều chạy `DocumentValidator`.
3. **Document là dữ liệu, không phải code.** Chỉ có 12 component trong allowlist. Không có event handler, script, CSS tuỳ ý hay URL scheme nguy hiểm.
4. **Static-first.** Publish biên dịch AST → HTML5 bằng PHP thuần, không có JS phía client trên trang public.
5. **Bất biến và chỉ-thêm (append-only).** Revision và release không bao giờ bị sửa; rollback tạo bản ghi mới.
6. **Đồng thời an toàn bằng PostgreSQL.** Kết hợp advisory lock theo phạm vi transaction, row lock (`FOR UPDATE`), khoá lạc quan (`lock_version`) và partial unique index.
7. **Ngân sách tài nguyên.** Không thêm daemon. Queue/cache/session dùng driver `database`.

`AppServiceProvider::boot()` còn bật: `Date::use(CarbonImmutable::class)`, `DB::prohibitDestructiveCommands()` ở production, và rule mật khẩu production (tối thiểu 12 ký tự, chữ hoa/thường, số, ký hiệu, chưa từng bị lộ).

### 2.4 Vòng đời một trang (end-to-end)

```text
Tạo page (blank hoặc từ template)
   │  pages.document_json = tài liệu rỗng chuẩn | template đã đổi ID
   ▼
Builder: chỉnh sửa trên client (useDocumentState) ──► undo/redo (snapshot)
   │  notifyChange() → debounce 2 s
   ▼
PUT /content {document_json, lock_version, is_autosave, seo_metadata}
   │  validate → quét media → transaction: FOR UPDATE + so lock_version (409 nếu lệch)
   │  → MED advisory locks → ghi draft, lock_version++ → có thể tạo revision (D-028)
   ▼
Preview (auth) hoặc share link có chữ ký 60 phút ──► render previewMode (lỗi node → placeholder)
   ▼
POST /publish
   │  Phase 1 (tx ngắn): PUB lock, validate, tái dùng/tạo revision, MED locks, tạo release PENDING
   │  Phase 2 (không tx): render HTML → staging_{uuid}/ → manifest → verify → rename r_{n}_{hash8}/
   │  Phase 3 (tx ngắn): ACTIVE cũ → SUPERSEDED, release mới → ACTIVE, page.published_revision_id
   ▼
GET /sites/{website}/{page} → đọc index.html, ETag = html_hash, 304 nếu khớp
   ▼
Rollback: draft (revision N+1) | release (chuyển tức thì hoặc biên dịch lại từ revision)
```

---

## 3. Document Engine — mô hình tài liệu trang

Thư mục: `app/Domains/DocumentEngine`. Đây là nguồn sự thật duy nhất cho câu hỏi "thế nào là một tài liệu trang hợp lệ". Mọi đường ghi (lưu page, rollback revision, lưu/sửa template, seeder, publish, render) đều gọi `DocumentValidator::validate()`.

### 3.1 Cấu trúc tài liệu (Document Schema v1)

```json
{
  "schemaVersion": 1,
  "root": {
    "id": "root_section_default",
    "type": "Section",
    "props":  { "padding": "medium", "background": "transparent" },
    "styles": {
      "tablet": { "background": "muted" },
      "mobile": { "padding": "small", "display": "hidden" }
    },
    "children": [
      {
        "id": "node_container_default",
        "type": "Container",
        "props": { "maxWidth": "lg", "centered": true },
        "children": []
      }
    ]
  },
  "pageSettings": { "title": "New Page", "description": "" }
}
```

**Node contract:**

| Trường | Bắt buộc | Quy tắc |
|---|---|---|
| `id` | có | Chuỗi khớp `/^[a-zA-Z0-9_-]{3,64}$/`, **duy nhất trong toàn cây** |
| `type` | có | Một trong 12 type của registry. Alias `"Rich Text"` → `RichText`, `"Card Grid"` → `CardGrid` |
| `props` | có (object) | Validate theo `propSchemas` của component; prop lạ bị từ chối; prop tuỳ chọn thiếu được điền default trong bản sanitized |
| `children` | tuỳ | Chỉ component container mới được có con khác rỗng |
| `styles` | tuỳ | Override theo viewport, chỉ có khoá `tablet`/`mobile` (xem [§5](#5-responsive-styling-engine-d-018)) |

Kiểu TypeScript tương ứng nằm ở `resources/js/types/document.ts`: `DocumentSchema`, `DocumentNode`, `ResponsiveStyleMap`, `PageSettings`, `CoreComponentType`.

**Bất biến cấu trúc:**

- Có đúng một root, và root phải là `Section`, `Container` hoặc `Hero` (`canBeRoot`).
- Ngoài phân biệt container/leaf, **không có ràng buộc type cha → con**. Ví dụ `Section` lồng trong `Columns` là hợp lệ. `Columns.count` không ràng buộc với số con.
- Media chỉ được tham chiếu bằng chuỗi URL trong props, ví dụ `Image.src = "/storage/media/{website_id}/{uuid}.jpg"`. Không có prop dạng asset ID (D-020).

### 3.2 Giới hạn tài nguyên (`DocumentLimits`)

Định nghĩa ở `app/Domains/DocumentEngine/DocumentLimits.php`; phía client là `DOCUMENT_LIMITS` trong `document.ts`. Test `renderer-registry-compat.test.ts` khẳng định hai bên khớp nhau.

| Hằng số | Giá trị | Ý nghĩa |
|---|---|---|
| `MAX_PAYLOAD_BYTES` | 524 288 (512 KB) | Kích thước JSON tối đa |
| `MAX_TREE_DEPTH` | 10 | Độ sâu tối đa (root = 1) |
| `MAX_TOTAL_NODES` | 250 | Tổng số node |
| `MAX_CHILDREN_PER_NODE` | 50 | Số con trực tiếp |
| `MAX_RICH_TEXT_LENGTH` | 65 535 | Độ dài `RichText.content` |
| `MAX_SHORT_STRING_LENGTH` | 500 | maxLength mặc định cho chuỗi không khai báo riêng |
| `MAX_URL_LENGTH` | 2 048 | Prop URL |
| `NODE_ID_REGEX` | `/^[a-zA-Z0-9_-]{3,64}$/` | Định dạng ID |
| `SUPPORTED_SCHEMA_VERSIONS` | `[1]` | Chỉ có schema v1 |

Các giới hạn này được công bố qua `GET /api/components/registry` (route `api.components.registry`).

### 3.3 Thuật toán `DocumentValidator::validate(string|array $payload): ValidationResult`

1. **Kích thước.** Nếu payload là string và `strlen > 524288` → `max_payload_size_exceeded`. Sau đó `json_decode(..., 512, JSON_THROW_ON_ERROR)`, lỗi → `invalid_json`. Nếu payload là array, kích thước được ước lượng bằng `strlen(json_encode(...))`.
2. **Hình dạng gốc.** Phải là mảng kết hợp; nếu không → `invalid_root_structure`.
3. **`schemaVersion`.** Thiếu → `missing_schema_version`; không phải int → `invalid_schema_version_type`; không thuộc `[1]` → `unsupported_schema_version`.
4. **`root`.** Phải là object; nếu không → `missing_or_invalid_root`, và validator dừng ngay.
5. **Kiểu root.** Thiếu → `missing_root_type`; chưa đăng ký → `unknown_component_type`; `canBeRoot=false` → `invalid_root_component`.
6. **Đệ quy `validateNode()`** (depth bắt đầu từ 1; `$seenNodeIds` và `$totalNodeCount` dùng chung toàn cây):
   - đếm node: vượt 250 → `max_nodes_exceeded` (bỏ subtree đó);
   - depth > 10 → `max_depth_exceeded`;
   - `id`: rỗng/không phải chuỗi → `missing_node_id`; sai regex → `invalid_node_id_format`; trùng → `duplicate_node_id`;
   - `type`: thiếu → `missing_node_type`; lạ → `unknown_component_type`; alias được chuẩn hoá;
   - `props` không phải array → `invalid_props_type`, sau đó chạy `ComponentDefinition::validateProps` (§3.4);
   - `children` không phải array → `invalid_children_type`; leaf có con → `children_not_allowed`; quá 50 → `max_children_exceeded`; phần tử không phải object → `invalid_child_node`;
   - `styles` → `validateStyles()` (§5.2);
   - kết quả từng node: `{id, type (đã chuẩn hoá), props (đã sanitize + default), children, styles?}`.
7. **Thất bại.** `ValidationResult::failure($errors)` gom **tất cả** lỗi, mỗi lỗi có `path` / `code` / `message`. Path có dạng `root.children[2].props.level` hoặc `root.styles.mobile.padding`.
8. **Thành công.** Trả bản sanitized `{schemaVersion, root, pageSettings?}`. `pageSettings` chỉ giữ `title` và `description` (mỗi trường cắt còn 500 ký tự; nếu không phải chuỗi thì thành `null`).

API phụ của `ValidationResult`: `isValid()`, `getErrors()`, `getErrorsArray()`, `getSanitizedDocument()`, `hasError($path)`, `firstError()`.

`DocumentValidator::createEmptyDocument($title = 'New Page')` tạo **tài liệu rỗng chuẩn**: root `Section#root_section_default` chứa `Container#node_container_default`. Phía client, `createDefaultDocument()` sinh cấu trúc giống hệt.

> ⚠️ **Lưu ý quan trọng:** `PagePersistenceService` và `PageTemplateController` lưu **document gốc của request**, không lưu `getSanitizedDocument()`. Validator đóng vai trò "cổng chặn", còn kết quả chuẩn hoá (điền default, chuẩn hoá alias, sanitize HTML) không được lưu xuống DB. Trang public vẫn an toàn vì renderer escape và sanitize lại khi render. Xem [§21](#21-hạn-chế-đã-biết-và-khoảng-trống).

### 3.4 Validate props (`ComponentDefinition::validateProps`)

| Kiểu schema | Kiểm tra | Mã lỗi |
|---|---|---|
| mọi kiểu | Key không có trong schema | `unknown_prop` |
| mọi kiểu | Bắt buộc mà thiếu hoặc `null` | `required_prop_missing` (prop tuỳ chọn thì điền default) |
| `string` | `is_string`; `mb_strlen ≤ maxLength ?? 500`; enum chặt; `isUrl` → `isSafeUrl`; `isHtml` → thay bằng bản `sanitize()` | `invalid_type`, `max_length_exceeded`, `invalid_enum_value`, `unsafe_url` |
| `integer` | `is_int` **chặt** (chuỗi `"2"` bị từ chối); `min`/`max`; enum | `invalid_type`, `min_value_exceeded`, `max_value_exceeded`, `invalid_enum_value` |
| `boolean` | `is_bool` chặt | `invalid_type` |
| `array` | Phải là list; `count ≤ maxItems ?? 50`; mỗi item là object; field theo `itemSchema` (required, string, maxLength, `isHtml` → sanitize) | `max_items_exceeded`, `invalid_item_type`, `required_field_missing` |

`validateSingleProp()` dùng cùng quy tắc nhưng không điền default. Hàm này dùng cho override trong `styles`.

> Kiểm tra integer chặt là lý do frontend cần `coercePropValue()`, vì `<select>` gốc trả về chuỗi (lỗi KI-007 / F-01 đã sửa).

### 3.5 `HtmlSanitizer` (chống XSS)

`app/Domains/DocumentEngine/Sanitization/HtmlSanitizer.php` bọc `symfony/html-sanitizer` (parser W3C thay cho regex, D-012).

- Cấu hình: `allowSafeElements()`, `allowLinkSchemes(['http','https','mailto','tel'])`, `allowRelativeLinks(true)`. Bị loại bỏ: `<script>`, `<style>`, `<iframe>`, thuộc tính `on*`, link `javascript:` / `data:`.
- **`isSafeUrl(?string)`**:
  - Hợp lệ: null hoặc rỗng; `#anchor`; `/path`.
  - Bị từ chối: `//host` (protocol-relative); ký tự điều khiển `[\x00-\x1F\x7F]`; mọi chuỗi có dạng `scheme:` với scheme ngoài `http, https, mailto, tel` (ví dụ `javascript:`, `data:`, `vbscript:`).

Phía client có bộ sanitize song song là `resources/js/lib/domSanitizer.ts` (`sanitizeHtml`, `isSafeUrl`):

- dùng `DOMParser` với allowlist tag/attribute;
- loại bỏ `id` (chống DOM clobbering, KI-010);
- loại bỏ `on*`, script/iframe/style/SVG/MathML;
- đổi `javascript:` href thành `#`;
- thêm `noopener` cho link `_blank`;
- **fail-closed**: khi không có DOM parser thì trả rỗng, không dùng regex dự phòng.

### 3.6 Versioning schema

Hiện **chưa có pipeline migration schema**. `SUPPORTED_SCHEMA_VERSIONS = [1]`, mọi writer vẫn ghi `schema_version = 1`. Release cũ dùng `renderer_version = 1`; release tạo từ Phase 2B dùng `renderer_version = 2` để đánh dấu responsive rendering/publish preflight mới. Không có migration hoặc sửa release cũ.

Khi cần v2, phải:

1. Thêm version vào danh sách hỗ trợ.
2. Viết migrator v1 → v2 chạy khi đọc (hoặc backfill).
3. Version hoá renderer.
4. Thêm fixture cho tài liệu cũ.

D-018 và D-020 đã cố ý mở rộng schema theo cách tương thích ngược để tránh phải nâng version.

---

## 4. Component Registry — 12 component lõi

### 4.1 Mỗi component được định nghĩa ở đâu

Một component tồn tại đồng thời ở **7 nơi**, và tất cả phải đồng bộ:

| Lớp | File | Nội dung |
|---|---|---|
| Schema chuẩn (backend) | `app/Domains/DocumentEngine/Registry/ComponentRegistry.php` | `ComponentDefinition(type, label, category, allowChildren, canBeRoot, propSchemas)`, alias, `RESPONSIVE_OVERRIDABLE_PROPS` |
| Registry API | `routes/api.php` → `GET /api/components/registry` | `{schemaVersion:1, components:{Type:{type,label,category,allowChildren,canBeRoot,props}}, limits:{...}}` |
| Kiểu và default (frontend) | `resources/js/types/document.ts` | `CoreComponentType`, `CONTAINER_COMPONENT_TYPES`, `DEFAULT_COMPONENT_PROPS` |
| Renderer editor | `resources/js/components/editor/canvas/renderers/<Type>Renderer.vue` + `rendererMap` trong `NodeRenderer.vue` | Preview Vue trên canvas |
| Renderer publish | `app/Domains/Publishing/Services/DocumentHtmlRenderer.php` (`renderSection` … `renderDivider`) | Chuỗi HTML phía server |
| Responsive allowlist | `resources/js/lib/responsive.ts` (`RESPONSIVE_OVERRIDABLE_PROPS`, `COMPONENT_DISPLAY_MODES`) | Phản chiếu PHP |
| Icon palette | `ComponentLibrary.vue` (`iconMap`) | |

**Lưu ý:** frontend **không** định nghĩa prop schema. Inspector sinh field lúc chạy từ registry API (D-015). Thêm component mới phải sửa cả 7 nơi, kèm test ở cả hai phía.

### 4.2 Bảng tổng hợp

| # | Type | Category | Container | Root | Props (kiểu · enum · default · ràng buộc) | Override responsive (+`display`) | Display gốc |
|---|---|---|---|---|---|---|---|
| 1 | `Section` | layout | ✔ | ✔ | `padding` none/small/**medium**/large · `background` **transparent**/muted/primary/dark | padding, background | block |
| 2 | `Container` | layout | ✔ | ✔ | `maxWidth` sm/md/**lg**/xl/full · `centered` bool (**true**) | maxWidth, centered | block |
| 3 | `Heading` | typography | ✘ | ✘ | `text`* ≤255 · `level`* int 1–6 (**2**) · `align` **left**/center/right | align | block |
| 4 | `RichText` | typography | ✘ | ✘ | `content`* ≤65 535, `isHtml` | — | block |
| 5 | `Image` | media | ✘ | ✘ | `src`* ≤2048 `isUrl` · `alt` ≤255 · `caption` ≤255 · `aspectRatio` **auto**/16:9/4:3/1:1 | aspectRatio | block |
| 6 | `Button` | interactive | ✘ | ✘ | `label`* ≤100 · `url`* ≤2048 `isUrl` · `variant` **primary**/secondary/outline/ghost · `size` sm/**md**/lg · `target` **_self**/_blank | size, variant | inline-block |
| 7 | `Hero` | content | ✔ | ✔ | `title`* ≤255 · `subtitle` ≤500 · `align` left/**center** · `ctaText` ≤100 · `ctaUrl` ≤2048 `isUrl` | align | flex |
| 8 | `Columns` | layout | ✔ | ✘ | `count` int 2/3/4 (**2**) · `gap` small/**medium**/large | count, gap | grid |
| 9 | `CardGrid` | layout | ✔ | ✘ | `columns` int 2/3/4 (**3**) · `gap` small/**medium**/large | columns, gap | grid |
| 10 | `FAQ` | content | ✘ | ✘ | `items` array ≤20, item `{question* ≤255, answer* ≤2000 isHtml}` | — | block |
| 11 | `Spacer` | layout | ✘ | ✘ | `size` small/**medium**/large/xlarge | size | flex |
| 12 | `Divider` | layout | ✘ | ✘ | `style` **solid**/dashed/dotted · `color` **muted**/primary/subtle | style, color | block |

Ký hiệu: `*` = bắt buộc; giá trị **in đậm** = default.

**Default khi chèn từ editor** (`DEFAULT_COMPONENT_PROPS`), khác default của schema ở chỗ có nội dung mẫu:

| Type | Default phía client |
|---|---|
| Section | `{padding:'medium', background:'transparent'}` |
| Container | `{maxWidth:'lg', centered:true}` |
| Heading | `{text:'New Heading', level:2, align:'left'}` |
| RichText | `{content:'<p>Start typing rich text here...</p>'}` |
| Image | `{src:'https://images.unsplash.com/photo-1579546929518-9e396f3cc809', alt:'Sample image', caption:'', aspectRatio:'auto'}` |
| Button | `{label:'Click Here', url:'#', variant:'primary', size:'md', target:'_self'}` |
| Hero | `{title:'Hero Headline', subtitle:'A compelling subheadline introducing your content.', align:'center', ctaText:'Learn More', ctaUrl:'#'}` |
| Columns | `{count:2, gap:'medium'}` |
| CardGrid | `{columns:3, gap:'medium'}` |
| FAQ | `{items:[{question:'What is this page builder?', answer:'<p>A high-performance visual page builder for SaaS applications.</p>'}]}` |
| Spacer | `{size:'medium'}` |
| Divider | `{style:'solid', color:'muted'}` |

**Vị trí field trong Inspector** (phân tab theo tập key cứng, xem §6.10):

| Type | Content | Style | Settings |
|---|---|---|---|
| Section | — | padding, background | — |
| Container | — | maxWidth, centered | — |
| Heading | text | level, align | — |
| RichText | content | — | — |
| Image | src, alt, caption | aspectRatio | — |
| Button | label, url | variant, size | target |
| Hero | title, subtitle, ctaText, ctaUrl | align | — |
| Columns | — | count, gap | — |
| CardGrid | — | columns, gap | — |
| FAQ | items | — | — |
| Spacer | — | size | — |
| Divider | — | style, color | — |

### 4.3 Chi tiết từng component

Mỗi mục mô tả: hành vi trên **canvas editor** (Vue) → hành vi khi **publish** (PHP). Hai renderer dùng **cùng bộ class Tailwind**, chỉ khác ở các điểm được ghi chú.

#### 4.3.1 Section — container, có thể làm root

- **Editor (`SectionRenderer.vue`):** render `<section class="w-full transition-colors {padding} {background}"><slot/></section>`.
  - padding: `none→py-0`, `small→py-4 sm:py-6`, `medium→py-8 sm:py-12`, `large→py-16 sm:py-24`.
  - background: `transparent→bg-transparent text-foreground`, `muted→bg-muted/50 text-foreground`, `primary→bg-primary/10 text-foreground`, `dark→bg-zinc-900 text-zinc-100 dark:bg-zinc-950`.
- **Root mặc định** của mọi page là `Section#root_section_default`.
- **Inspector:** tab Style có Padding và Background (đều override được theo viewport).
- **Publish (`renderSection`):** cùng class, thêm `id="{node.id}"` và class ẩn/hiện responsive. Khác biệt nhỏ: server không thêm `dark:bg-zinc-950`.

#### 4.3.2 Container — container, có thể làm root

- **Editor:** `<div class="w-full px-4 sm:px-6 {maxWidth} {centered ? 'mx-auto' : ''}">`.
  - maxWidth: `sm→max-w-screen-sm`, `md→max-w-screen-md`, `lg→max-w-screen-lg`, `xl→max-w-screen-xl`, `full→max-w-full`.
- **Inspector:** Max Width (select) và Centered (checkbox), cả hai responsive.
- **Publish:** tương đương. Thiếu key `centered` thì coi là `true`.

#### 4.3.3 Heading — leaf

- **Editor:** tag động `h{level}`, level bị kẹp trong 1–6.
  - Cỡ chữ: 1 `text-3xl sm:text-4xl font-extrabold tracking-tight` · 2 `text-2xl sm:text-3xl font-bold tracking-tight` · 3 `text-xl sm:text-2xl font-bold` · 4 `text-lg sm:text-xl font-semibold` · 5 `text-base sm:text-lg font-medium` · 6 `text-sm sm:text-base font-medium`.
  - Căn lề: `text-left|center|right`.
  - Text được interpolate (đã escape); chuỗi rỗng hiển thị "Heading".
- **Inspector:** Content: `text` (bộ đếm 255). Style: `level` (select, ép kiểu về integer), `align` (responsive).
- **Publish:** tương đương, text qua `htmlspecialchars`.

#### 4.3.4 RichText — leaf

- **Editor:** `<div class="prose dark:prose-invert my-2 max-w-none" v-html="sanitizeHtml(content)">`.
- **Inspector:** một `Textarea` monospace để nhập HTML (`isHtml`). **Không có trình soạn WYSIWYG/contentEditable.**
- **Sanitize hai lớp:** client `domSanitizer.sanitizeHtml`; server `HtmlSanitizer::sanitize` lúc validate **và** lúc render publish.
- **Responsive:** chỉ có `display`.

#### 4.3.5 Image — leaf

- **Editor:** `<figure><img loading="lazy">` + `<figcaption>` tuỳ chọn.
  - aspect: `16:9→aspect-16/9 object-cover`, `4:3→aspect-4/3 object-cover`, `1:1→aspect-square object-cover`, `auto→h-auto max-h-[500px] object-cover`.
  - Khi `src` rỗng hoặc ảnh lỗi (`@error`), hiển thị khung placeholder nét đứt 16:9 với thông báo "No Image Source Configured".
- **Inspector:**
  - Content: `src` (ô URL + nút **"Choose from Library"** mở `MediaLibraryModal`), `alt`, `caption`.
  - Style: `aspectRatio`.
  - Khi chọn asset: ghi `src = asset.url` (URL tương đối `/storage/media/...`) và chỉ điền `alt` nếu `alt` đang rỗng (D-020).
- **Publish (`renderImage`):** thêm `decoding="async"`. `src` rỗng hoặc không an toàn → `PublishRenderingException`. Ở preview mode thì hiện placeholder thay vì ném lỗi.
  - ⚠️ Editor cho phép Image rỗng nhưng publish sẽ thất bại.

#### 4.3.6 Button — leaf

- **Editor:** `<div class="my-2 inline-block"><a :href :target @click.prevent>`. Điều hướng bị chặn trong editor.
  - variant: `primary→bg-primary text-primary-foreground hover:bg-primary/90`, `secondary→bg-secondary …`, `outline→border border-input bg-background hover:bg-accent …`, `ghost→hover:bg-accent …`.
  - size: `sm→h-8 px-3 text-xs`, `md→h-9 px-4 text-sm font-medium`, `lg→h-11 px-6 text-base font-semibold`.
- **Inspector:** Content: `label`, `url`. Style: `variant`, `size` (responsive). Settings: `target`.
- **Publish:** validate URL, thêm `role="button"`, thêm `rel="noopener noreferrer"` khi `target=_blank`.

#### 4.3.7 Hero — container, có thể làm root

- **Editor:** cột flex `px-4 py-12 sm:px-6 sm:py-20`; căn `text-left items-start` hoặc `text-center items-center`.
  - Gồm: `<h1 class="max-w-3xl text-3xl … sm:text-5xl">` tiêu đề; `<p>` subtitle tuỳ chọn; link CTA hiển thị khi có `ctaText`; vùng con `<div class="mt-6 w-full"><slot/></div>`.
- **Inspector:** Content: title, subtitle, ctaText, ctaUrl. Style: align (responsive).
- **Publish:** chỉ validate `ctaUrl` khi `ctaText` khác rỗng. Vùng con chỉ được render khi có children; editor luôn render vùng này để chứa drop zone.

#### 4.3.8 Columns — container

- **Editor:** `<div class="my-4 grid w-full {cols} {gap}">`. **Mỗi con trực tiếp là một ô grid**; không có component "Column" riêng.
  - cols: `2→grid-cols-1 md:grid-cols-2`, `3→grid-cols-1 sm:grid-cols-2 md:grid-cols-3`, `4→grid-cols-1 sm:grid-cols-2 md:grid-cols-4`.
  - gap: `small→gap-3 sm:gap-4`, `medium→gap-6 sm:gap-8`, `large→gap-8 sm:gap-12`.
- **Lưu ý editor:** các `DropZone` là anh em của node con nên cũng chiếm ô grid. Vì vậy bố cục trên canvas không trùng khớp 100% với bản publish.
- `count` chỉ nhận 2/3/4. Giá trị 1 bị cấm trong hợp đồng dữ liệu; grid tự xếp thành 1 cột ở màn hình hẹp (D-018).

#### 4.3.9 CardGrid — container

- Cấu trúc giống Columns, khác breakpoint/gap:
  - cols: `2→grid-cols-1 md:grid-cols-2`, `3→grid-cols-1 sm:grid-cols-2 md:grid-cols-3`, `4→grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`.
  - gap: `small→gap-3 sm:gap-4`, `medium→gap-6`, `large→gap-8 sm:gap-10`.
- Không có wrapper "card": card là bất kỳ con nào bạn đặt vào, thường là một Container.

#### 4.3.10 FAQ — leaf

- **Editor:** accordion tương tác. `openIndex` cục bộ (mặc định 0); dùng `@click.stop` để bấm câu hỏi không chọn node. Answer render qua `v-html="sanitizeHtml(...)"`.
- **Inspector:** repeater "Items" gồm Question (`Input`) và Answer (`Textarea`, cho phép HTML). Nút "Add FAQ" thêm `{question:'New Question', answer:'<p>Answer details here.</p>'}`; mỗi item có nút xoá. Repeater **không chặn giới hạn 20 item phía client**; server sẽ từ chối.
- **Publish:** `<details>/<summary>` thuần HTML, không cần JS; chevron `group-open:rotate-180`; answer được sanitize lại. Danh sách rỗng hiển thị "No FAQ items configured."

#### 4.3.11 Spacer — leaf

- size → chiều cao: `small h-4 sm:h-6`, `medium h-8 sm:h-12`, `large h-16 sm:h-20`, `xlarge h-24 sm:h-32`.
- Là div `aria-hidden`; trong editor hiện viền nét đứt khi hover.

#### 4.3.12 Divider — leaf

- `<div class="my-6 w-full py-2"><hr class="w-full border-t {style} {color}">`.
- color: `muted→border-border`, `primary→border-primary/40`, `subtle→border-border/40`.
- Prop tên `style` trùng tên attribute dành riêng của Vue nhưng vẫn hoạt động vì đã được khai báo là prop.

---

## 5. Responsive Styling Engine (D-018)

### 5.1 Mô hình lưu trữ

- **Desktop là gốc của cascade** và nằm trong `node.props`.
- Override thưa (sparse) nằm trong `node.styles.tablet` và `node.styles.mobile`.
- Không nâng `schemaVersion`: tài liệu không có `styles` vẫn hợp lệ 100%.

```jsonc
{
  "id": "node_columns_k3j9x0lq2a", "type": "Columns",
  "props":  { "count": 3, "gap": "large" },
  "styles": {
    "tablet": { "count": 2 },
    "mobile": { "count": 2, "gap": "small", "display": "hidden" }
  }
}
```

**Cascade:**

```text
desktop = props[key]
tablet  = styles.tablet[key] ?? props[key]
mobile  = styles.mobile[key] ?? styles.tablet[key] ?? props[key]
```

### 5.2 Quy tắc validate (`DocumentValidator::validateStyles`)

| Đầu vào | Kết quả |
|---|---|
| `styles: null` | Bỏ qua |
| Không phải object | `invalid_styles_format` |
| `{}` hoặc `[]` rỗng | Bị cắt bỏ (xử lý an toàn khác biệt `{}`/`[]` của PHP) |
| List khác rỗng | `invalid_styles_format` |
| Khoá viewport khác `tablet`/`mobile` (kể cả `desktop`) | `invalid_viewport_key` |
| Viewport rỗng | Bị cắt bỏ |
| Prop không nằm trong `RESPONSIVE_OVERRIDABLE_PROPS[type]` | `disallowed_style_override` (prop nội dung như text, src, content, items, `Button.target` không override được) |
| `display` | Chỉ nhận `visible` hoặc `hidden` |
| Prop khác | Validate kiểu/enum bằng `validateSingleProp` theo schema gốc |

Allowlist canonical nằm ở `ComponentRegistry::RESPONSIVE_OVERRIDABLE_PROPS`; breakpoint canonical nằm ở `ComponentRegistry::RESPONSIVE_BREAKPOINTS`. `GET /api/components/registry` trả cả hai trong field `responsive`, và editor gọi `configureResponsiveContract()` sau khi fetch. `lib/responsive.ts` giữ fallback tương đương để render an toàn trước khi request hoàn tất:

```ts
Section:['padding','background']  Container:['maxWidth','centered']  Heading:['align']
RichText:[]  Image:['aspectRatio']  Button:['size','variant']  Hero:['align']
Columns:['count','gap']  CardGrid:['columns','gap']  FAQ:[]  Spacer:['size']  Divider:['style','color']
// 'display' luôn được phép trên mọi type
```

### 5.3 Engine phía client (`resources/js/lib/responsive.ts`)

| Hàm | Mục đích |
|---|---|
| `resolveResponsiveProp(node, key, vp)` | Cascade như §5.1 |
| `resolveAllResponsiveProps(node, vp)` | `{...props}` rồi áp dụng cascade cho mọi key trong allowlist cộng `display` |
| `isPropOverridden(node, key, vp)` | Có giá trị tường minh ở **chính** viewport đó không (desktop luôn `false`). `undefined` = không override; `false` hoặc `''` vẫn tính là override |
| `getInheritedPropInfo(node, key, vp)` | `{value, source:'tablet'|'desktop'}` cho nhãn "Inherited from …" |
| `isResponsiveProp(type, key)` | `key === 'display'` hoặc key nằm trong allowlist |
| `COMPONENT_DISPLAY_MODES`, `RESPONSIVE_DISPLAY_CLASSES` | Hằng chuỗi class tĩnh để Tailwind quét và biên dịch |
| `RESPONSIVE_BREAKPOINTS` | Contract public: tablet 768 px, desktop 1024 px; độc lập preset canvas desktop 1280 px |
| `configureResponsiveContract(contract)` | Hydrate allowlist + breakpoint từ Component Registry API, tránh editor tự định nghĩa một hệ breakpoint khác |
| `buildEditorResponsivePresentation(node, vp)` | Ánh xạ enum đã validate thành inline presentation cho viewport đang mô phỏng, không phụ thuộc kích thước cửa sổ thật |

**Luồng chỉnh sửa:**

1. `InspectorPanel.handlePropChange` kiểm tra viewport đang chọn và `isResponsiveProp`.
2. Đang ở tablet/mobile và key responsive → `updateStyle` → `useDocumentState.updateNodeStyle(id, vp, key, value)`. Hàm này tạo `styles[vp]` nếu chưa có và gán bản deep copy.
3. Ngược lại → `updateProp` ghi vào `props`. **Nội dung (text, URL) luôn là toàn cục**, kể cả khi đang chỉnh ở viewport mobile.
4. Reset → `resetNodeStyle` xoá key, rồi xoá viewport rỗng, rồi xoá `styles` rỗng.
5. Mọi thay đổi đi qua `recordDebouncedEdit` nên đều undo được.

Trên canvas, `NodeRenderer` gọi `resolveAllResponsiveProps(node, viewport)`, bỏ `display` ra trước khi `v-bind` vào renderer, và dùng `display` để vẽ trạng thái "ẩn trên viewport này". `buildEditorResponsivePresentation` bổ sung style root/content theo viewport đã chọn, nên padding, alignment, grid count/gap, typography và các property responsive được hỗ trợ không còn lấy breakpoint từ cửa sổ thật. Node bị ẩn vẫn nằm trong DOM editor (viền nét đứt, badge "Hidden on {viewport}") để còn chọn được.

### 5.4 CSS được tạo ra thế nào

- Base markup vẫn dùng class Tailwind hiện hữu. Nếu node không có responsive override, renderer không sinh thêm CSS và hành vi desktop cũ được giữ nguyên.
- Nếu `styles.tablet`/`styles.mobile` có key trong allowlist, `DocumentHtmlRenderer` ánh xạ **chỉ enum đã validate** thành rule CSS theo selector `[id="node-id"]`; không nối giá trị CSS tự do từ document.
- Rule mobile là mặc định, tablet nằm trong `@media (min-width: 768px)`, desktop trong `@media (min-width: 1024px)`. Mobile kế thừa tablet khi không có override riêng; desktop luôn quay về `props` base.
- CSS responsive được đặt trong `<style data-pb-responsive>` ở `<head>` của document đầy đủ và nằm trong `html_hash` release. `render()` fragment cũng trả style block để unit/consumer fragment thấy đúng output.
- Các target con như Image `<img>`, Button `<a>` và Divider `<hr>` dùng selector cố định do renderer tạo. URL/HTML sanitization không bị nới lỏng.

Breakpoint public: mobile `< 768px`, tablet `768–1023px`, desktop `≥ 1024px`. Preset editor vẫn là 375 / 768 / 1280 px; 1280 chỉ là kích thước canvas desktop.

### 5.5 Parity editor ↔ publish

1. Property ngoài allowlist vẫn bị validator từ chối; không phải mọi component hỗ trợ mọi property.
2. `tablet=hidden` kế thừa xuống mobile; `styles.mobile.display='visible'` reset lại riêng mobile. Desktop không có bucket override trong schema và giữ base visible.
3. Canvas dùng inline presentation theo viewport logic để vô hiệu ảnh hưởng của kích thước cửa sổ thật đối với các class `sm:/md:/lg:` nội bộ.
4. Test contract gồm TS cascade/breakpoint/editor presentation và PHP HTML/CSS thực tế cho đủ 12 component.

---

## 6. Page Builder (frontend editor)

### 6.1 Cấu trúc thư mục frontend

| Đường dẫn | Nội dung |
|---|---|
| `resources/js/app.ts` | Bootstrap Inertia. Layout mặc định: `Welcome` → không layout; `auth/*` → `AuthLayout`; `settings/*` → `[AppLayout, SettingsLayout]`; còn lại → `AppLayout`. Đăng ký directive `v-focus`, `initializeTheme()`, `initializeFlashToast()` |
| `resources/js/pages/` | `Dashboard`, `Welcome`, `Pages/{Builder,Create,Edit}`, `Websites/{Index,Create,Show}`, `auth/*`, `settings/{Profile,Security,Appearance}` |
| `resources/js/components/editor/` | `EditorShell`, `EditorToolbar`, `ViewportSwitcher`, `EditorCanvas`, `ComponentLibrary`, `InspectorPanel`, `RevisionsModal`, `ConflictModal`, `PublishSuccessModal`, `SeoSettingsModal`, `SaveAsTemplateModal`(*) |
| `resources/js/components/editor/canvas/` | `NodeRenderer.vue`, `DropZone.vue`, `NodeToolbar.vue`, `renderers/*Renderer.vue` (12 file) |
| `resources/js/components/media/` | `MediaLibraryModal`, `MediaGrid`, `MediaCard`, `MediaDetailsPanel`, `MediaUploadDropzone` |
| `resources/js/components/templates/` | `SaveAsTemplateModal`, `TemplateCard` |
| `resources/js/components/ui/` | Primitive của shadcn-vue |
| `resources/js/composables/` | Editor: `useDocumentState`, `useEditorHistory`, `useAutosave`, `useEditorState`; starter kit: `useAppearance`, `useCurrentUrl`, `useInitials`, `useTwoFactorAuth` |
| `resources/js/config/editor.ts` | `VIEWPORT_PRESETS`, `COMPONENT_CATEGORIES` |
| `resources/js/lib/` | `responsive.ts`, `inspectorHelpers.ts`, `domSanitizer.ts`, `flashToast.ts`, `utils.ts` (`cn()`, `toUrl()`) |
| `resources/js/types/` | `document.ts`, `media.ts`, `seo.ts`, `auth.ts`, `navigation.ts`, `ui.ts` |
| `resources/js/actions/**`, `routes/**`, `wayfinder/` | Helper route/action có kiểu, sinh bởi Wayfinder (builder hầu như không dùng) |

(*) `SaveAsTemplateModal` nằm ở `components/templates/`.

**Không có Pinia hay store toàn cục.** Trạng thái editor nằm trong 4 composable được khởi tạo **một lần** trong `EditorShell.vue`. Dữ liệu đi xuống qua props; thay đổi đi lên qua emit.

**HTTP:** các component editor/media/template gọi `fetch()` với URL viết cứng. CSRF xử lý như sau:

- Hầu hết đọc cookie `XSRF-TOKEN` và gửi header `X-XSRF-TOKEN`. Helper `getXsrfToken()` bị lặp lại ở `useAutosave.ts`, `EditorShell.vue`, `RevisionsModal.vue`, còn `SaveAsTemplateModal.vue` viết inline.
- Các component media dùng `getCsrfToken()`: ưu tiên `meta[name=csrf-token]`, fallback cookie, và gửi cả `X-XSRF-TOKEN` lẫn `X-CSRF-TOKEN`.

### 6.2 Điểm vào: route và dữ liệu

- **Route:** `GET /websites/{website}/pages/{page}/builder` (alias `/editor`) → `PageController@builder`.
- `Pages/Builder.vue` là lớp vỏ mỏng: `<Head title="Page Builder — {title}">` và `<EditorShell :website :page :user-role :can :initial-document>`.
- **Props Inertia:**
  - `website{id,name,slug}`
  - `page{id,title,slug,status,lock_version,updated_at,document_json,seo_metadata}`
  - `userRole`
  - `initialDocument`
  - `can{edit,delete}`
- **Nguồn của `initialDocument`:**
  1. `page.document_json` nếu đã có.
  2. Nếu chưa có và URL có `?template=<id>`, server khởi tạo từ template đó.
  3. Nếu vẫn không có, client dùng `createDefaultDocument()`.
- Builder render bên trong `AppLayout`. `EditorShell` cao `h-screen`, và toolbar chứa `SidebarTrigger` của app.

### 6.3 `EditorShell.vue` — gốc kết hợp (composition root)

Khởi tạo các composable:

```ts
const { viewport, leftSidebarOpen, rightPanelOpen, setViewport, toggleLeftSidebar, toggleRightPanel } = useEditorState();
const { document, selectedNodeId, hoveredNodeId, selectedNode, lastError, selectNode, clearSelection,
        setHoveredNode, insertNode, updateNodeProps, updateNodeStyle, resetNodeStyle, deleteNode,
        duplicateNode, moveNodeRelative, setDocument } = useDocumentState(props.initialDocument);
const { canUndo, canRedo, pushSnapshot, recordDebouncedEdit, undo, redo } =
    useEditorHistory(() => document.value, (d) => setDocument(d), { maxSnapshots: 30, debounceMs: 300 });
const { lockVersion, saveStatus, isDirty, isSaving, lastSavedAt, errorMessage, conflictData,
        notifyChange, saveNow, forceUpdateLockVersion } = useAutosave({ websiteId, pageId,
        initialLockVersion: props.page.lock_version ?? 1,
        getCurrentDocument: () => document.value, getSeoMetadata: () => seoMetadata.value,
        onConflict: () => { conflictModalOpen.value = true; } });
```

**Theo dõi thay đổi (dirty tracking):** một deep watcher trên `document`. Lần mount đầu tiên bị bỏ qua. Cập nhật nội bộ do khôi phục revision (cờ `isInternalDocumentUpdate`) cũng bị bỏ qua. Mọi thay đổi khác, kể cả undo/redo, gọi `notifyChange()` (nếu user có quyền sửa).

**Registry:** khi mount, `fetchRegistryMeta()` gọi `GET /api/components/registry` để lấy `registryMetadata`. Inspector nhận `registryMetadata[selectedNode.type]`. (`ComponentLibrary` gọi lại endpoint này một lần nữa, tức có 2 request.)

**Bảng handler — thao tác cấu trúc chụp snapshot trước, chỉ đẩy vào history nếu thành công:**

| Handler | Gọi | History |
|---|---|---|
| `handleInsertComponent(type)` (click) | Node đang chọn là container → `insertNode(type, id, 'inside')`; là leaf → `'after'`; không chọn gì → thêm vào root | `pushSnapshot(docBefore)` |
| `handleDropComponent({type,parentId,index})` | `insertNode(type, parentId, 'inside', undefined, index)` | `pushSnapshot` |
| `handleMoveUp/Down(id)` | `moveNodeRelative(id,'up'/'down')` | `pushSnapshot` |
| `handleDuplicate(id)` | `duplicateNode` (chặn root) | `pushSnapshot` |
| `handleDelete(id)` | `deleteNode` (chặn root) | `pushSnapshot` |
| `handleUpdateProp({key,value})` | `updateNodeProps` | `recordDebouncedEdit` |
| `handleUpdateStyle` / `handleResetStyle` | `updateNodeStyle` / `resetNodeStyle` | `recordDebouncedEdit` |
| `handleSelectNode(id)` | `selectNode` + tự mở Inspector nếu đang đóng | — |

**Phím tắt:**

| Phím | Hành động | Ghi chú |
|---|---|---|
| `Esc` | `clearSelection()` | Chạy cả khi đang gõ trong input |
| `Ctrl/Cmd+B` | Bật/tắt panel Components | Bỏ qua khi focus ở input/textarea/contentEditable |
| `Ctrl/Cmd+S` | `saveNow()` (manual, tạo revision) | Chạy cả trong input |
| `Ctrl/Cmd+Z` | Undo | Đăng ký trong `useEditorHistory`; bỏ qua khi đang trong input |
| `Ctrl/Cmd+Shift+Z` hoặc `Ctrl/Cmd+Y` | Redo | Như trên. macOS dùng `metaKey` |

**Preview:** `window.open('/websites/{w}/pages/{p}/preview', '_blank')`.

**Publish (`handlePublish`):**

1. Nếu trạng thái save khác `saved` → `await saveNow('Pre-publish autosave', false)`; kết quả `false`, conflict hoặc exception dừng publish và giữ thông báo lỗi/conflict.
2. Chỉ sau khi draft mới nhất đã lưu thành công mới gọi `POST /websites/{w}/pages/{p}/publish`. `isPublishing` được bật trước bước save để chặn kích hoạt lặp.
3. Thành công → `publishedPublicUrl`, mở `PublishSuccessModal`, gán `props.page.status = 'published'` (sửa trực tiếp object prop).
4. Thất bại → ghi vào `lastError` (banner lỗi).

**Khôi phục revision (`handleRevisionRestored`):**

1. Bật `isInternalDocumentUpdate`.
2. `setDocument(doc)`.
3. `forceUpdateLockVersion(lock_version)`.
4. Đặt `saveStatus='saved'` và đóng modal.

Lịch sử undo **không** bị xoá, nên có thể undo về trạng thái trước khi khôi phục.

**Xử lý conflict (D-026):**

- "Reload Server Version" → `window.location.reload()`.
- "Overwrite (Keep Mine)" → `forceUpdateLockVersion(conflictData.current_lock_version)` rồi `saveNow('Overwrite after conflict resolution')`. Đây là last-write-wins có chủ đích.

**Bố cục:** `EditorToolbar` ở trên; banner lỗi (`lastError || autosaveError`, có nút Dismiss); thân 3 cột `ComponentLibrary` (trái, `v-show`, hiệu ứng trượt) · `EditorCanvas` (giữa) · `InspectorPanel` (phải). Có nút nổi để mở lại panel đã thu gọn. Các modal: `SaveAsTemplateModal`, `ConflictModal`, `RevisionsModal`, `PublishSuccessModal`, `SeoSettingsModal`.

### 6.4 `useDocumentState.ts` — trạng thái và API biến đổi cây

**Mô hình bộ nhớ:**

- Toàn bộ cây nằm trong một `ref<DocumentSchema>` reactive sâu.
- Các mutation sửa tại chỗ (`children.splice`, `node.props = {...}`), riêng `setDocument` thay bằng một bản deep clone.
- Giá trị ghi vào cây luôn được deep copy (`JSON.parse(JSON.stringify())`) để không chia sẻ tham chiếu với state của Inspector.
- Các ref phụ: `selectedNodeId`, `hoveredNodeId`, `lastError`. `selectedNode` là computed, dùng `findNode` đệ quy.

**Sinh ID:**

```ts
export function createNodeId(type: string): string {
    const cleanType = type.toLowerCase().replace(/[^a-z0-9]/g, '').slice(0, 8);
    const rand = Math.random().toString(36).substring(2, 8);
    const time = Date.now().toString(36).slice(-4);
    return `node_${cleanType}_${rand}${time}`;   // ví dụ node_richtext_x8k2ab9fq1
}
```

ID khớp `NODE_ID_REGEX` nhưng không đảm bảo duy nhất về mặt mật mã học. Server từ chối ID trùng bằng `duplicate_node_id`.

**Hàm truy vấn:**

- `findNode(id) → {node, parent, index, depth}` (root có depth 1)
- `countNodes()`
- `getMaxDepth()`
- `isDescendant(searchId, ancestorId)` (dùng chống vòng lặp)
- `cloneNodeWithNewIds(node)` (giữ nguyên `styles`, đổi mọi ID)

**API biến đổi.** Mọi hàm trả `TreeMutationResult {success, error?}`; khi lỗi thì ghi `lastError` (hiện ở banner).

| Hàm | Ngữ nghĩa và ràng buộc |
|---|---|
| `insertNode(type, targetId?, position='inside', customProps?, targetIndex?)` | Từ chối khi tổng node ≥ 250. Không có target → chèn vào root tại `targetIndex` (được kẹp trong biên). `'inside'` đòi target là container. `'before'/'after'` cần node cha và không áp dụng cho root. Kiểm tra số con ≤ 50 và depth+1 ≤ 10. Props = `DEFAULT_COMPONENT_PROPS[type]` gộp `customProps`. Container được gán `children:[]`. **Tự chọn node mới.** |
| `updateNodeProps(id, newProps)` | Gộp nông: `props = {...props, ...deepCopy(newProps)}` |
| `updateNodeStyle(id, vp, key, value)` | Tạo `styles[vp]` nếu thiếu, gán bản deep copy |
| `resetNodeStyle(id, vp, key)` | Xoá key, cắt bỏ object rỗng |
| `deleteNode(id)` | Cấm root. Xoá lựa chọn nếu node bị xoá **hoặc một hậu duệ** của nó đang được chọn (F-06 / KI-011) |
| `duplicateNode(id)` | Cấm root. Clone với ID mới. Kiểm tra `tổng + subtree ≤ 250` và số con của cha < 50. Chèn tại `index+1` và chọn bản sao |
| `moveNodeRelative(id, 'up'/'down')` | Hoán đổi với anh em liền kề. Ở biên thì trả success mà không đổi gì |
| `moveNode(id, targetParentId, targetIndex)` | Cấm root và vòng lặp; target phải là container; kiểm tra `target.depth + 1 + (subtreeMaxDepth − node.depth) ≤ 10`. **Hiện chưa có UI nào gọi.** |
| `setDocument(doc)` | Thay bằng deep clone; bỏ lựa chọn nếu node đang chọn không còn. Dùng cho undo/redo/khôi phục revision |

**Tài liệu mặc định:** root `Section` (`padding:'medium'`, `background:'transparent'`) chứa `Container` (`maxWidth:'lg'`, `centered:true`), cùng `pageSettings {title:'New Page', description:''}`. `pageSettings` không chỉnh được trên UI; server dùng nó làm fallback cho `<title>`/description.

**Validate phía client** chỉ kiểm tra cấu trúc: số node, độ sâu, số con, container/leaf, bảo vệ root, chống vòng lặp. Inspector có thêm `maxlength` và `<select>` cho enum. Client **không** kiểm tra: prop bắt buộc, độ an toàn URL, kích thước payload, `maxItems` của FAQ, `canBeRoot`. Tất cả việc đó do server đảm nhận.

### 6.5 `useEditorHistory.ts` — undo/redo bằng snapshot (D-017)

Thiết kế dùng **ngăn xếp snapshot toàn tài liệu**, không phải command log. Lý do: tài liệu ≤ 250 node (khoảng 20–50 KB), clone mất dưới 0,1 ms, và cách này không có bug nghịch đảo lệnh.

- `past` / `future`: mảng `DocumentSchema` đã deep clone. Các tuỳ chọn `maxSnapshots` (mặc định và đang dùng: **30**) và `debounceMs` (**300**).
- `pushSnapshot(snapshot?)` cho thao tác rời rạc:
  1. `flushPending()`.
  2. Đẩy bản clone vào `past`; vượt giới hạn thì `shift()` bản cũ nhất.
  3. Xoá `future`.
- `recordDebouncedEdit(beforeDoc)` cho chỉnh sửa liên tục (gõ phím):
  1. Lần gọi đầu tiên của một loạt lưu `pendingBaseSnapshot`.
  2. Mỗi lần gọi khởi động lại timer 300 ms.
  3. Khi timer chạy, base snapshot được đẩy vào `past`.
  → **Một loạt gõ phím = một bước undo.**
- `undo()`: `flushPending` → đẩy tài liệu hiện tại vào `future` → pop `past` → `setDocument`. `redo()` làm ngược lại.
- Listener bàn phím chỉ đăng ký khi có component instance (`getCurrentInstance()`), nên composable test được độc lập. `onUnmounted` flush rồi gỡ listener.
- Có `clearHistory()` nhưng chưa được dùng.

### 6.6 `useAutosave.ts` — autosave, trạng thái lưu, khoá lạc quan (D-026, D-027)

**State:**

- `saveStatus: 'saved'|'saving'|'dirty'|'error'|'conflict'`
- `lockVersion`, `lastSavedAt`, `errorMessage`, `conflictData{current_lock_version, updated_at}`
- computed `isDirty` (`dirty` hoặc có thay đổi đến trong lúc đang lưu), `isSaving`

**`notifyChange()`:**

- Đang `conflict` → không làm gì.
- Đang có request lưu → đánh dấu `pendingChangeAfterSave`.
- Ngược lại → chuyển `dirty` và (khởi động lại) debounce **2000 ms** → `saveNow(undefined, true)` (autosave).

**`saveNow(changeSummary?, isAutosave=false)`:**

```http
PUT /websites/{w}/pages/{p}/content
X-XSRF-TOKEN: …
{ "document_json": {...}, "lock_version": 7, "change_summary": "…", "is_autosave": true, "seo_metadata": {...} }
```

| Phản hồi | Hệ quả |
|---|---|
| 200 `success` | Nhận `lock_version` mới → `saved`, `lastSavedAt`, reset retry. Nếu có sửa đổi đến trong lúc lưu thì `notifyChange()` lại |
| **409** | `conflict` + `conflictData` → mở `ConflictModal`. Không retry. Tài liệu cục bộ được giữ nguyên |
| Lỗi khác / lỗi mạng | `error`. Autosave retry với backoff `min(1000·2^(n−1), 8000)` ms, tối đa 3 lần. Lưu thủ công không retry |

**Bảo vệ khỏi mất dữ liệu:**

- `beforeunload` cảnh báo khi còn thay đổi chưa lưu, đang lưu, hoặc còn thay đổi chờ.
- `visibilitychange` (tab bị ẩn) gọi `saveNow(undefined, true)` theo kiểu best-effort.

**Trạng thái trên toolbar:**

| Trạng thái | Biểu tượng | Nhãn |
|---|---|---|
| Đang lưu | `Loader2` quay | "Saving..." |
| `conflict` | `AlertTriangle` | "Conflict" |
| `error` | `AlertCircle` | "Save Error" |
| dirty | `Save` (viền amber) | "Save Draft*" |
| sạch | `CheckCircle2` xanh | "Saved" |

SEO metadata được lưu **cùng** request nội dung (qua `getSeoMetadata`); không có endpoint SEO riêng.

### 6.7 `EditorCanvas.vue` và `NodeRenderer.vue` — canvas đệ quy

**`EditorCanvas`:**

- Dải header hiển thị "`{label}` Viewport (`{width}`px simulated canvas)" và badge `Schema v1`.
- Khung có class `VIEWPORT_PRESETS[vp].widthCss`: desktop `w-full max-w-[1280px]`, tablet `w-[768px]`, mobile `w-[375px]`. Có thanh "trình duyệt giả" với 3 chấm, tiêu đề trang và nhãn "Live Preview".
- Click vào nền (`e.target === e.currentTarget`) → `clearSelection`.

**`NodeRenderer`** là registry phía editor:

```ts
const rendererMap: Record<CoreComponentType, any> = { Section: SectionRenderer, Container: ContainerRenderer,
  Heading: HeadingRenderer, RichText: RichTextRenderer, Image: ImageRenderer, Button: ButtonRenderer,
  Hero: HeroRenderer, Columns: ColumnsRenderer, CardGrid: CardGridRenderer, FAQ: FAQRenderer,
  Spacer: SpacerRenderer, Divider: DividerRenderer };
const effectiveProps = computed(() => resolveAllResponsiveProps(props.node, props.viewport));
const isHiddenOnActiveViewport = computed(() => effectiveProps.value.display === 'hidden');
const componentProps = computed(() => { const { display: _, ...rest } = effectiveProps.value; return rest; });
```

- Mỗi node được bọc trong `<div :data-node-id :data-node-type>`:
  - được chọn: `ring-2 ring-primary ring-offset-2` (ẩn trên viewport hiện tại thì thay bằng vòng amber nét đứt, opacity 75%);
  - hover: `ring-1 ring-primary/40`;
  - ẩn mà không được chọn: viền nét đứt, opacity 40%.
- Click → `stopPropagation` + `select(id)`. Mouseenter/leave → `hover(id|null)`.
- Node được chọn (không read-only) có `NodeToolbar` nổi phía trên. Toolbar hiển thị type, và với node không phải root thì có thêm Move Up / Move Down / Duplicate / Delete.
- **Error boundary:** `onErrorCaptured` bắt lỗi của renderer con, hiển thị hộp "Error rendering {type}: …" và trả `false` để lỗi không lan ra cả canvas. Type lạ hiển thị "Unknown component: {type}".
- **Container:** trong `<slot/>`, khi có con thì đặt `DropZone(index 0)`, sau đó với mỗi con là `NodeRenderer` đệ quy (depth+1) rồi `DropZone(index+1)`. Khi không có con thì đặt một `DropZone` "Empty Container".
- Sự kiện được re-emit qua từng cấp lên tới `EditorCanvas`, rồi tới `EditorShell`.

### 6.8 Kéo-thả (D-016) — `ComponentLibrary` → `DropZone`

Dùng **HTML5 drag & drop gốc**, không thư viện (thêm dưới 1 KB bundle), kèm click-to-insert cho người dùng bàn phím.

```ts
// Nguồn kéo (ComponentLibrary)
e.dataTransfer.effectAllowed = 'copy';
e.dataTransfer.setData('application/json', JSON.stringify({ type }));
e.dataTransfer.setData('text/plain', type);
```

- **`DropZone`** (props `parentId`, `insertIndex`, `readOnly`, `emptyContainer`):
  - `dragover/dragenter` → `preventDefault`, `dropEffect='copy'`, bật highlight;
  - `drop` → parse JSON → emit `dropComponent({type, parentId, index: insertIndex})`.
- **Hiển thị:** vùng chèn giữa các node là dải cao 2 px vô hình, khi kéo qua thì thành vạch `bg-primary/80`. Container rỗng có khung nét đứt 90 px.
- **Giới hạn:** kéo-thả chỉ dùng để **chèn component mới**. Sắp xếp lại node có sẵn chỉ làm được bằng Move Up/Down (hoán đổi trong cùng cha). Không thể kéo để đổi thứ tự hay đổi node cha, dù `moveNode` đã hỗ trợ ở tầng state.

### 6.9 `ComponentLibrary.vue` — palette (panel trái)

- Khi mount gọi `GET /api/components/registry`. Có skeleton loading (6 ô), Alert lỗi với nút "Retry Connection", và trạng thái tìm kiếm rỗng với nút "Reset filters". Danh sách component **không viết cứng** (D-015).
- Tìm kiếm cục bộ theo label/type/category. Các pill danh mục lấy từ `COMPONENT_CATEGORIES` (all, layout, typography, media, interactive, content). Kết quả nhóm theo danh mục.
- Mỗi thẻ có: icon theo `iconMap` (Section `Square`, Container `Box`, Heading `Heading`, RichText `FileText`, Image `Image`, Button `MousePointerClick`, Hero `Sparkles`, Columns `Columns`, CardGrid `Grid3X3`, FAQ `HelpCircle`, Spacer `MoveVertical`, Divider `Minus`), nhãn "Container"/"Leaf Component", badge danh mục và nút "+". Thẻ có `draggable` trừ khi đang read-only.
- Click thẻ hoặc nút "+" → `insertComponent(type)` (quy tắc chèn ở §6.3).

### 6.10 `InspectorPanel.vue` — panel thuộc tính (panel phải)

- **Props:** `selectedNode`, `componentMetadata`, `readOnly`, `isRoot`, `currentViewport`, `websiteId`.
- **Emit:** `updateProp`, `updateStyle`, `resetStyle`, `deleteNode`, `duplicateNode`, `close`.

Field được **sinh từ schema**. Mỗi key của `componentMetadata.props` được xếp vào tab theo tập key cố định:

```ts
const CONTENT_PROP_KEYS = new Set(['text','content','src','alt','caption','label','url','title','subtitle','ctaText','ctaUrl','items']);
const STYLE_PROP_KEYS   = new Set(['level','align','padding','background','maxWidth','aspectRatio','variant','size','gap','style','color','count','columns','centered']);
// còn lại → tab Settings (thực tế chỉ có Button.target)
```

Khi đổi node được chọn, Inspector mở tab đầu tiên có field theo thứ tự Content → Style → Settings.

**Tab Content** (luôn đọc giá trị desktop vì không có prop nội dung nào là responsive):

| Điều kiện schema | Widget |
|---|---|
| `type==='array' && key==='items'` | Repeater FAQ (Question `Input` + Answer `Textarea`) |
| `isHtml` | `Textarea` monospace, kèm ghi chú HTML sẽ bị sanitize |
| `isUrl` | `Input` có icon link. Riêng `Image.src` có thêm nút "Choose from Library" |
| còn lại | `Input` với `maxlength` và bộ đếm `n/maxLength` |

**Tab Style:**

- "Component Visibility" có trên mọi node. Ở desktop chỉ là dòng giải thích. Ở tablet/mobile là select Visible/Hidden, kèm badge "Overridden" + Reset hoặc dòng "Inherited from …".
- Widget: `enum` → `<select>`; integer không có enum → number; boolean → checkbox; còn lại → text.
- Giá trị đọc qua `resolveResponsiveProp(node, key, viewport)`. Ở viewport khác desktop, key responsive có badge override/kế thừa.

**Tab Settings:** các key còn lại, và thẻ "Node Identity" (ID, type, số con).

**Banner:** "Viewer mode" khi read-only; "Editing {viewport} styles / Overrides cascade" khi ở tablet/mobile.

**Đường ghi:**

```ts
const handlePropChange = (key, value) => {
    if (props.readOnly) return;
    const resolved = coercePropValue(propSchemas.value[key], value);      // F-01: ép integer/boolean
    if (currentViewport !== 'desktop' && isResponsiveProp(selectedNode.type, key))
        emit('updateStyle', { viewport: currentViewport, key, value: resolved });
    else emit('updateProp', { key, value: resolved });
};
```

`coercePropValue` (`lib/inspectorHelpers.ts`): `integer → parseInt`, `boolean → Boolean`, còn lại giữ nguyên. File này cũng có `canPerformNodeAction()` (đã có test nhưng component chưa dùng).

**Tích hợp media (D-020):**

```ts
const handleMediaSelect = (asset) => {
  handlePropChange('src', asset.url);
  if (!String(node.props.alt ?? '').trim() && asset.alt_text) handlePropChange('alt', asset.alt_text);
};
```

### 6.11 `EditorToolbar.vue` và `ViewportSwitcher.vue`

`EditorToolbar` là component trình bày thuần, không có state riêng.

- **Nhóm trái:** `SidebarTrigger`, link quay về `/websites/{id}`, nút bật/tắt Component Library, breadcrumb `{website} / {page}`, badge trạng thái (published `CheckCircle2` / archived `destructive` / Draft).
- **Nhóm giữa:** Undo/Redo (disabled theo `canUndo/canRedo` và quyền), `ViewportSwitcher`.
- **Nhóm phải:** badge role ("Read Only (Viewer)" màu amber hoặc "Editor"), nút bật/tắt Inspector, **Preview**, **Save as Template** (chỉ editor), **Revisions**, **SEO**, **nút Save** (state machine ở §6.6), **Publish** (disabled khi đang publish hoặc đang lưu).
- **Emit:** `update:viewport`, `toggleLeftSidebar`, `toggleRightPanel`, `undo`, `redo`, `saveAsTemplate`, `save`, `openRevisions`, `openSeo`, `publish`, `preview` (`sharePreview` đã khai báo nhưng chưa dùng).

`ViewportSwitcher` là một `role="radiogroup"` gồm 3 nút Desktop (`Monitor`), Tablet (`Tablet`), Mobile (`Smartphone`), tooltip lấy từ `VIEWPORT_PRESETS[id].description`. Cấu hình preset (D-014) nằm ở `config/editor.ts`:

```ts
desktop: { width: 1280, widthCss: 'w-full max-w-[1280px]' }
tablet:  { width: 768,  widthCss: 'w-[768px]' }
mobile:  { width: 375,  widthCss: 'w-[375px]' }
```

`useEditorState.ts` giữ state UI thuần: `viewport` (mặc định desktop), `leftSidebarOpen`, `rightPanelOpen` (đều true). State này không được lưu lại giữa các lần tải trang. Các trường `searchQuery/selectedCategory/resetFilters` trong composable này chưa được dùng; `ComponentLibrary` tự giữ ref riêng.

### 6.12 Media picker (`components/media/*`)

- **`MediaLibraryModal`** (shadcn `Dialog`):
  - Props `open`, `websiteId`, `canManage`; emit `select(asset)`. Có 2 tab: "Browse Library" và "Upload New".
  - Danh sách: `GET /websites/{id}/media?page=&per_page=24&search=&type=`, tìm kiếm debounce 300 ms, lọc MIME (JPEG/PNG/WebP/GIF).
  - Upload xong → chuyển sang tab Browse và chọn sẵn asset mới. Xoá item cuối cùng của một trang → lùi về trang trước.
- **`MediaGrid`:** lưới 2/3/4 cột, skeleton, empty state, phân trang ("Showing page X of Y").
- **`MediaCard`:** chọn được bằng bàn phím (`role=button`, Enter/Space), thumbnail lazy, badge W×H, tên file, `formatted_size`.
- **`MediaDetailsPanel`:** preview, copy URL, sửa alt text (`PUT /websites/{w}/media/{id}`), xoá (`DELETE`). Phản hồi 422 `media_in_use` → hiển thị danh sách `referencing_pages` và chặn xoá.
- **`MediaUploadDropzone`:** kéo-thả hoặc chọn file, kèm alt text. Kiểm tra phía client: ≤ 5 MB, chỉ JPEG/PNG/WebP/GIF, **SVG bị từ chối kèm thông báo riêng**. Upload qua `POST /websites/{id}/media` (FormData) và ánh xạ lỗi 422 `errors.file`.
- **Nơi dùng:** picker `src` của Image (Inspector) và picker ảnh OG (SEO modal).

### 6.13 Các modal khác

- **`RevisionsModal`:**
  - `GET .../revisions`. Mỗi dòng có chip `#n`, tóm tắt (hoặc "Autosave checkpoint"/"Manual snapshot"), badge Autosave/Manual, "Latest", thời gian, người tạo.
  - Nút **Restore** (disabled ở dòng mới nhất) → `window.confirm` → `POST .../revisions/{id}/rollback` → emit `restored({document_json, lock_version})`.
  - Chân modal ghi rõ "past revisions are never deleted".
- **`ConflictModal`:** "Conflict Detected … (Server Version #{n})", hai nút **Reload Server Version** / **Overwrite (Keep Mine)**.
- **`PublishSuccessModal`:** URL public (copy được, hiện "Copied" trong 2 giây) và nút "Visit Site" (`target=_blank rel=noopener noreferrer`).
- **`SeoSettingsModal`** (`v-model:open` + `v-model` `PageSeoMetadata`):
  - Tab **Google:** SERP preview, meta title (đếm/60, amber khi vượt), meta description (đếm/160), canonical override, `noindex`.
  - Tab **Social:** card OpenGraph, ảnh OG (chọn từ thư viện), `og_title`, `og_description`.
  - Khi lưu: trim, chuỗi rỗng thành `null` → emit `update:modelValue` → `save` → `EditorShell` gọi `saveNow('Updated SEO metadata')`.
  - Các trường `twitter_card`, `nofollow`, `schema_type`, `structured_data_json` chưa có control.
- **`SaveAsTemplateModal`:** name (bắt buộc), description, category (mặc định `general`) → `POST /websites/{id}/templates` với `document_json` hiện tại → đóng sau 1,2 giây.

### 6.14 Các màn hình ngoài editor

| Màn hình | File | Nội dung |
|---|---|---|
| Danh sách website | `Websites/Index.vue` | Thẻ website (role badge, `pages_count`), empty state |
| Tạo website | `Websites/Create.vue` | `useForm({name, slug})`, tự sinh slug |
| Chi tiết website | `Websites/Show.vue` | 3 tab: **Pages** (bảng title/slug/status/updated, nút Builder/Edit/Delete), **Team Members** (đổi role editor/viewer, xoá, thêm theo email; Owner chính bị khoá), **Website Settings** (name/slug, Danger Zone xoá website) |
| Tạo page | `Pages/Create.vue` | `useForm({title, slug, template_id})`; lưới `TemplateCard` (`role=radio`, màu theo category, badge Global); mặc định chọn template `blank`; slug tự sinh khi blur nếu đang trống |
| Sửa metadata page | `Pages/Edit.vue` | `title`, `slug`, `status` → `PUT /websites/{w}/pages/{p}` (đổi slug có thể sinh redirect 301) |
| Dashboard | `Dashboard.vue` | Placeholder của starter kit |
| Settings/Auth | `settings/*`, `auth/*` | Profile, mật khẩu, 2FA, passkey, giao diện sáng/tối |

**Chưa có UI** cho: thư viện media dạng trang riêng, quản lý template (list/sửa/xoá), quản trị form submission, danh sách/rollback release, cây layers, share preview link. Backend đều đã có endpoint JSON (xem [§21](#21-hạn-chế-đã-biết-và-khoảng-trống)).

---

## 7. Lưu trữ draft, khoá lạc quan và revision

Service: `app/Domains/Pages/Services/PagePersistenceService.php` (hằng `AUTOSAVE_MILESTONE_SECONDS = 300`).

### 7.1 Mô hình (D-025)

- **Draft** = `pages.document_json` (jsonb, có thể thay đổi) cùng `pages.lock_version` (bigint, khởi đầu 1). Autosave tần suất cao chỉ ghi vào đây, nên editor tải bằng một truy vấn không join.
- **Revision** = `page_revisions`: snapshot bất biến (`revision_number`, `document_json`, `document_hash`, `is_autosave`, `change_summary`, `created_by`, chỉ có `created_at`).

### 7.2 `savePageContent(...)` — đường lưu chính

Endpoint: `PUT /websites/{website}/pages/{page}/content` (`PageContentController@update`, FormRequest `SavePageContentRequest`: `document_json` array bắt buộc, `lock_version` int ≥ 1, `change_summary` ≤ 255, `is_autosave` bool, `seo_metadata` nullable array; authorize IDOR 404 + `update`).

1. `DocumentValidator::validate`. Lỗi → **422** `validation_failed` + `details[]`.
2. Quét media của tài liệu **mới** (trước transaction): `indeterminate` → 422 `media_scan_indeterminate`; media của website khác hoặc không tìm thấy → 422 `media_asset_unavailable`.
3. `DB::transaction`:
   1. `SELECT … FROM pages WHERE id=? AND website_id=? FOR UPDATE`. Không thấy → 404.
   2. **Khoá lạc quan:** `lock_version !== submitted` → **409** `document_conflict` + `current_lock_version`, `updated_at`.
   3. Quét media của tài liệu **cũ** trên chính row đã khoá.
   4. Lấy MED advisory lock trên **hợp** asset ID cũ ∪ mới (tăng dần), rồi kiểm tra lại các asset mới có `ACTIVE`, chưa xoá mềm, đúng website (`FOR UPDATE`).
   5. Ghi `document_json`; nếu có `seo_metadata` thì chuẩn hoá qua `PageSeoManager::normalizeSeoMetadata`. Sau đó `lock_version++`, `updated_by`.
   6. Gọi `evaluateAndCreateRevision()` (D-028).
4. Trả **200** `{lock_version, updated_at, revision_id, revision_number}` (hai trường revision là `null` nếu không tạo revision).

### 7.3 Chính sách tạo revision chống phình (D-028)

```text
hash = sha256( json_encode( ksort_recursive(document), UNESCAPED_SLASHES|UNESCAPED_UNICODE ) )
latest = revision mới nhất (FOR UPDATE)

nếu hash == latest.document_hash            → KHÔNG tạo (dedup)
nếu chưa có revision nào                     → tạo #1 ("Initial version" / "Initial autosave")
nếu là lưu thủ công (Save Draft / Ctrl+S)    → luôn tạo N+1 ("Manual save" hoặc summary)
nếu là autosave và latest cũ hơn 300 giây    → tạo N+1 ("Autosave checkpoint")
còn lại                                      → chỉ cập nhật draft
```

`PageRevision::canonicalizeDocument()` sắp xếp đệ quy key của các mảng kết hợp, nên hash ổn định bất kể thứ tự key.

### 7.4 Rollback draft (D-029)

`POST /websites/{w}/pages/{p}/revisions/{revision}/rollback` → `rollbackToRevision()`:

1. Revision phải thuộc page, page phải thuộc website; nếu không → 404.
2. Validate tài liệu lịch sử (422 nếu lỗi), quét media của nó.
3. Transaction:
   1. Khoá row page, quét draft cũ.
   2. Lấy MED lock trên hợp asset ID, kiểm tra lại asset.
   3. `max(revision_number) FOR UPDATE + 1`.
   4. Ghi draft = tài liệu được khôi phục, `lock_version++`, **chèn revision N+1** "Restored from Revision #k".
4. Trả `{document_json, lock_version, revision_id, revision_number}`.

Lịch sử không bao giờ bị sửa: rollback cũng có thể được "rollback" lại (giống `git revert`). Rollback **không** kiểm tra `lock_version`, nên nó luôn thắng, và editor khác sẽ nhận 409 ở lần lưu kế tiếp.

API đọc: `GET .../revisions` (danh sách), `GET .../revisions/{revision}`.

---

## 8. Media Library

Thư mục: `app/Domains/Media`, `app/Http/Controllers/MediaAssetController.php`, `app/Console/Commands/PruneUnreferencedMediaCommand.php`.

### 8.1 Lưu trữ (D-019)

- File đặt ở disk `public`, đường dẫn `media/{website_id}/{uuid}.{ext}` → URL `/storage/media/{website_id}/{uuid}.ext` (cần `php artisan storage:link`).
- Tên file do server sinh (UUID). Tên gốc chỉ lưu vào cột `original_filename` (đã qua `basename()`).
- Nginx phục vụ ảnh trực tiếp, không tốn worker PHP. Thư mục `/storage/media/` bị cấm thực thi script (D-046).

### 8.2 Kiểm tra upload (D-021) — `MediaStorageService::validateImageFile`

Theo thứ tự (lỗi ném `ValidationException` với key `file`):

1. File tồn tại và đọc được.
2. Kích thước ≤ **5 MB** (5 242 880 byte).
3. **Nhận diện MIME bằng magic bytes** (`finfo(FILEINFO_MIME_TYPE)`), bỏ qua header/đuôi file client gửi lên.
4. MIME thuộc `image/jpeg`, `image/png`, `image/webp`, `image/gif`. **SVG bị cấm.**
5. `getimagesize()` đọc được (chỉ parse header, dưới 100 KB RAM, không re-encode).
6. MIME từ finfo **phải khớp** MIME từ header ảnh (chống giả mạo).
7. Kích thước ảnh từ 16×16 đến 4096×4096.

`store()` lưu file rồi tạo row `status=ACTIVE`. Nếu insert DB lỗi thì xoá file vừa ghi (không để lại file mồ côi). FormRequest: `file required|file|max:5120`, `alt_text nullable|max:255`.

### 8.3 Vòng đời và trạng thái (`MediaAssetStatus`)

```text
ACTIVE ──(DELETE, không còn tham chiếu)──► PENDING_DELETION + deleted_at
PENDING_DELETION ──(pruner, hết grace period)──► PURGING ──(quét sạch + unlink OK)──► force-delete row
PURGING ──(vẫn còn tham chiếu / quét lỗi / unlink lỗi)──► PENDING_DELETION
PURGING kẹt > 1 giờ ──(watchdog)──► PENDING_DELETION (file còn) | force-delete (file đã mất)
```

`PURGED` có định nghĩa nhưng không bao giờ được gán, vì row bị force-delete. Model event: soft delete → tự chuyển sang `PENDING_DELETION`.

### 8.4 `MediaUsageScanner` — phát hiện tham chiếu (D-022, D-030)

**`findUsagesInWebsite($website, $asset)`** quét 3 nguồn và trả danh sách `{id,title,slug,reference_type: confirmed|indeterminate}`:

1. `pages.document_json` của website;
2. **mọi `page_revisions`** của các page thuộc website (D-030: revision lịch sử bảo vệ media, nên rollback không bao giờ trỏ tới file đã bị xoá);
3. `page_templates` của website **và** template global.

**Kiểm tra fail-closed** (`inspectDocumentReference`):

- Lọc nhanh: nếu tên file UUID không xuất hiện trong chuỗi → chắc chắn không tham chiếu.
- Có xuất hiện nhưng JSON hỏng hoặc UTF-8 lỗi → `referenced=true, indeterminate=true` → **chặn xoá**.
- Còn lại → duyệt mọi chuỗi trong `props` và `styles` đệ quy.

**So khớp chính xác** (`isAssetReferencedInUrl`): `rawurldecode` + `parse_url(PATH)`. Path chuẩn hoá phải **bằng** `storage_path` hoặc `storage/{storage_path}`, không so khớp chuỗi con. Cách này chống va chạm tiền tố.

**`inspectAssetReferences($doc, $website)`** (dùng cho writer) trả `{asset_ids, indeterminate, has_foreign_media, foreign_asset_ids, unresolved_media_paths}`. URL ngoài (ví dụ `https://cdn…`) không chứa `media/` thì bị bỏ qua, không cần khoá.

### 8.5 Xoá media (`MediaAssetController::destroy`)

1. IDOR (asset phải thuộc website, nếu không → 404), policy `delete`.
2. Transaction → MED lock asset → đọc lại `FOR UPDATE`. Không còn `ACTIVE` → 422 `media_already_pending_deletion`.
3. `findUsagesInWebsite`: lỗi quét → 422 `media_scan_error`; có tham chiếu → **422 `media_in_use`** + `referencing_pages`.
4. Không còn tham chiếu → `PENDING_DELETION` + soft delete. **Không unlink file ở bước này**; file chỉ bị xoá khỏi đĩa bởi pruner sau grace period.

Các endpoint khác: `GET` (lọc `search`, `type`, `per_page` 1–48, mặc định 24), `POST` (201), `PUT` (chỉ sửa `alt_text`).

### 8.6 Pruner `media:prune-unreferenced`

Tuỳ chọn: `--hours=24`, `--force` (cutoff = now), `--website=`, `--watchdog-only`.

1. **Watchdog:** các asset `PURGING` có `updated_at` cũ hơn 1 giờ → `tryLockAsset` (không chờ; lock đang bị giữ nghĩa là có worker còn sống → bỏ qua) → file còn thì về `PENDING_DELETION`, file mất thì force-delete.
2. **Quét chính:** asset đã xoá mềm có `deleted_at ≤ cutoff`. Với mỗi asset, trong transaction riêng:
   1. Lấy lock chặn (`lockAsset`), đọc lại `FOR UPDATE`, kiểm tra lại trạng thái và cutoff.
   2. **Path containment:** path phải bắt đầu bằng `media/{website_id}/` và không chứa `..`. Vi phạm → log "Security violation".
   3. Chuyển `PURGING`, chạy `findUsagesInWebsite`. Còn tham chiếu hoặc quét lỗi → về `PENDING_DELETION`.
   4. Unlink file (file đã mất thì coi là idempotent). Unlink lỗi → về `PENDING_DELETION`.
   5. `forceDelete()`.

Lệnh in ra số asset đã xoá/bỏ qua/lỗi; exit code `FAILURE` nếu có lỗi.

---

## 9. Templates

### 9.1 Mô hình (D-024)

Bảng `page_templates`:

- `website_id = NULL` → **template hệ thống (global)**. Bất biến với người dùng (update/delete → 403 "Global starter templates are immutable."), và không được tham chiếu media của website nào.
- `website_id = X` → **template tuỳ biến**. Owner/editor tạo; chỉ được tham chiếu media `ACTIVE` của chính website đó. Truy cập chéo website → 404.
- Category được phép: `general, blank, landing, portfolio, lead_gen, business`.
- Partial unique index: `(website_id, slug) WHERE website_id IS NOT NULL` và `(slug) WHERE website_id IS NULL`.

### 9.2 Khởi tạo và đổi ID node — `TemplateInstantiationService::instantiate()`

1. Bắt buộc có `root`.
2. Deep clone cây và **sinh ID mới cho mọi node**: `node_{type8}_{8 hex ngẫu nhiên}{4 ký tự uniqid}`, duy nhất trong cây. Nhờ vậy hai page tạo từ cùng một template không bao giờ trùng ID node.
3. Dựng `{schemaVersion:1, root, pageSettings:{title, description: template.description}}`.
4. **Validate** kết quả; lỗi → `InvalidArgumentException`.

### 9.3 Luồng tạo page (`PageController::store`)

```text
Tạo page DRAFT, lock_version=1
├─ có template_id (Rule::exists: template active, global hoặc của website)
│     → instantiate → lưu document_json → revision #1 "Initialized from template: {name}"
│     → redirect websites.pages.builder
└─ không có → createEmptyDocument(title) → redirect websites.show
```

`PageController::builder` cũng khởi tạo template theo `?template=` nếu `document_json` đang `null`.

### 9.4 API `PageTemplateController` (JSON)

| Route | Hành vi |
|---|---|
| `GET websites/{w}/templates` | Thành viên bất kỳ. Template global + của website, đang active. Lọc `category`, `search`. Global xếp trước |
| `POST websites/{w}/templates` | Owner/editor. Validate → quét media → transaction + MED lock + kiểm tra asset active → slug duy nhất từ `Str::slug(name)` (thêm hậu tố `-2`, `-3`… khi trùng). Trả 201 |
| `GET …/{template}` | Global ai cũng xem được; custom template của website khác → 404 |
| `PUT …/{template}` | Global → 403. Khoá row template, quét **tài liệu cũ trên row đã khoá**, MED lock hợp asset ID, kiểm tra lại, cập nhật một phần |
| `DELETE …/{template}` | Global → 403 |
| `POST …/{template}/instantiate` | Template inactive → 404. Trả `{template_id, template_name, document}` |

### 9.5 Seeder

`StarterTemplateSeeder` tạo 5 template global: `blank` (Blank Canvas), `business-landing`, `portfolio-agency`, `product-waitlist`, `lead-generation`. Mỗi fixture được validate theo schema v1. Seeder **không phá huỷ**: slug đã có thì giữ nguyên, trừ khi chạy với `--force`. Lệnh: `php artisan templates:seed [--force]`.

---

## 10. Publishing pipeline, release, preview và public delivery

Thư mục: `app/Domains/Publishing`.

### 10.1 Khái niệm và trạng thái

| Khái niệm | Lưu ở | Tính chất |
|---|---|---|
| Draft | `pages.document_json` | Có thể thay đổi, khoá lạc quan |
| Revision | `page_revisions` | Bất biến, có `document_hash` |
| Release | `page_releases` (UUID PK) + thư mục trên disk `local` | Bất biến: `index.html` + `manifest.json` |

`PageReleaseStatus`: `pending`, `active`, `superseded`, `failed`, `pruned`.
`PageStatus`: `draft`, `published`, `archived`.

**Bất biến ở tầng DB:**

```sql
CREATE UNIQUE INDEX page_releases_one_active_per_page ON page_releases (page_id) WHERE status = 'active';
```

**Máy trạng thái release:**

```text
createCandidate            → PENDING (chưa niêm phong)
stageAndSeal thành công    → PENDING (đã niêm phong: directory_path + manifest_json)
stageAndSeal lỗi           → FAILED (xoá staging, error_message ≤ 1000 ký tự)
transaction kích hoạt      → ACTIVE (ACTIVE cũ → SUPERSEDED)
rollback(target)           → target ACTIVE lại (Case A) | release mới ACTIVE (Case B)
releases:reconcile         → PENDING quá TTL → FAILED
releases:prune-superseded  → SUPERSEDED cũ → PRUNED (xoá thư mục)
```

### 10.2 `PagePublishingService::publish()` — kích hoạt hai pha (D-031, D-032, D-034)

**Phase 1 — Pre-flight (transaction ngắn, khoảng dưới 10 ms):**

1. `PagePublishingLock::tryLockPage` = `pg_try_advisory_xact_lock((0x505542 << 40) | page_id)` (namespace `'PUB'`). Không lấy được → `PublishConcurrencyException` → **409 `publish_in_progress`**.
2. Khoá row page `FOR UPDATE`. `document_json` null → lỗi.
3. `validateForPublishing()` validate schema rồi chạy semantic preflight. Image `src` rỗng/whitespace → 422 `validation_failed`, `details[]` có node path; draft save và preview vẫn có thể giữ placeholder Image rỗng.
4. Tính hash chuẩn. Nếu trùng revision mới nhất → **tái dùng** revision đó (idempotent). Nếu khác → tạo revision N+1 "Published release".
5. `inspectAssetReferences` trên revision đích → MED lock asset ID (tăng dần) → mọi asset phải `ACTIVE`, chưa xoá mềm, đúng website. Nếu không → `MediaUnavailableException` (422).
6. `ReleaseStagingService::createCandidate()` → release `PENDING`, `release_number = max+1` (dưới khoá row page).
7. Commit, giải phóng mọi advisory lock.

**Phase 2 — Render và niêm phong (không giữ transaction hay lock nào):** `stageAndSeal()` (§10.3). Media vẫn an toàn vì revision đích đã tồn tại trong `page_revisions`, nơi scanner coi là nguồn tham chiếu (D-030/D-034).

**Phase 3 — Kích hoạt (transaction ngắn, khoảng dưới 5 ms):**

```sql
-- dưới FOR UPDATE của row page
UPDATE page_releases SET status='superseded' WHERE page_id=? AND status='active';
UPDATE page_releases SET status='active', activated_at=now() WHERE id=?;
UPDATE pages SET published_revision_id=?, status='published' WHERE id=?;
```

**Bất biến:**

- Release đang ACTIVE **không bao giờ bị mất** khi publish lỗi ở bất kỳ bước nào, vì nó chỉ bị đụng tới ở Phase 3 (D-042).
- Tối đa 1 release ACTIVE trên mỗi page, do index DB đảm bảo.
- Không giữ lock trong lúc render hay ghi đĩa, nên không gây nghẽn connection pool.

**Ánh xạ lỗi (`PagePublishingController`):**

| Exception | HTTP | `error` |
|---|---|---|
| `PublishConcurrencyException` | 409 | `publish_in_progress` |
| `MediaUnavailableException` | 422 | `media_asset_unavailable` |
| `PublishValidationException` | 422 | `validation_failed` (+ `details`) |
| `PublishRenderingException` | 422 | `render_failed` (+ `details`) |
| `ReleaseStagingException` | 500 | `staging_failed` |
| khác (gồm `PublishFailedException`) | 500 | `publish_failed` |

Thành công → `{success, message, release, public_url}`.

### 10.3 `ReleaseStagingService::stageAndSeal()` — đóng gói release

Thư mục (disk `local`, gốc `storage/app/private`):

```text
releases/{website_id}/{page_id}/staging_{release_uuid}/            ← tạm
releases/{website_id}/{page_id}/r_{release_number}_{hash8}/        ← đã niêm phong
    ├── index.html
    └── manifest.json
```

1. Không chấp nhận release `ACTIVE`; yêu cầu có page và revision. Các segment path phải khớp `/^[a-zA-Z0-9_\-]+$/`.
2. Thư mục đích đã tồn tại → **FAILED**, không ghi đè (fail-closed).
3. Xoá staging cũ (nếu có), tạo staging mới (0755).
4. `PageSeoManager::buildSeoPayload()` → `DocumentHtmlRenderer::renderDocument($doc, $seo, previewMode:false)`.
5. Ghi `index.html`, kiểm tra không rỗng, tính SHA-256.
6. Ghi `manifest.json`:

   ```json
   { "release_uuid": "…", "page_id": 12, "page_revision_id": 98, "release_number": 7,
     "schema_version": 1, "renderer_version": 2, "document_hash": "…",
     "html_bytes": 8894, "html_hash": "sha256…",
     "artifacts": [{ "path": "index.html", "bytes": 8894, "sha256": "…" }],
     "media_asset_ids": [3, 9], "created_at": "2026-10-24T10:00:00+00:00" }
   ```

7. **Verify staging** trước khi niêm phong (§10.4).
8. **`rename(staging → sealed)`** nguyên tử. Staging và release phải cùng mount filesystem.
9. Cập nhật DB: `directory_path`, `manifest_json`. Trạng thái **vẫn là PENDING**; staging không bao giờ tự kích hoạt.
10. Bất kỳ lỗi nào → xoá staging, release chuyển `FAILED`, ném lại exception.

### 10.4 `ReleaseManifestVerifier` — kiểm tra toàn vẹn

**Kiểm tra trên thư mục:**

- Không có `..` hoặc NUL trong path; `realpath` tồn tại.
- `manifest.json` đọc được và là JSON hợp lệ.
- `index.html` không rỗng, chứa `<!DOCTYPE html>`, SHA-256 bằng `html_hash`, kích thước bằng `html_bytes`.
- `artifacts` là list khác rỗng. Mỗi artifact không có `..` hay `/` đầu, `realpath` nằm trong thư mục release, kích thước và hash khớp.

**Kiểm tra trên model:** `release_uuid`, `page_id`, `page_revision_id`, `release_number` trong manifest phải khớp DB, và `manifest_json.html_hash` trong DB phải khớp manifest trên đĩa. Nhờ vậy phát hiện được sửa đổi trái phép ở **cả hai phía**.

### 10.5 `DocumentHtmlRenderer` — biên dịch AST → HTML5 tĩnh (D-031)

- `render($doc, $previewMode)`:
  - Preview dùng schema validation thường để giữ placeholder; publish dùng publish validation gồm semantic Image source. Lỗi → `PublishRenderingException` có `details` theo path.
  - Đệ quy `renderNode`: depth > 10 → ném lỗi; type lạ → ném lỗi; chỉ render con khi `allowChildren`; `match` gọi 12 hàm `render{Type}`.
  - **Publish mode:** fail-closed, lỗi ở một node làm hỏng cả bản build.
  - **Preview mode:** node lỗi được thay bằng `<div class="preview-node-error …">[Preview Warning: …]</div>`.
- **Escape:** mọi text và attribute qua `htmlspecialchars(ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')`. RichText `content` và FAQ `answer` sanitize lại bằng `HtmlSanitizer`. `Image.src`, `Button.url`, `Hero.ctaUrl` kiểm tra lại bằng `isSafeUrl`.
- Mỗi phần tử có `id="{node.id}"`; responsive rule được tổng hợp theo §5.4.
- `renderDocument($doc, $meta, $preview, $bodyPrefixHtml)` bọc thành trang HTML5 đầy đủ:
  - `<html lang="en">`, charset, viewport, `<title>` (`meta.title` → `pageSettings.title` → "Published Page"), meta description;
  - canonical; `meta_tags` (đã được `PageSeoManager` escape sẵn);
  - **CSS:** nếu `meta.stylesheet_url` được truyền thì dùng `<link rel="stylesheet">`. Mặc định, renderer **nhúng inline** CSS đã build (`<style>…</style>`): tra entry `resources/css/app.css` trong `public/build/manifest.json` để lấy file có hash (`inlineCompiledStylesheet()`, cache theo process). Lý do: Vite đổi tên file theo hash ở mỗi lần build, và mỗi lần deploy thay toàn bộ `public/build`, nên một release bất biến không được link tới file có thể biến mất. CSS nhúng nằm trong `html_hash` của manifest. Không tìm thấy bản build → `Log::warning` và render không có CSS (không phát ra link chết);
  - `<body class="min-h-screen bg-white text-gray-900 antialiased">`; `bodyPrefixHtml` chỉ dùng cho markup nội bộ tin cậy như preview banner và được chèn đúng sau opening body tag.
- **Tất định:** cùng tài liệu luôn cho HTML giống nhau từng byte (có test). Không có JS phía client.
- Benchmark: tài liệu 51 node render trong khoảng 1,87 ms; phần markup khoảng 8,7 KB, cộng thêm khoảng 135 KB CSS nhúng (khoảng 20 KB sau gzip).
- **Hệ quả của CSS nhúng:** đổi giao diện (class Tailwind, theme) chỉ có hiệu lực trên trang public sau khi **publish lại**. Class mà renderer PHP dùng phải xuất hiện trong mã nguồn được Tailwind quét (file PHP trong `app/` được quét tự động), nếu không chúng sẽ thiếu trong bản build.

### 10.6 Rollback release (D-033) — `rollbackToRelease()`

Endpoint: `POST /websites/{w}/pages/{p}/releases/{release}/rollback` (cần quyền `update`). Danh sách: `GET …/releases`.

- Release thuộc page khác → lỗi.
- **Case A — chuyển tức thì:** `index.html` và `manifest.json` tồn tại, và `verifyDirectoryWithReport` hợp lệ. Một transaction: ACTIVE hiện tại → SUPERSEDED, target → ACTIVE (`activated_at` mới), page trỏ tới revision của target. Trả `instant:true`.
- **Case B — biên dịch lại:** file đã bị prune, mất, hoặc hỏng → `stageRevision()` tạo **release mới** từ revision bất biến rồi kích hoạt. Trả `recompiled:true`.
- **Bất biến cách ly draft:** rollback release **không bao giờ** đụng tới `pages.document_json`. Rollback draft (§7.4) và rollback release là hai thao tác độc lập.
- Nếu bất kỳ bước verify hay re-render nào lỗi → release ACTIVE hiện tại giữ nguyên.

### 10.7 Preview (`PagePreviewController`)

| Route | Truy cập | Hành vi |
|---|---|---|
| `GET websites/{w}/pages/{p}/preview` (`websites.pages.preview.show`) | Đăng nhập + quyền `view` (viewer cũng xem được) | Render draft, hoặc `?revision={id}` |
| `POST …/preview/share-link` (`.share-link`) | Thành viên | Trả `URL::temporarySignedRoute('preview.signed', +60 phút)` + `expires_at` |
| `GET preview/pages/{page}` (`preview.signed`) | Không cần đăng nhập, chỉ cần chữ ký hợp lệ | Chữ ký sai hoặc hết hạn → 403 |

- Render với `previewMode=true`, tiêu đề "{title} (Preview)", banner amber "PREVIEW MODE" được truyền qua `bodyPrefixHtml`. `<!DOCTYPE html>` luôn là byte đầu tiên; public output không truyền prefix nên không có banner. Draft rỗng được thay bằng tài liệu tối thiểu "Draft is empty".
- Header: `Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private`, `Pragma: no-cache`, `Expires: 0`, `X-Robots-Tag: noindex, nofollow, noarchive, nosnippet`.

### 10.8 Phục vụ trang public (`PublicPageController::show`)

Route `GET sites/{website:slug}/{page:slug}` (`public.sites.pages.show`), không cần đăng nhập, khai báo **cuối cùng** để sitemap/robots được match trước.

1. Tìm website theo slug (không có → 404), rồi tìm page theo `(website_id, slug)`.
2. Page không có hoặc không `PUBLISHED` → `RedirectManager::resolveRedirect` → redirect 301/302 nếu khớp, không thì 404.
3. Tìm release ACTIVE. Không có, không có `directory_path`, hoặc thiếu `index.html` → 404.
4. **ETag** = `manifest_json.html_hash` (fallback: SHA-256 của file). `If-None-Match` khớp → **304**.
5. 200 + nội dung file, `Content-Type: text/html; charset=UTF-8`, `Cache-Control: public, max-age=300, must-revalidate`.

HTML được PHP đọc từ storage private (không do Nginx phục vụ trực tiếp). Ngoài HTTP cache, không có cache tầng ứng dụng.

**Unpublish:** không có endpoint riêng. Đổi `status` sang draft/archived qua `PUT …/pages/{p}` thì route public trả 404. Row ACTIVE vẫn được giữ, và sẽ phục vụ lại khi status quay về `published`.

---

## 11. SEO, redirect, sitemap, robots

### 11.1 `PageSeoManager` (D-037)

Lưu ở cột `pages.seo_metadata` (jsonb), ghi cùng request `PUT …/content`.

**`normalizeSeoMetadata()`:**

| Trường | Chuẩn hoá |
|---|---|
| `meta_title`, `og_title` | `strip_tags`, trim, ≤ 120 |
| `meta_description`, `og_description` | `strip_tags`, trim, ≤ 320 |
| `canonical_url`, `og_image_url` | trim, ≤ 2048 |
| `twitter_card` | `summary` \| `summary_large_image` (mặc định) |
| `noindex`, `nofollow` | bool |
| `schema_type` | `WebPage` (mặc định) \| `Article` \| `Organization` |
| `structured_data_json` | array \| null |

**Cascade fallback** (`buildSeoPayload`):

```text
title       = meta_title ?? page.title
description = meta_description ?? ''
canonical   = canonical_url ?? url("/sites/{website.slug}/{page.slug}")
og:title    = og_title ?? meta_title ?? page.title
og:desc     = og_description ?? meta_description ?? ''
robots      = noindex ? "noindex, nofollow" : (nofollow ? "index, nofollow" : "index, follow")
```

Sinh các thẻ `og:type/title/url/description/image` (ảnh được chuyển thành URL tuyệt đối), `twitter:card/title/description/image`, và JSON-LD `<script type="application/ld+json">`. JSON-LD dùng `structured_data_json` nếu có, không thì `{@context, @type, name, description, url}`, encode với `JSON_HEX_TAG|AMP|APOS|QUOT` để chống thoát khỏi `</script>`.

> SEO được **biên dịch vào `index.html` lúc publish**. Đổi SEO thì phải publish lại mới có hiệu lực trên trang public.

### 11.2 `RedirectManager` (D-040)

- Bảng `page_redirects(website_id, source_slug, target_slug, status_code 301|302, is_active)`, unique `(website_id, source_slug)`.
- **Tự động:** `PageController::update` tạo redirect 301 khi slug thay đổi **và** page đang published hoặc đã có `published_revision_id`.
- `createRedirect(old, new)`:
  - bỏ qua khi rỗng hoặc trùng nhau;
  - xoá redirect ngược `new→old` (phá vòng 2 nút);
  - **làm phẳng chuỗi**: mọi redirect đang trỏ tới `old` được trỏ thẳng tới `new`;
  - `updateOrCreate`.
- `resolveRedirect()`: đi theo tối đa `MAX_CHAIN_DEPTH = 5` bước, có phát hiện vòng lặp.

### 11.3 Sitemap và robots (D-038, D-039)

- `GET sites/{w}/sitemap.xml`: W3C `urlset`, chỉ gồm page `PUBLISHED`, có release ACTIVE, không `noindex`. `lastmod` = `activated_at`; `changefreq=weekly`; `priority` 1.0 cho slug `home`/`index`, còn lại 0.8.
- `GET sites/{w}/robots.txt`: `Allow: /sites/{slug}/`, `Disallow: /preview/`, `/websites/`, `/dashboard`, kèm dòng `Sitemap:`.
- Cả hai trả `Cache-Control: public, max-age=3600`.

---

## 12. Forms (nội dung động)

Hợp đồng D-036: trang tĩnh không chứa token, cookie hay secret. Form gửi tới một endpoint động riêng.

- **Endpoint public:** `POST sites/{website:slug}/forms/{form_name?}` (`public.sites.forms.submit`), middleware `throttle:5,1` (5 request/phút/IP).
- **`FormSubmissionService::submit()`:**
  1. **Honeypot:** trường `_hp_verification` có giá trị → **âm thầm bỏ**, không lưu, vẫn trả 201.
  2. Loại mọi key bắt đầu bằng `_` (`_token`, `_page_slug`…). Làm sạch đệ quy: xoá khối `<script>/<style>`, `strip_tags`, `trim`.
  3. Payload rỗng → 422. JSON lớn hơn **64 KB** → 422.
  4. `form_name` chỉ nhận `[a-zA-Z0-9_-]`, mặc định `contact`, tối đa 64 ký tự.
  5. Lưu `form_submissions` (UUID): `payload` jsonb, `ip_address`, `user_agent` (≤ 500), `status='new'`.
- **Phản hồi:** 201 `{success, message:'Thank you for your submission.', id}` với `Cache-Control: no-store, no-cache, private, must-revalidate` và `Pragma: no-cache`.
- **Quản trị (JSON):** `GET websites/{w}/submissions` (lọc `form_name`, `status`, phân trang 25), `PATCH …/{id}` (`status ∈ new|read|archived|spam`), `DELETE …/{id}`.
- **Chưa có component `Form`** trong registry, nên trang publish chưa sinh được form. Endpoint chỉ dùng được với HTML do hệ thống tích hợp ngoài gửi tới. Route nằm trong nhóm `web`, nên request cross-origin vẫn phải qua kiểm tra CSRF/origin của Laravel.

---

## 13. Danh tính, phân quyền và HTTP layer

### 13.1 Middleware (`bootstrap/app.php`)

Nhóm `web` được nối thêm:

1. **`SecurityHeadersMiddleware`** (D-041): `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`, `X-Frame-Options: SAMEORIGIN` (nếu chưa được đặt).
2. `HandleAppearance` (cookie `appearance`, không mã hoá).
3. `HandleInertiaRequests` (chia sẻ `name`, `auth.user`, `sidebarOpen`).
4. `AddLinkHeadersForPreloadedAssets`.

Exception trả về JSON khi path là `api/*` hoặc request `expectsJson()`. Health check: `/up` (framework) và `/api/health` (`SELECT 1` → `{status: healthy|degraded, timestamp, services.database}`, 200 hoặc 503, không lộ chi tiết lỗi).

### 13.2 Ma trận phân quyền

| Khả năng | Owner | Editor | Viewer | Người ngoài |
|---|:-:|:-:|:-:|:-:|
| Xem website, pages, builder (read-only), preview, revisions, releases, media, templates, submissions | ✔ | ✔ | ✔ | 404/403 |
| Tạo/sửa/xoá page, lưu nội dung, publish, rollback, upload/sửa/xoá media, CRUD custom template | ✔ | ✔ | ✘ | ✘ |
| Sửa trạng thái/xoá form submission | ✔ | ✔ | ✘ | ✘ |
| Sửa/xoá website, quản lý thành viên | ✔ | ✘ | ✘ | ✘ |

- **IDOR → 404, không phải 403** (D-008): truy cập tài nguyên của website khác luôn trả 404 để không lộ sự tồn tại.
- **Bất biến Owner** (D-006): tạo website và tạo membership owner nằm trong **cùng một transaction**. `WebsiteMembershipPolicy` cấm đổi role hoặc xoá Owner chính. Membership chỉ gán được `editor`/`viewer`; không cấp được `owner` qua API.
- Policy: `WebsitePolicy`, `WebsiteMembershipPolicy`, `PagePolicy`, `MediaAssetPolicy`, `PageTemplatePolicy`. Tất cả dựa trên `Website::hasMember($user, $roles)` và `getMemberRole()`; mutation submission dùng `WebsitePolicy::manageFormSubmissions` cho Owner/Editor.

### 13.3 Bảng route

**Đã xác thực (`auth`, `verified`):**

| Method · URI | Tên | Controller |
|---|---|---|
| `GET dashboard` | `dashboard` | Inertia |
| resource `websites` (trừ edit) | `websites.*` | `WebsiteController` |
| `POST/PUT/DELETE websites/{w}/memberships[/{m}]` | `websites.memberships.*` | `WebsiteMembershipController` |
| `GET/POST/PUT/DELETE websites/{w}/media[/{asset}]` | `websites.media.*` | `MediaAssetController` |
| `GET websites/{w}/pages/create` · `POST websites/{w}/pages` | `websites.pages.create/store` | `PageController` |
| `GET …/pages/{p}/edit` · `PUT …/pages/{p}` · `DELETE …/pages/{p}` | `websites.pages.edit/update/destroy` | `PageController` |
| `GET …/pages/{p}/builder` (+ `/editor`) | `websites.pages.builder` / `.editor` | `PageController@builder` |
| `PUT …/pages/{p}/content` | `websites.pages.content.update` | `PageContentController` |
| `GET …/revisions`, `GET …/revisions/{r}`, `POST …/revisions/{r}/rollback` | `websites.pages.revisions.*` | `PageRevisionController` |
| `POST …/pages/{p}/publish` | `websites.pages.publish` | `PagePublishingController` |
| `GET …/releases`, `POST …/releases/{r}/rollback` | `websites.pages.releases.*` | `PageReleaseController` |
| `GET …/preview`, `POST …/preview/share-link` | `websites.pages.preview.*` | `PagePreviewController` |
| templates (6 route) | `websites.templates.*` | `PageTemplateController` |
| `GET/PATCH/DELETE websites/{w}/submissions[/{s}]` | `websites.submissions.*` | `FormSubmissionAdminController` |

**Public (không cần đăng nhập):**

| Method · URI | Tên |
|---|---|
| `GET api/health` · `GET up` | `api.health` |
| `GET api/components/registry` | `api.components.registry` |
| `POST sites/{website:slug}/forms/{form_name?}` (throttle 5/1) | `public.sites.forms.submit` |
| `GET sites/{website:slug}/sitemap.xml` | `public.sites.sitemap` |
| `GET sites/{website:slug}/robots.txt` | `public.sites.robots` |
| `GET preview/pages/{page}` (signed) | `preview.signed` |
| `GET sites/{website:slug}/{page:slug}` | `public.sites.pages.show` |

### 13.4 Form request chính

| Request | Quy tắc |
|---|---|
| `StorePageRequest` | `title` ≤ 255; `slug` alpha_dash ≤ 255, unique theo website (D-007); `template_id` tồn tại trong số template active global/của website |
| `UpdatePageRequest` | Như trên + `status` `Rule::enum(PageStatus)`; slug unique (bỏ qua chính nó) |
| `Store/UpdateWebsiteRequest` | `slug` alpha_dash, unique toàn cục |
| `StoreWebsiteMembershipRequest` | Chỉ owner; `email` phải tồn tại trong users; `role ∈ editor|viewer`; từ chối thành viên đã có |
| `SavePageContentRequest` | Xem §7.2 |

---

## 14. Mô hình dữ liệu (PostgreSQL)

| Bảng | Cột chính | Ràng buộc và index |
|---|---|---|
| `websites` | `owner_id` FK users (cascade), `name`, `slug`, `status` (`active`), `settings_json` jsonb | `slug` unique; index `status`, `owner_id` |
| `website_memberships` | `website_id`, `user_id` (cascade), `role` | unique `(website_id,user_id)`; index `(website_id,role)` |
| `pages` | `website_id` (cascade), `title`, `slug`, `document_json` jsonb, `seo_metadata` jsonb, `status` (`draft`), `lock_version` bigint (1), `published_revision_id` FK (nullOnDelete), `created_by`/`updated_by` | unique `(website_id,slug)`; index `(website_id,status)`. **Không có soft delete** |
| `page_revisions` | `page_id` (cascade), `revision_number`, `schema_version`, `document_json` jsonb, `document_hash` char(64), `change_summary`, `is_autosave`, `created_by`, `created_at` (không có `updated_at`) | unique `(page_id,revision_number)`; index `(page_id,created_at)`, `(page_id,document_hash)` |
| `page_releases` | `id` **uuid**, `page_id`, `page_revision_id` (cascade), `release_number`, `status`, `schema_version`, `renderer_version`, `manifest_json` jsonb, `directory_path`, `error_message`, `created_by`, `activated_at` | index `(page_id,release_number)`, `(page_id,status)`; **partial unique `page_releases_one_active_per_page`** |
| `media_assets` | `website_id`, `storage_disk` (`public`), `storage_path` (512), `original_filename`, `status`, `mime_type`, `size_bytes`, `width`, `height`, `alt_text`, `uploaded_by`, `deleted_at` | `storage_path` unique; index `(website_id,created_at)`, `(website_id,original_filename)`, `(website_id,status)` |
| `page_templates` | `website_id` nullable, `name`, `slug`, `description`, `category`, `schema_version`, `document_json` **json**, `thumbnail_path`, `is_active`, `created_by` | partial unique theo scope (§9.1); index `(category,is_active)` |
| `form_submissions` | `id` uuid, `website_id`, `page_id` (nullOnDelete), `form_name`, `payload` jsonb, `ip_address`, `user_agent`, `status` | index `(website_id,created_at)`, `(website_id,form_name,status)` |
| `page_redirects` | `website_id`, `source_slug`, `target_slug`, `status_code` (301), `is_active` | unique `(website_id,source_slug)`; index `(website_id,is_active)` |

Ngoài ra có các bảng chuẩn của framework: `users` (có cột 2FA), `passkeys`, `sessions`, `cache`, `jobs`, `migrations`.

**Quan hệ Eloquent đáng chú ý:**

- `Page`: `revisions()` (theo `revision_number desc`), `publishedRevision`, `releases()`, `activeRelease()` (HasOne `status=active`).
- `Website`: `owner`, `memberships`, `users` (pivot `role`), `pages`, `mediaAssets`, `templates`, `formSubmissions`, `redirects`.

**Hiệu ứng cascade:** xoá website hoặc page sẽ cascade các row trong DB, nhưng **file trên đĩa (media, thư mục release) không bị dọn**. Xem §21.

**Chiến lược migration:** chỉ thêm (additive). Không sửa migration đã chạy ở môi trường dùng chung; luôn tạo migration mới. Unique slug được đảm bảo ở DB, không chỉ ở UI.

---

## 15. Mô hình đồng thời (concurrency) tổng hợp

### 15.1 Các cơ chế

| Cơ chế | Khoá / key | Dùng ở đâu | Mục đích |
|---|---|---|---|
| Khoá lạc quan | `pages.lock_version` | `PUT /content` | Chống ghi đè từ tab/người sửa cũ (409) |
| Row lock | `SELECT … FOR UPDATE` | Page, template, asset, revision mới nhất | Tuần tự hoá ghi trên cùng một bản ghi |
| **MED advisory lock** (D-023) | `pg_advisory_xact_lock((0x4D4544 << 40) \| asset_id)` | Mọi writer có tham chiếu media + delete + pruner | Loại trừ lẫn nhau giữa ghi tài liệu và dọn media |
| **PUB advisory lock** (D-032) | `pg_try_advisory_xact_lock((0x505542 << 40) \| page_id)` | Phase 1 của publish | Chặn publish đồng thời trên cùng page |
| Partial unique index | `page_releases (page_id) WHERE status='active'` | Phase 3 / rollback | Đảm bảo tối đa 1 release ACTIVE |
| Rename nguyên tử | `rename(staging → r_n_hash8)` | Staging | Không bao giờ có thư mục release ghi dở |

Key 64-bit = 24 bit namespace (`'MED'`, `'PUB'` dạng ASCII) cộng 40 bit ID (tối đa khoảng 1,1 nghìn tỷ). Cách này tránh giới hạn `(int4,int4)` và không có va chạm hash.

### 15.2 Giao thức MED (D-023) — 3 bất biến

1. **Không có tham chiếu hỏng:** không tài liệu đã commit nào (page, revision, template) được tham chiếu asset đã bị unlink hoặc đang `purging`.
2. **Không unlink sớm:** không xoá file khi còn tham chiếu đã commit hoặc đang có transaction commit tham chiếu tới nó.
3. **Bền vững khi crash:** transaction lỗi hay rollback không để lại trạng thái disk/DB mâu thuẫn. Advisory lock gắn với backend process của PostgreSQL nên tự giải phóng khi worker chết; không bao giờ rò lock.

**Giao thức của writer:**

```text
1. Trích asset ID từ tài liệu MỚI (và CŨ, đọc trên row đã khoá)
2. BEGIN
3. Khoá row của chính bản ghi (FOR UPDATE)
4. sort(ids ∪, SORT_NUMERIC) → pg_advisory_xact_lock từng ID   ← thứ tự tăng dần ⇒ không deadlock
5. SELECT … FROM media_assets WHERE id IN (new) AND status='active' AND deleted_at IS NULL
          AND website_id = X FOR UPDATE   → thiếu thì 422 media_asset_unavailable
6. Ghi tài liệu (+ revision)
7. COMMIT  (lock tự giải phóng)
```

**Writer xen kẽ với pruner:** nếu writer đang chờ lock của một asset mà pruner đang xoá, sau khi pruner commit, writer đọc lại thì không còn row → 422. Bất biến 1 luôn được giữ.

**Môi trường test:** trên SQLite, `MediaConcurrencyLock` ghi warning và **bỏ qua lock** (chỉ ở env `testing`; ở env khác sẽ ném lỗi), còn `PagePublishingLock` luôn trả true. Vì vậy 26 test concurrency chỉ chạy được trên PostgreSQL (`phpunit.pgsql.xml`).

---

## 16. Lệnh bảo trì và lịch chạy

`routes/console.php`:

```php
Schedule::command('media:prune-unreferenced --hours=24')->daily();
Schedule::command('media:prune-unreferenced --watchdog-only')->hourly();
Schedule::command('releases:prune-superseded')->daily();      // keep=5, hours=24
Schedule::command('releases:reconcile')->everyThirtyMinutes(); // staging-ttl=15
```

| Lệnh | Tuỳ chọn | Việc làm | Exit |
|---|---|---|---|
| `media:prune-unreferenced` | `--hours=24 --force --website= --watchdog-only` | §8.6 | FAILURE nếu có lỗi |
| `releases:reconcile` (D-032/D-042) | `--disk=local --dry-run --staging-ttl=15` | (1) Xoá thư mục `staging_*` cũ hơn TTL. (2) Release PENDING quá TTL → FAILED "Abandoned uncommitted candidate…". (3) Verify mọi release ACTIVE; hỏng → `Log::critical` (không tự sửa) | luôn 0 |
| `releases:prune-superseded` (D-035) | `--disk=local --dry-run --keep=5 --hours=24` | Với mỗi page: release SUPERSEDED có `page_revision_id ≠ pages.published_revision_id`, cũ hơn 24 giờ, sau 5 bản mới nhất, mỗi lượt tối đa 100 → xoá thư mục, đổi thành PRUNED. **Không bao giờ đụng file media** | luôn 0 |
| `templates:seed` | `--force` | §9.5 | |
| `app:production-health-check` (D-047) | `--json` | DB + latency; migration chưa chạy; thư mục ghi được (tự tạo 0775 nếu thiếu); `APP_KEY` có, `APP_DEBUG` tắt ở production; probe cache put/get/forget | SUCCESS/FAILURE |
| `app:benchmark-performance` | `--iterations=10` | Tài liệu 51 node: validate + render N lần, ngân sách < 50 ms, Δmem < 8 MB, peak < 64 MB; trên pgsql chạy `EXPLAIN` 4 truy vấn nóng với `enable_seqscan=off` | |

**Chính sách giữ release (D-035):** giữ release ACTIVE, release ứng với `published_revision_id`, 5 release superseded gần nhất, mọi release trong 24 giờ gần nhất, và release đang xử lý. Rollback tới release đã bị prune sẽ tự biên dịch lại (Case B).

---

## 17. Vận hành production: deploy, rollback, backup, runbook

### 17.1 Hạ tầng mục tiêu

| Thành phần | Công nghệ | Vị trí |
|---|---|---|
| Reverse proxy / TLS | Nginx 1.24+ | :80 → 301 sang :443; TLS 1.2/1.3, HSTS, OCSP stapling |
| Runtime | PHP 8.4-FPM | `/var/run/php/php8.4-fpm.sock` |
| Database | PostgreSQL 18 | `127.0.0.1:5432`, DB `page_builder_prod`, user `pentagon_app` |
| Mã nguồn | Symlink | `/var/www/pentagon/current` → `releases/{TIMESTAMP}` |
| Trạng thái dùng chung | Thư mục | `/var/www/pentagon/shared/` (`.env`, `storage/`) |
| Scheduler | systemd timer | `pentagon-scheduler.timer` (`OnCalendar=*:0/1`) → `artisan schedule:run` |
| User | `www-data:www-data` | Không chạy bằng root |

**`.env.production.example`:**

- `APP_ENV=production`, `APP_DEBUG=false`, `BCRYPT_ROUNDS=12`
- `LOG_CHANNEL=daily`, `LOG_LEVEL=warning`, `LOG_DAILY_DAYS=14`
- `DB_CONNECTION=pgsql` (**bắt buộc**: MED lock ném lỗi trên driver khác)
- `SESSION_DRIVER=database`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`
- `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `FILESYSTEM_DISK=local`

### 17.2 Deploy không downtime (D-044) — `deploy/scripts/deploy.sh [branch]`

```bash
sudo -u www-data env PENTAGON_REPOSITORY_URL=<git-url> /var/www/pentagon/deploy/scripts/deploy.sh master
```

1. Kiểm tra `shared/.env` tồn tại; tạo cây `shared/storage/...`.
2. `git clone --depth 1 --branch $BRANCH` từ `PENTAGON_REPOSITORY_URL` vào `releases/$TIMESTAMP`.
3. Symlink `shared/.env` và `shared/storage` vào release.
4. `composer install --no-dev --optimize-autoloader`.
5. `npm ci && npm run build`, sau đó xoá `node_modules`.
6. `php artisan migrate --force --isolated`; `config:cache`, `route:cache`, `view:cache`.
7. Tạo symlink tạm rồi `mv -T` nguyên tử để đổi `current` sang release mới; reload service từ `PENTAGON_PHP_FPM_SERVICE` (mặc định `php8.4-fpm`).
8. `app:production-health-check`. Lỗi → **tự động trỏ symlink về release trước**, reload, rồi exit 1.
9. Xoá release cũ, chỉ giữ 5 bản gần nhất.

> Migration chạy **trước** khi đổi symlink, nên mọi migration phải tương thích ngược với code đang chạy (additive).

### 17.3 Rollback ứng dụng — `deploy/scripts/rollback.sh [TIMESTAMP]`

Không có tham số: trỏ `current` về release liền trước. Có tham số: trỏ về release chỉ định. Sau đó reload FPM, cache lại config, kiểm tra health (khoảng dưới 3 giây). Lưu ý: script **không rollback migration**.

### 17.4 Backup và diễn tập khôi phục (D-045)

- `deploy/scripts/backup.sh /var/backups/pentagon` (hằng ngày lúc 02:00):
  - `pg_dump` định dạng custom (`.dump`);
  - nén `storage/app/public/media` và `storage/app/private/releases`;
  - tạo `SHA256SUMS`;
  - không tự xóa backup cũ; retention phải được vận hành bằng job riêng có kiểm soát.
- `deploy/scripts/restore-rehearsal.sh`: khôi phục `.dump` mới nhất vào DB tạm `pentagon_rehearsal_restore`, so số row của `pages`, `page_revisions`, `page_releases`, `media_assets`, ghi báo cáo, rồi drop DB tạm. Không đụng dữ liệu thật.

Script fail rõ ràng nếu media/release source thiếu hoặc `tar` lỗi, và từ chối ghi đè thư mục backup trùng timestamp.

### 17.5 Nginx (`deploy/nginx/pentagon.conf`, D-046)

- Header: `X-Frame-Options SAMEORIGIN`, `nosniff`, `Referrer-Policy`, `HSTS max-age=31536000; includeSubDomains`, `Permissions-Policy`.
- `client_max_body_size 10M`; gzip.
- `location ^~ /storage/media/`: ánh xạ MIME ảnh tường minh, `default_type application/octet-stream`, `expires 30d`, **cấm `.php|phtml|phar|sh|pl|py|cgi`**.
- Asset Vite (`css|js|woff2…`): `Cache-Control: public, max-age=31536000, immutable`.
- `location ~ /\.(?!well-known)` → deny (chặn `.git`, `.env`).
- `location /` → `try_files $uri $uri/ /index.php?$query_string`.

### 17.6 Runbook sự cố

| Sự cố | Triệu chứng | Xử lý |
|---|---|---|
| **A. 502 / app không phản hồi** | Nginx trả 502 | `systemctl status php8.4-fpm` → `restart`; xem `/var/log/php8.4-fpm.log`. Gặp `pm.max_children` → kiểm tra nguồn traffic, rate limit `/sites/*/forms/*`, truy vấn chậm |
| **B. OOM** | `dmesg`: "Out of memory: Killed process" | `free -m`; xác nhận `pm.max_children=4`, `memory_limit=128M`, `shared_buffers=128MB`; buffer dưới 300 MB thì `sync; echo 3 > /proc/sys/vm/drop_caches` |
| **C. Cạn connection PostgreSQL** | `SQLSTATE[08006] … remaining connection slots…` | `SELECT count(*), state FROM pg_stat_activity GROUP BY state;` · terminate các connection `idle in transaction` quá 5 phút · `max_connections=30` |
| **D. Trang public 404 / release hỏng** | `/sites/{w}/{p}` trả 404 hoặc HTML thiếu | Kiểm tra page có `status=published` và release ACTIVE; `releases:reconcile` (log critical khi release ACTIVE hỏng); **publish lại** hoặc rollback release (Case B tự biên dịch lại) |
| **E. Đầy đĩa** | "No space left on device" | `df -h /`; `releases:prune-superseded`, `releases:reconcile`, `media:prune-unreferenced --hours=1`; `truncate -s 0` các log |
| **Publish báo 409** | `publish_in_progress` | Đang có publish khác trên cùng page; thử lại sau vài giây |
| **Publish báo 422 media** | `media_asset_unavailable` | Tài liệu tham chiếu asset đã bị xoá mềm hoặc thuộc website khác; thay ảnh rồi lưu lại |

Chẩn đoán: `php artisan app:production-health-check [--json]`; `systemctl status pentagon-scheduler.timer`; `journalctl -u pentagon-scheduler.service -n 50`.

---

## 18. Ngân sách tài nguyên (2 GB RAM / 2 vCPU)

D-043. Máy mục tiêu: 2 vCPU, 2 048 MB RAM, NVMe ≥ 20 GB.

| Thành phần | RAM | Cấu hình |
|---|---|---|
| PostgreSQL 18 | 384 MB | `shared_buffers=128MB`, `work_mem=4MB`, `maintenance_work_mem=64MB`, `effective_cache_size=1024MB`, `max_connections=30`, `wal_buffers=4MB`, `checkpoint_completion_target=0.9` |
| PHP-FPM | 256 MB | `pm=dynamic`, `max_children=4`, `start_servers=2`, `min_spare=1`, `max_spare=3`, `max_requests=1000`, `memory_limit=128M`, `upload_max_filesize=5M`, `post_max_size=8M`, `max_execution_time=30` |
| Nginx + OS | 400 MB | `worker_processes auto`, `worker_connections 1024` |
| Page cache + headroom | ~1 008 MB | `vm.swappiness=10`, `vm.overcommit_memory=1` |

**Số đo (`app:benchmark-performance`, PostgreSQL 18):**

| Chỉ số | Đo được | Ngân sách |
|---|---|---|
| Render tài liệu 50 node | **1,87 ms** | < 50 ms |
| Δ bộ nhớ khi biên dịch | < 1 MB | < 8 MB |
| Peak RSS worker | ~34 MB | < 64 MB |
| Kích thước HTML | 8 894 byte markup + khoảng 135 KB CSS nhúng | < 256 KB |
| Truy vấn release ACTIVE / slug page / slug redirect / submissions | Index Scan (`page_releases_one_active_per_page`, `pages_website_id_slug_unique`, `page_redirects_website_id_source_slug_unique`, `form_submissions_website_id_created_at_index`) | Index Scan |

Quy tắc: không có sequential scan trên đường nóng; không thêm daemon (Redis, SSR, Octane, queue worker thường trực) khi chưa có số đo chứng minh.

---

## 19. Kiểm thử và quality gates

### 19.1 Lệnh

| Kiểm tra | Lệnh | Kết quả gần nhất |
|---|---|---|
| Pest (SQLite) | `php artisan test` | 299 test: **273 pass, 26 skip** (concurrency chỉ chạy trên pgsql), 1 326 assertion |
| Pest (PostgreSQL concurrency) | `php artisan test -c phpunit.pgsql.xml` | 36/36 pass |
| Vitest | `npm run test:frontend` (`vp test --run`) | 85/85 pass, 10 file |
| TypeScript | `npm run types:check` (vue-tsc) | 0 lỗi |
| Biome | `npm run check` | sạch |
| Pint | `./vendor/bin/pint --test` | sạch |
| PHPStan | `vendor/bin/phpstan analyse` | 0 lỗi |
| Build | `npm run build` | thành công |

### 19.2 Backend

**Unit:**

- `DocumentValidatorTest` (23): mọi quy tắc validator, kể cả responsive styles.
- `ComponentRegistryTest` (5).
- `HtmlSanitizerTest` (5).
- `DocumentHtmlRendererTest` (15): 12 component, XSS, depth, URL, tính tất định, fail-closed so với preview, class responsive.

**Feature:**

| Mảng | File (số test) | Trọng tâm |
|---|---|---|
| Media | `MediaAssetTest` (24), `MediaConcurrencyTest` (18, TC01–08 & TCT01–10 chỉ pgsql) | SVG, MIME giả mạo, giới hạn, RBAC, IDOR, scanner khớp chính xác và fail-closed, grace period, path containment, thứ tự lock và không deadlock, khôi phục sau crash |
| Pages | `PageBuilderTest` (9), `PageManagementTest` (6), `PagePersistenceTest` (11), `PageRevisionTest` (7), `PageConcurrencyTest` (4) | 409, dedup và mốc autosave, rollback N+1, rollback transaction |
| Publishing | `PagePublishingServiceTest` (8), `PagePublishingControllerTest` (8), `PagePublishingConcurrencyTest` (4), `ReleaseStagingTest` (14), `PageReleaseModelTest` (10), `PublicPageControllerTest` (5), `PagePreviewControllerTest` (6), `ReleaseCommandsTest` (4) | Hai pha, tái dùng revision, Case A/B, hash manifest, phát hiện sửa đổi, path traversal, partial unique, ETag 200/304, preview hết hạn |
| Chaos | `PublishingChaosTest` (3) | Giữ release ACTIVE khi publish lỗi, rollback release đã prune, reconcile |
| Templates | `PageTemplateDomainAndApiTest` (14), `TemplateMediaOwnershipTest` (8) | Bất biến global, IDOR, seeder, đổi ID, media ngoại/xoá mềm/đang purging |
| SEO | `PageSeoManagerTest` (3), `RedirectManagerTest` (3), `SitemapGeneratorTest` (1), `PublicSeoDeliveryTest` (4) | Fallback, noindex, làm phẳng chuỗi redirect, JSON-LD trong `index.html` |
| Forms | `FormSubmissionTest` (6) | Honeypot, strip HTML, 64 KB |
| Security/Prod | `AuthorizationMatrixTest` (5), `ProductionSmokeTest` (6), `HealthCheckTest` (3) | Header, 404 chéo tenant, ma trận role, không lộ stack trace |

### 19.3 Frontend (`tests/frontend/`, môi trường Node, không mount component)

| File | Trọng tâm |
|---|---|
| `document-state.test.ts` | Insert/delete/duplicate/move, giới hạn, F-05 chèn đúng index, F-06 bỏ chọn khi xoá node tổ tiên, style responsive |
| `editor-history.test.ts` | Snapshot, xoá redo stack khi có thao tác mới, giới hạn 30, khôi phục styles |
| `use-autosave.test.ts` | Debounce, gộp loạt sửa, xếp hàng khi đang lưu, backoff, 409, giữ lockVersion khi lỗi, visibilitychange/beforeunload |
| `responsive.test.ts` | Cascade, override, kế thừa, display mode, allowlist |
| `inspector-helpers.test.ts` | `coercePropValue` (F-01), `canPerformNodeAction` |
| `dom-sanitizer.test.ts` | `isSafeUrl`, `sanitizeHtml` (F-02 bỏ `id`, `on*`, script, `javascript:`) |
| `image-inspector.test.ts` | Ngữ nghĩa media picker (D-020) |
| `media-library.test.ts` | Kiểm tra upload phía client |
| `renderer-registry-compat.test.ts` | Default 12 type, giới hạn khớp backend (F-07) |
| `seo-metadata.test.ts` | Fallback title/canonical, giới hạn ký tự |

---

## 20. Nhật ký quyết định kiến trúc (D-001 → D-047)

Toàn bộ quyết định đều ở trạng thái **Accepted và đã hiện thực**. Chi tiết kỹ thuật của từng quyết định nằm trong các mục tương ứng ở trên.

| ID | Quyết định | Tóm tắt / hệ quả | Mục |
|---|---|---|---|
| D-001 | Modular monolith | Một bản deploy, phân miền rõ, vận hành nhẹ | §2 |
| D-002 | PostgreSQL 18 + JSONB | Bảng quan hệ cho metadata, JSONB cho cây tài liệu đã validate | §14 |
| D-003 | Laravel 13 + Vue 3 + Inertia + TS + Tailwind v4 | Starter kit chính thức | §2.1 |
| D-004 | DB dev riêng `page_builder_dev` | Cô lập môi trường | — |
| D-005 | Health check an toàn `/api/health` | Không lộ secret/SQL | §13.1 |
| D-006 | Bất biến Owner + membership trong cùng transaction | Không leo thang quyền qua API | §13.2 |
| D-007 | Slug unique theo `(website_id, slug)` | Ràng buộc ở DB | §14 |
| D-008 | IDOR → 404 | Không lộ sự tồn tại tài nguyên | §13.2 |
| D-009 | Document Schema v1 | `schemaVersion`, cây root duy nhất | §3 |
| D-010 | Allowlist 12 component + alias | Không chèn được component tuỳ ý | §4 |
| D-011 | Validator đệ quy + giới hạn cứng | 512 KB / depth 10 / 250 node / 50 con | §3.2 |
| D-012 | `symfony/html-sanitizer` | Parser W3C thay regex | §3.5 |
| D-013 | Tách `/edit` (metadata) và `/builder` (`/editor`) | Builder toàn màn hình | §6.2 |
| D-014 | Viewport 1280 / 768 / 375 | Preset tập trung, radiogroup ARIA | §6.11 |
| D-015 | Palette tải động từ registry API | Không viết cứng danh sách | §6.9 |
| D-016 | DnD HTML5 gốc + click-to-insert | Dưới 1 KB, hỗ trợ bàn phím | §6.8 |
| D-017 | Undo/redo bằng snapshot, giới hạn | 30 bước, debounce 300 ms | §6.5 |
| D-018 | `styles.{tablet,mobile}` + allowlist | Không nâng schema; cascade tất định | §5 |
| D-019 | Media trên disk public, phân vùng theo website | Nginx phục vụ trực tiếp | §8.1 |
| D-020 | Lưu URL tương đối trong `Image.src` | Không cần asset ID, tương thích ngược | §8 |
| D-021 | Chỉ ảnh raster, cấm SVG, finfo | 5 MB, 16–4096 px | §8.2 |
| D-022 | Chặn xoá khi còn tham chiếu + xoá mềm + pruner | 422 `media_in_use`, grace 24 giờ | §8.5 |
| D-023 | Hợp đồng đồng thời media (MED lock + state machine) | Không TOCTOU, không ảnh hỏng | §15.2 |
| D-024 | `page_templates` global/website + đổi ID + MED | Template độc lập, an toàn | §9 |
| D-025 | Draft ở `pages.document_json` | Revision là các mốc | §7.1 |
| D-026 | Khoá lạc quan `lock_version` | 409 `document_conflict` | §7.2 |
| D-027 | Autosave debounce 2 s + flush thủ công | Giảm khoảng 80% request | §6.6 |
| D-028 | Chính sách tạo revision chống phình | Hash dedup + mốc 5 phút | §7.3 |
| D-029 | Rollback draft = revision N+1 | Lịch sử bất biến | §7.4 |
| D-030 | Revision lịch sử bảo vệ media | Rollback không bao giờ ra ảnh hỏng | §8.4 |
| D-031 | Renderer HTML PHP + manifest | Không JS client, không SSR daemon | §10.5 |
| D-032 | Kích hoạt hai pha + niêm phong + PUB lock + reconcile | Không có "cửa sổ hỏng" | §10.2 |
| D-033 | Rollback release tách khỏi draft, verify trước | Case A tức thì / Case B biên dịch lại | §10.6 |
| D-034 | Lock media ngắn ở pre-flight | Không giữ lock khi render | §10.2 |
| D-035 | Giữ release có giới hạn (5 + 24 giờ) | Đĩa có giới hạn; tách khỏi media | §16 |
| D-036 | Form động: honeypot, 5/phút, 64 KB, no-store | Không lộ secret trong HTML tĩnh | §12 |
| D-037 | SEO JSONB + cascade fallback + JSON-LD | Biên dịch vào `<head>` | §11.1 |
| D-038 | Sitemap XML tự động | Loại draft và `noindex` | §11.3 |
| D-039 | `robots.txt` động | Chặn `/preview/` | §11.3 |
| D-040 | Redirect 301 khi đổi slug, làm phẳng chuỗi | Giữ SEO | §11.2 |
| D-041 | Security headers toàn cục + ma trận IDOR | | §13 |
| D-042 | Publish chịu lỗi và crash | Giữ ACTIVE, biên dịch lại, dọn mồ côi | §10, §16 |
| D-043 | Ngân sách 2 GB RAM | `max_children=4`, 0 seq scan | §18 |
| D-044 | Deploy symlink nguyên tử | Không downtime, tự rollback | §17.2 |
| D-045 | Backup + diễn tập khôi phục | pg_dump + tar + SHA256 | §17.4 |
| D-046 | Hardening Nginx, cấm thực thi script trong uploads | TLS 1.2/1.3, HSTS | §17.5 |
| D-047 | Health check, smoke test, runbook | MTTR thấp | §16, §17.6 |

**Mẫu ghi quyết định mới:**

```markdown
### D-0XX — Tiêu đề ngắn
- Ngày / Trạng thái: Proposed | Accepted | Rejected | Superseded
- Bối cảnh:
- Các phương án đã cân nhắc:
- Quyết định:
- Hệ quả:
- Bằng chứng (file, test):
```

### 20.1 Lịch sử phát triển (tóm tắt)

| Phase | Nội dung | Module |
|---|---|---|
| 00 | Khảo sát, khả thi, ADR | — |
| 01 | Nền tảng, CI, health check | M01 |
| 02 | Danh tính, membership, CRUD website/page, IDOR | M02, M03 |
| 03 | Document engine, registry, validator, sanitizer | M04, M05 |
| 04 | Editor shell, viewport switcher, palette | M06, M07 |
| 05 | Canvas, DnD, inspector, undo/redo (+ sửa F-01…F-07) | M08–M10 |
| 06 | Responsive, media (D-023), templates | M11–M13 |
| 07 | Lưu trữ, autosave, khoá lạc quan, revision | M14, M15 |
| 08 | Preview, renderer, publish, release | M16, M17 |
| 09 | Form động, SEO, sitemap, robots, redirect | M18, M19 |
| 10 | Hardening, chaos test, benchmark, ngân sách | M20–M22 |
| 11 | Deploy, backup, Nginx, runbook | M23 |

Các lỗi đã sửa ở Phase 05: ép kiểu integer cho enum (KI-007), kiểm tra root động (KI-008), chèn đúng vị trí drop (KI-009), sanitizer bỏ `id` và fail-closed (KI-010), bỏ chọn khi xoá subtree (KI-011).

---

## 21. Hạn chế đã biết và khoảng trống

Các mục dưới đây được xác minh bằng cách đọc code. Mức độ: 🔴 nên xử lý trước khi có traffic thật · 🟠 ảnh hưởng tính năng/UX · 🟡 nợ kỹ thuật.

### 21.1 Publish / render

| # | Mức | Vấn đề | Vị trí |
|---|---|---|---|
| 1 | ✅ | *Đã sửa (2026-10-24):* trang publish từng link tới `/build/assets/app.css` không tồn tại (Vite sinh `app-*.css`), nên mất toàn bộ CSS. Giờ CSS được nhúng inline (§10.5). **Các release đã publish trước bản sửa vẫn không có CSS: cần publish lại** | `DocumentHtmlRenderer::inlineCompiledStylesheet` |
| 2 | ✅ | *Đã sửa Phase 2B:* responsive property trong allowlist được publish thành CSS enum-safe tại 768/1024; renderer tests bao phủ 12 component | `DocumentHtmlRenderer` (§5.4) |
| 3 | ✅ | *Đã sửa Phase 2B:* `tablet=hidden` kế thừa xuống mobile; mobile có thể reset `visible`; editor/public dùng cùng cascade | `responsive.ts`, `DocumentHtmlRenderer` |
| 4 | ✅ | *Đã sửa Phase 2B:* banner được chèn qua `bodyPrefixHtml` bên trong body; DOCTYPE vẫn đứng đầu | `PagePreviewController`, `DocumentHtmlRenderer` |
| 5 | ✅ | *Đã sửa Phase 2B:* publish preflight trả 422 `validation_failed` với `empty_image_source` và node path; preview placeholder vẫn giữ | `DocumentValidator::validateForPublishing` |
| 6 | ✅ | *Đã sửa Phase 2A:* publish dừng khi save thất bại/conflict/exception và không gửi POST publish | `EditorShell.vue`, `publishGuard.ts` |
| 7 | 🟡 | PUB lock chỉ bao Phase 1. Phase 3 dựa vào row lock và partial unique (thực chất là last-writer-wins). Rollback release không lấy PUB lock và không kiểm tra lại media trước khi biên dịch lại (Case B) | `PagePublishingService` |
| 8 | 🟡 | Class `prose` được dùng nhưng `@tailwindcss/typography` không có trong dependency | RichText/FAQ |

### 21.2 Dữ liệu / bảo mật

| # | Mức | Vấn đề | Vị trí |
|---|---|---|---|
| 9 | 🟠 | Bản tài liệu đã sanitize bị bỏ: draft và template lưu JSON gốc (không điền default, không chuẩn hoá alias, HTML chưa sanitize lúc lưu). Đầu ra public vẫn an toàn nhờ sanitize lúc render | `PagePersistenceService`, `PageTemplateController` |
| 10 | ✅ | *Đã sửa Phase 2A:* Viewer chỉ đọc; Owner/Editor mới được sửa/xoá submission; cross-tenant giữ 404 | `FormSubmissionAdminController`, `WebsitePolicy` |
| 11 | 🟡 | Xoá website/page không dọn file media và thư mục release. Thư mục đã niêm phong của release FAILED cũng không bị lệnh nào dọn | Cascade + `releases:reconcile` |
| 12 | 🟡 | Nginx vẫn ánh xạ MIME `svg` trong `/storage/media/` dù SVG bị cấm upload (vô hại, nhưng không nhất quán) | `deploy/nginx/pentagon.conf` |
| 13 | ✅ | *Đã sửa Phase 2A:* archive chứa `private/releases`, lỗi `tar` trả non-zero, fixture checksum/extract restore pass | `deploy/scripts/backup.sh`, `BackupScriptTest.sh` |
| 14 | 🟡 | Chưa có pipeline migration schema; `MediaAssetStatus::PURGED` và `MediaUnavailableException::$unavailableIds` không được dùng; *Endpoint unpublish đã bổ sung tại `PagePublishingController@unpublish`* | — |

### 21.3 Editor / UI

| # | Mức | Vấn đề |
|---|---|---|
| 15 | ✅ | *Đã sửa Package 3:* Kéo thả trực quan sắp xếp lại (reorder) và đổi node cha (reparent) trên Canvas và Layers Panel với cycle/depth/count guard |
| 16 | ✅ | *Đã sửa Package 3 & Phase 5:* Cây Layers Panel, quản lý workspace templates (`Websites/Show.vue`), quản trị submissions (`Websites/Submissions.vue`), danh sách/rollback public releases (`ReleasesModal.vue`), nút share signed preview link (`SharePreviewModal.vue`) |
| 17 | ✅ | *Đã sửa Phase 2B:* `buildEditorResponsivePresentation` áp presentation theo viewport 375/768/1280, độc lập cửa sổ thật |
| 18 | ✅ | *Đã sửa Package 3:* `Esc` kiểm tra focused input/textarea/contentEditable và blur trước khi huỷ chọn node |
| 19 | ✅ | *Đã sửa Phase 3:* Khôi phục revision xoá lịch sử undo và thiết lập boundary thông báo |
| 20 | ✅ | *Đã sửa Phase 2C:* RegistrySession dùng session-level cache 1 lần fetch; `httpClient.ts` dùng chung CSRF/headers |
| 21 | ✅ | *Đã sửa Phase 5:* Đầy đủ controls cho `twitter_card`, `nofollow`, `schema_type`, và `structured_data_json` với realtime JSON-LD syntax validator trong `SeoSettingsModal.vue` |
| 22 | ✅ | *Đã sửa Phase 2C:* `AppLayout` ownership chuẩn hoá; không còn render trùng lặp |
| 23 | ✅ | *Đã sửa Phase 5:* Làm sạch placeholder text cũ trong `Pages/Create.vue` và `Pages/Edit.vue` |
| 24 | ✅ | *Đã sửa Phase 5:* Toàn bộ event handlers và modals (`sharePreview`, `clearHistory`, `releases`, `unpublish`) đã được nối hoàn chỉnh |
| 25 | ✅ | *Đã sửa Phase 3:* Repeater FAQ chặn client 20 items và inspector cung cấp hướng dẫn validation trực tiếp |

### 21.4 Câu hỏi mở (từ giai đoạn khảo sát)

- PostgreSQL chạy cùng host với app hay tách riêng? (ảnh hưởng ngân sách RAM ở §18)
- Có cần custom domain hoặc nhiều domain cho mỗi website không? (hiện public URL là `/sites/{website-slug}/{page-slug}`)
- Những component động nào cần thêm ngoài form?

---

## 22. Quy tắc kỹ thuật khi phát triển tiếp

### 22.1 Trước khi sửa code

1. Đọc file này, các route, migration, test liên quan. Không tự giả định phiên bản framework.
2. Chạy baseline: `php artisan test`, `npm run test:frontend`, `npm run types:check`, `npm run check`, `./vendor/bin/pint --test`.
3. Với thay đổi lớn: viết kế hoạch và tiêu chí chấp nhận trước; ghi quyết định mới vào §20.

### 22.2 Thêm một component mới (checklist)

1. `ComponentRegistry::registerCoreComponents()`: type, label, category, `allowChildren`, `canBeRoot`, `propSchemas`.
2. `RESPONSIVE_OVERRIDABLE_PROPS` (PHP) **và** `lib/responsive.ts` (TS); thêm display mode gốc vào `INTRINSIC_DISPLAY_MODES` / `COMPONENT_DISPLAY_MODES`.
3. `CoreComponentType`, `DEFAULT_COMPONENT_PROPS`, và `CONTAINER_COMPONENT_TYPES` nếu là container (`types/document.ts`).
4. `canvas/renderers/<Type>Renderer.vue` + `rendererMap` trong `NodeRenderer.vue`.
5. `DocumentHtmlRenderer::render<Type>()` + nhánh trong `match`. Dùng **cùng class Tailwind** với renderer Vue; escape text; `isSafeUrl` cho URL; sanitize HTML.
6. Icon trong `ComponentLibrary.iconMap`. Nếu key prop mới thuộc tab Content hoặc Style, thêm vào tập key của `InspectorPanel`.
7. Nếu prop có thể chứa URL media: đảm bảo `MediaUsageScanner` phát hiện được (chuỗi chứa `media/`).
8. Test: `DocumentValidatorTest`, `DocumentHtmlRendererTest`, `renderer-registry-compat.test.ts`, fixture.

### 22.3 Bất biến không được phá

- Document JSON là **dữ liệu**. Chỉ type trong allowlist; validate ở server; client validate chỉ để cải thiện UX.
- Mọi endpoint có scope phải authorize ở server. Không tin `website_id`/`page_id`/role từ client. Truy cập chéo tenant → **404**.
- **Mọi đường ghi tài liệu có tham chiếu media phải theo giao thức MED (§15.2)**: khoá hợp asset cũ ∪ mới, theo thứ tự tăng dần, kiểm tra lại dưới lock.
- Chỉ báo "Saved" khi server xác nhận. Không âm thầm ghi đè bản cũ (dùng `lock_version`).
- Revision và release là bất biến. Rollback tạo bản ghi mới.
- Publish lỗi **không được** làm mất release ACTIVE. Không giữ transaction/lock trong lúc render hay ghi đĩa.
- Không xoá release hay media còn được revision/release tham chiếu. Prune release không bao giờ đụng file media.
- Không đưa secret, token hay dữ liệu cá nhân vào HTML tĩnh.
- Migration chỉ thêm (additive), tương thích ngược với code đang chạy (deploy chạy migrate trước khi đổi symlink).
- Không thêm service hay daemon mới khi chưa có số đo.

### 22.4 Definition of Done

- [ ] Tiêu chí chấp nhận đạt; test phù hợp đã chạy và ghi lại kết quả (nếu chưa chạy được thì nói rõ lý do).
- [ ] Authorization và validation ở server; có test forbidden/IDOR cho endpoint mới.
- [ ] Có test cho đường lỗi và retry, không chỉ happy path.
- [ ] Không có secret trong repo hay log.
- [ ] Cập nhật **file này** nếu hành vi được mô tả thay đổi.

---

## 23. Phụ lục: bảng tra nhanh

### 23.1 Mã lỗi API thường gặp

| HTTP | `error` / `code` | Nguồn | Ý nghĩa |
|---|---|---|---|
| 409 | `document_conflict` | `PUT /content` | `lock_version` cũ: có người hoặc tab khác đã lưu |
| 409 | `publish_in_progress` | `POST /publish` | Đang có publish khác trên page này |
| 422 | `validation_failed` (+`details[]`) | `PUT /content`, `POST /publish` | Tài liệu sai schema hoặc không publishable; Image rỗng dùng `empty_image_source` + node path |
| 422 | `media_scan_indeterminate` | Writer | Không xác định được tham chiếu media (fail-closed) |
| 422 | `media_asset_unavailable` | Writer, publish | Asset thiếu, đã xoá mềm, đang purging hoặc thuộc website khác |
| 422 | `media_in_use` (+`referencing_pages`) | `DELETE /media` | Asset còn được tham chiếu |
| 422 | `media_already_pending_deletion` / `media_scan_error` | `DELETE /media` | |
| 422 | `render_failed` (+`details`) | `POST /publish` | Renderer fail-closed trong staging/rollback (vd. URL không an toàn hoặc revision cũ không render được) |
| 500 | `staging_failed` / `publish_failed` / `rollback_failed` | Publishing | Lỗi đóng gói hoặc lỗi khác |
| 403 | — | Template global, preview signed hết hạn | |
| 404 | — | Truy cập chéo tenant (IDOR), page chưa publish | |

### 23.2 Mã lỗi validator

`max_payload_size_exceeded`, `invalid_json`, `invalid_root_structure`, `missing_schema_version`, `invalid_schema_version_type`, `unsupported_schema_version`, `missing_or_invalid_root`, `missing_root_type`, `invalid_root_component`, `max_nodes_exceeded`, `max_depth_exceeded`, `missing_node_id`, `invalid_node_id_format`, `duplicate_node_id`, `missing_node_type`, `unknown_component_type`, `invalid_props_type`, `invalid_children_type`, `children_not_allowed`, `max_children_exceeded`, `invalid_child_node`, `unknown_prop`, `required_prop_missing`, `invalid_type`, `max_length_exceeded`, `invalid_enum_value`, `unsafe_url`, `empty_image_source` (publish-only), `min_value_exceeded`, `max_value_exceeded`, `max_items_exceeded`, `invalid_item_type`, `required_field_missing`, `invalid_styles_format`, `invalid_viewport_key`, `disallowed_style_override`.

### 23.3 Hằng số quan trọng

| Hằng | Giá trị | Ở đâu |
|---|---|---|
| Debounce autosave | 2 000 ms | `useAutosave` |
| Retry autosave | 3 lần, backoff tối đa 8 s | `useAutosave` |
| Undo | 30 snapshot, debounce 300 ms | `EditorShell` → `useEditorHistory` |
| Mốc revision của autosave | 300 s | `PagePersistenceService::AUTOSAVE_MILESTONE_SECONDS` |
| Upload | 5 MB, 16–4096 px, JPEG/PNG/WebP/GIF | `MediaStorageService` |
| Grace period xoá media | 24 giờ; watchdog PURGING 1 giờ | `media:prune-unreferenced` |
| Preview signed | 60 phút | `PagePreviewController` |
| Cache trang public | `max-age=300` + ETag | `PublicPageController` |
| Cache sitemap/robots | `max-age=3600` | |
| Staging TTL | 15 phút | `releases:reconcile` |
| Giữ release | 5 superseded + 24 giờ | `releases:prune-superseded` |
| Form | 5 request/phút, 64 KB | `throttle:5,1`, `FormSubmissionService` |
| Chuỗi redirect | 5 bước | `RedirectManager::MAX_CHAIN_DEPTH` |
| Namespace lock | MED `0x4D4544`, PUB `0x505542` (`<< 40`) | `MediaConcurrencyLock`, `PagePublishingLock` |

### 23.4 Bản đồ file cốt lõi

```text
app/Domains/DocumentEngine/  DocumentLimits · Registry/ComponentRegistry · Schema/ComponentDefinition
                             Validation/DocumentValidator · Sanitization/HtmlSanitizer
app/Domains/Pages/Services/PagePersistenceService.php
app/Domains/Media/Services/  MediaStorageService · MediaUsageScanner · MediaConcurrencyLock
app/Domains/Templates/       TemplateInstantiationService
app/Domains/Publishing/      Services/{PagePublishingService, ReleaseStagingService, DocumentHtmlRenderer,
                             ReleaseManifestVerifier, PagePublishingLock} · Exceptions/*
app/Domains/SEO/Services/    PageSeoManager · RedirectManager · SitemapGenerator
app/Domains/Forms/Services/  FormSubmissionService
app/Http/Controllers/        Page*, Media*, PageTemplate*, PublicPage*, FormSubmission*, Sitemap, Robots
app/Console/Commands/        PruneUnreferencedMedia · ReconcileReleases · PruneSupersededReleases
                             ProductionHealthCheck · BenchmarkPerformance
resources/js/components/editor/  EditorShell · EditorToolbar · EditorCanvas · ComponentLibrary
                                 InspectorPanel · *Modal · canvas/{NodeRenderer, DropZone, NodeToolbar, renderers/*}
resources/js/composables/        useDocumentState · useEditorHistory · useAutosave · useEditorState
resources/js/lib/                responsive · domSanitizer · inspectorHelpers
resources/js/types/document.ts   Hợp đồng schema phía client
deploy/                          nginx/pentagon.conf · scripts/{deploy,rollback,backup,restore-rehearsal}.sh
                                 systemd/pentagon-scheduler.{service,timer}
```
