# Phase 0 - Baseline Audit

Ngày audit: 2026-10-24
Phạm vi: chỉ đọc, chạy kiểm tra và đo baseline; không sửa code ứng dụng, schema, dependency hay migration.

## 1. Trạng thái repository và môi trường

- Branch hiện tại: `master`, commit `ec7e074` (`fix(publishing): inline compiled stylesheet into release HTML`).
- Working tree trước và sau audit: chỉ có file chưa track `skills-lock.json`; file này được giữ nguyên.
- Không có remote Git được cấu hình. Workflow CI chỉ bắt sự kiện push vào `main`, trong khi branch cục bộ hiện tại là `master` (`.github/workflows/tests.yml:4`).
- Runtime local: Windows, Node `24.11.1`, npm `11.6.4`, PHP `8.3.26`, Composer `2.9.2`, Git `2.52.0`.
- Laravel `13.37.0`, môi trường `local`, debug bật, PostgreSQL là DB đang dùng; cache/queue/session dùng database.
- Tài liệu ghi PHP 8.4 (`PENTAGON_SYSTEM_REFERENCE.md:92`), nhưng `composer.json` cho phép `^8.3` và baseline đang chạy PHP 8.3.26. Đây là chênh lệch tài liệu/môi trường, chưa phải lỗi runtime.
- 18 migration hiện có đều ở trạng thái `Ran`. Audit không chạy migration.

## 2. Stack, entry point và lệnh kiểm tra

| Lớp | Hiện trạng đã xác minh |
|---|---|
| Backend | Laravel modular monolith; entry HTTP ở `bootstrap/app.php`, route ở `routes/web.php` và `routes/api.php` |
| Frontend | Vue 3 + TypeScript + Inertia; entry `resources/js/app.ts`; build bằng Vite Plus |
| Database | PostgreSQL local/prod; PHPUnit mặc định dùng SQLite `:memory:` |
| Storage | Media ở disk `public`; release ở disk `local` dưới `storage/app/private/releases` |
| Package manager | npm với `package-lock.json`; Composer với `composer.lock` |
| Quality gates | `npm run check`, `npm run types:check`, `npm run test:frontend`, `npm run build`, `composer lint:check`, `composer types:check`, `php artisan test`, `composer ci:check` |

Không có OpenAPI spec, Postman collection hay contract API dạng máy đọc trong repo. API hiện được mô tả bằng route, request validation, test và system reference.

## 3. Bản đồ kiến trúc và module

| Module | Trách nhiệm chính | Entry/consumer quan trọng |
|---|---|---|
| `app/Domains/DocumentEngine` | Registry 12 component, schema, validation, sanitization, giới hạn tài liệu | Save, template, preview, publish |
| `app/Domains/Pages` | Draft, optimistic lock, autosave revision, restore revision | `PageContentController`, `PageRevisionController` |
| `app/Domains/Media` | Upload, kiểm tra MIME/dimension, quét reference, advisory lock, prune | Editor media picker, template, save, publish |
| `app/Domains/Templates` | Instantiate template và đổi toàn bộ node ID | Page create/builder |
| `app/Domains/Publishing` | Preflight, render HTML, staging/manifest, activate/rollback release | Preview và public delivery |
| `app/Domains/SEO` | Metadata, redirect, sitemap | Save, publish, public route |
| `app/Domains/Forms` | Public submission, honeypot, payload limit | Public form endpoint/admin JSON |
| `resources/js/components/editor` | Composition root, toolbar, canvas, library, inspector, modal | `Pages/Builder.vue` |
| `resources/js/composables` | Document state, history, autosave, editor UI state | `EditorShell.vue` |
| `resources/js/components/media` | Browse, upload, select, update alt, delete | Image inspector và SEO OG image |

Các boundary backend nhìn chung rõ. Điểm coupling lớn nhất nằm ở frontend: `EditorShell.vue` (609 dòng) điều phối state, persistence, publish, keyboard và modal; `InspectorPanel.vue` (1.099 dòng) chứa nhiều loại control và logic responsive/media/FAQ.

## 4. Luồng nghiệp vụ quan trọng

1. Tạo website và membership Owner; thêm Editor/Viewer.
2. Tạo page từ blank hoặc system/custom template.
3. Chỉnh tree: thêm, chọn, duplicate, delete, move sibling và nested insert.
4. Undo/redo tối đa 30 snapshot; autosave debounce 2 giây.
5. Save draft với `lock_version`; conflict trả 409; revision được tạo theo milestone.
6. Media upload/select; save/template/publish quét ownership và lifecycle; delete fail-closed khi còn reference.
7. Preview draft/revision; tạo signed link 60 phút.
8. Publish ba phase: preflight, seal release, atomic activation; active release cũ được giữ khi lỗi.
9. Public delivery đọc release active, có ETag/cache; slug change có redirect; sitemap/robots được sinh.
10. Rollback draft tạo revision mới; rollback release chuyển pointer hoặc recompile revision.

## 5. Kết quả baseline checks

| Kiểm tra | Kết quả đo tại audit |
|---|---|
| `npm run check` | Pass; 111 file format đúng, 110 file không có warning/lint error; 5,47 giây |
| `npm run types:check` | Pass; 7,06 giây |
| `npm run test:frontend` | Pass; 10 file, 85/85 test; 2,97 giây cho command, Vitest báo 522 ms |
| `npm run build` | Pass; 3.523 module; build 4,17 giây, command 5,51 giây |
| `composer lint:check` | Pass; 5,34 giây |
| `composer types:check` | Fail; 3 lỗi PHPStan; 5,82 giây |
| `php artisan test` | Pass; 299 test, 273 pass, 26 skip, 1.326 assertion; 13,60 giây |
| `composer ci:check` | Fail tại PHPStan; 17,42 giây; test backend không được chạy trong command này sau lỗi |
| `npm audit --omit=dev` | 0 advisory production dependency |
| `composer audit --locked` | 0 advisory, 0 package abandoned |

### Lỗi PHPStan baseline

1. `app/Domains/DocumentEngine/Sanitization/HtmlSanitizer.php:64`: `parse_url()` có thể trả `false`, nhưng giá trị được truyền vào `strtolower()` như string.
2. `app/Domains/DocumentEngine/Validation/ValidationResult.php:17`: thiếu value type cho iterable `$sanitizedDocument`.
3. `app/Http/Controllers/WebsiteController.php:33`: PHPStan không biết property pivot trên `Website`.

System reference ghi PHPStan 0 lỗi (`PENTAGON_SYSTEM_REFERENCE.md:1674`), nên tài liệu không phản ánh baseline hiện tại.

## 6. Baseline hiệu năng đo được

### Server render và query plan

Lệnh: `php artisan app:benchmark-performance --iterations=50` trên PHP 8.3.26/PostgreSQL local.

| Metric | Kết quả |
|---|---:|
| Document | 50 node |
| Validate + render trung bình | 2,02 ms/document |
| Memory delta | 0,03 MB |
| Peak process memory | 36 MB |
| HTML tạo ra | 146.686 byte |
| Active release lookup | Index Scan |
| Page slug lookup | Index Scan |
| Redirect lookup | Index Scan |
| Form submission lookup | Index Scan |

Giới hạn: benchmark chạy trong CLI local, stylesheet đã được inline và DB có quy mô chưa được kiểm soát. Nó không đo network, browser parse/style/layout, TTFB thực tế hay contention.

### Build output

| Artifact | Raw size | Gzip từ Vite |
|---|---:|---:|
| Builder chunk | 133,38 KB | 35,51 KB |
| App chunk | 146,08 KB | 41,90 KB |
| Shared `dist` chunk | 233,45 KB | 79,77 KB |
| CSS chính | 137,82 KB | 21,79 KB |
| CSS phụ | 14,91 KB | 3,04 KB |
| Tổng JS assets | 762.364 byte | Không cộng gzip do chunk loading phụ thuộc route |
| Tổng CSS assets | 155.091 byte | Không cộng gzip do release inline CSS không được nén trong file HTML |

`DocumentHtmlRenderer` inline toàn bộ CSS entry vào mỗi release (`DocumentHtmlRenderer.php:118`, `DocumentHtmlRenderer.php:160`). Vì vậy HTML benchmark 50 node đã là 146.686 byte trước HTTP compression.

## 7. Code quality và UX baseline

- Các domain service backend có test sâu và dùng transaction/lock có chủ đích.
- Các màn hình editor có loading/error/empty state tương đối đầy đủ; keyboard shortcut và focus style tồn tại ở nhiều control.
- Frontend test chủ yếu kiểm thử composable/helper trong Node; không có Playwright/Cypress, visual regression hay axe accessibility suite.
- Không có đo browser cho builder startup, canvas rerender, inspector latency, LCP/CLS/INP.
- Registry được fetch hai lần khi editor mount (`EditorShell.vue:233`, `ComponentLibrary.vue:91`).
- Nhiều helper CSRF/fetch được lặp lại; API contract chưa tập trung.
- `Websites/*` và `Pages/Create|Edit` tự bọc `AppLayout`, trong khi `resources/js/app.ts:20` áp default `AppLayout`; static inspection cho thấy nguy cơ layout lồng đôi, chưa được browser test xác nhận.

## 8. Security, authorization và reliability baseline

Điểm mạnh đã xác minh qua code/test:

- Tenant-scoped route kiểm tra IDOR và policy ở nhiều endpoint.
- Document validation, URL validation và HTML sanitization đều ở server.
- Upload dựa trên magic bytes, dimension và allowlist raster; SVG bị chặn.
- Publish giữ active release cũ nếu render/staging lỗi; release có manifest hash.
- `npm audit` và `composer audit` không phát hiện advisory tại thời điểm audit.

Rủi ro xác minh được:

- Viewer được phép update/delete form submission do controller dùng quyền `view` cho mutation (`FormSubmissionAdminController.php:42`, `FormSubmissionAdminController.php:65`).
- Publish frontend bỏ qua boolean thất bại của pre-publish save (`EditorShell.vue:194`).
- Bộ concurrency PostgreSQL không chạy trong CI mặc định; config riêng trỏ thẳng vào `page_builder_dev` và test có thao tác xóa bảng.
- Script backup dùng sai thư mục release và nuốt lỗi tar (`deploy/scripts/backup.sh:42`).

## 9. Test coverage và vùng thiếu test

- Backend có 299 test được discovery; 273 pass trong SQLite, 26 skip.
- Frontend có 85 test utility/composable; không có component-level interaction đầy đủ.
- CI không cấu hình coverage (`coverage: none`) và repo không có baseline line/branch coverage; không thể đưa ra tỷ lệ coverage.
- Không có automated test cho layout lồng đôi, responsive visual equivalence, keyboard focus order, screen reader live region, canvas overflow hay Core Web Vitals.
- Test PostgreSQL concurrency lịch sử được tài liệu ghi 36/36 pass, nhưng audit không chạy vì `phpunit.pgsql.xml:27` trỏ database phát triển và các test xóa dữ liệu. Không thể dùng kết quả lịch sử như bằng chứng hiện tại.

## 10. Chưa thể kết luận

- Builder startup, canvas render với 250 node, số lần rerender và inspector latency: thiếu browser profiler/harness.
- TTFB/LCP/CLS/INP public: không có dataset/page public ổn định và không có browser runner.
- Accessibility thực tế bằng screen reader và tab order: chưa có browser/assistive technology test.
- Query latency/N+1 ở dữ liệu production-scale: query plan có index nhưng dataset local chưa đại diện production.
- Responsive visual equivalence ở 375/768/1280: code cho thấy mismatch, nhưng chưa có screenshot regression để định lượng diện tích/CLS.

## 11. Tiêu chí hoàn thành Phase 0

- [x] Stack, entry point, module boundary và package manager được xác minh.
- [x] Git status và thay đổi chưa commit được ghi nhận, không bị đụng tới.
- [x] Luồng Page Builder quan trọng được map.
- [x] Lint, typecheck, test và build phù hợp đã chạy.
- [x] Lỗi baseline được tách khỏi thay đổi mới.
- [x] Chỉ số hiệu năng khả dụng đã được đo và nêu giới hạn.
- [x] Không sửa code ứng dụng, schema, dependency hay migration.
