# Phase 1 - Architecture and Code Quality Review

## 1. Kết luận kiến trúc

Codebase đang đi đúng hướng modular monolith: business rule quan trọng nằm trong domain service, schema document tập trung, controller phần lớn mỏng, và các giao thức concurrency/media/publish có test tương đối sâu. Không có bằng chứng cần rewrite.

Rủi ro chính không nằm ở việc thiếu abstraction tổng thể, mà ở bốn seam chưa khép kín:

1. Editor orchestration và UI control đang tập trung vào component rất lớn.
2. Contract responsive có hai implementation không tương đương giữa Vue và PHP renderer.
3. Contract persistence/publish chưa được biểu diễn thành invariant xuyên suốt UI.
4. Quality gate và test production-specific chưa phản ánh đúng môi trường production.

## 2. Dependency direction

### Backend

Hướng phụ thuộc hiện tại nhìn chung hợp lý:

```text
HTTP Controller/FormRequest/Policy
        -> Domain Service
        -> Model/DB/Storage

DocumentEngine <- Pages / Templates / Publishing
Media          <- Pages / Templates / Publishing
SEO            <- Pages / Publishing / Public delivery
```

Điểm cần giữ nguyên:

- `DocumentValidator` là cổng chặn cho mọi đường ghi.
- Media writer phải lấy advisory lock trên hợp reference cũ và mới.
- Publish không giữ transaction trong khi render/ghi disk.
- Revision/release là append-only và rollback không sửa lịch sử.

### Frontend

```text
Pages/Builder.vue
  -> EditorShell.vue
      -> useDocumentState
      -> useEditorHistory
      -> useAutosave
      -> ComponentLibrary / Canvas / Inspector / Modals
```

Seam này rõ về mặt data flow, nhưng `EditorShell` hiện sở hữu quá nhiều protocol: document watcher, conflict, publish, preview, registry fetch, keyboard, SEO, revision, modal và panel. Việc tách sau này nên theo protocol có hành vi độc lập, không theo mục tiêu giảm số dòng.

## 3. Findings theo mức độ

### A-01 - Publish protocol không bắt buộc save thành công

- Severity: Critical.
- Bằng chứng: `saveNow()` trả `Promise<boolean>` và trả `false` khi conflict/error (`useAutosave.ts:97`, `useAutosave.ts:165`, `useAutosave.ts:182`), nhưng `handlePublish()` chỉ `await` rồi luôn POST publish (`EditorShell.vue:194`).
- Hậu quả: người dùng có thể thấy nội dung mới trên canvas nhưng server publish draft cũ, đặc biệt khi 409 hoặc validation/network error.
- Root cause: save và publish là hai action rời, không có application-level precondition chung.
- Hướng xử lý: tạo một publish coordinator nhỏ trong frontend hoặc làm server accept expected lock/document hash; trước mắt chặn publish khi `saveNow` trả false/conflict/error.

### A-02 - Responsive contract bị phân đôi

- Severity: High.
- Bằng chứng: client resolve toàn bộ override (`resources/js/lib/responsive.ts`), renderer PHP chỉ đọc `display` (`DocumentHtmlRenderer.php:646`).
- Hậu quả: editor và public output không cùng một document semantics.
- Root cause: responsive mapping được khai báo ở registry/client nhưng renderer không có shared mapping/test theo property.
- Hướng xử lý: xác định một contract canonical cho base/tablet/mobile và thêm characterization test editor-vs-renderer cho từng prop trước khi sửa renderer.

### A-03 - Mutation quyền form submission dùng policy đọc

- Severity: Critical.
- Bằng chứng: update/delete đều gọi `Gate::authorize('view', $website)` (`FormSubmissionAdminController.php:42`, `FormSubmissionAdminController.php:65`).
- Hậu quả: Viewer có thể thay đổi/xóa dữ liệu bằng API trực tiếp.
- Root cause: chưa có policy/action riêng cho submission mutation.
- Hướng xử lý: dùng quyền update website hoặc policy submission rõ ràng; bổ sung authorization matrix cho Viewer.

### A-04 - CI không đại diện default branch và production concurrency

- Severity: High.
- Bằng chứng:
  - Repo đang ở `master`, workflow push chỉ theo `main` (`.github/workflows/tests.yml:4`).
  - `composer ci:check` hiện fail vì 3 lỗi PHPStan.
  - Workflow không có PostgreSQL service và chỉ chạy default SQLite; 26 test bị skip.
  - `phpunit.pgsql.xml:27` trỏ `page_builder_dev`, trong khi test concurrency có cleanup/xóa dữ liệu.
- Hậu quả: direct push vào `master` có thể không chạy CI; regression lock/partial index không được bảo vệ tự động.
- Hướng xử lý: sửa trigger theo default branch, thêm DB test riêng biệt dùng database disposable và chạy suite pgsql trong CI.

### A-05 - Component orchestration quá lớn

- Severity: Medium.
- Bằng chứng: `InspectorPanel.vue` 1.099 dòng, `EditorShell.vue` 609 dòng, `useDocumentState.ts` 573 dòng.
- Hậu quả: thay đổi inspector/responsive dễ gây rerender và regression chéo; khó mount/test từng interaction.
- Hướng xử lý: tách theo responsibility ổn định: publish/save protocol, keyboard command routing, inspector field groups; không tạo generic form abstraction trước khi có characterization tests.

### A-06 - API client và CSRF contract bị lặp

- Severity: Medium.
- Bằng chứng: token helper xuất hiện ở autosave, shell, revision, template, media; registry được fetch hai lần (`EditorShell.vue:233`, `ComponentLibrary.vue:91`).
- Hậu quả: error mapping/header/retry không nhất quán, thêm endpoint dễ copy lỗi.
- Hướng xử lý: một HTTP helper nhỏ cho JSON/CSRF/error normalization và truyền registry từ composition root xuống library/inspector.

### A-07 - Persisted document bỏ sanitized result

- Severity: Medium.
- Bằng chứng: validator tạo sanitized document nhưng save ghi `$document` gốc (`PagePersistenceService.php:174`); template ghi request document (`PageTemplateController.php:157`, `PageTemplateController.php:311`).
- Hậu quả: DB có thể giữ alias/default thiếu và RichText chưa sanitize; public renderer hiện sanitize lại nên chưa phải XSS public, nhưng các consumer mới có thể dùng nhầm dữ liệu raw.
- Quyết định cần xác nhận: chuẩn hóa khi ghi sẽ thay đổi representation/hash hiện có. Không nên sửa trước khi định nghĩa migration/compatibility.

### A-08 - Page creation không bao transaction toàn bộ

- Severity: Medium.
- Bằng chứng: page được tạo trước, sau đó instantiate/save/revision chạy tách rời trong `PageController::store`.
- Hậu quả: template hỏng hoặc lỗi revision có thể để page tồn tại ở trạng thái một phần.
- Hướng xử lý: characterization test lỗi instantiate/revision, sau đó bọc create + initial document + revision trong transaction phù hợp.

### A-09 - Layout ownership không nhất quán

- Severity: Medium.
- Bằng chứng: `resources/js/app.ts:20` gán default `AppLayout`; năm page dưới `Websites/*` và `Pages/Create|Edit` lại tự bọc `AppLayout`.
- Hậu quả: nguy cơ sidebar/header/layout lồng đôi, ảnh hưởng kích thước canvas và navigation.
- Hướng xử lý: xác định một owner duy nhất cho layout và thêm component/browser test route render.

### A-10 - Dead/unused paths có bằng chứng

- Severity: Low.
- `useEditorState.searchQuery/selectedCategory/resetFilters`, `canPerformNodeAction`, `clearHistory`, emit `sharePreview`, `ConflictModal.serverUpdatedAt` và `MediaLibraryModal mode` chưa được dùng theo static search.
- Không nên xóa hàng loạt. Chỉ xóa khi từng item có test/usage search và không nằm trong roadmap gần.

## 4. Data access và query review

- Các list chính dùng eager loading (`WebsiteController::show`, release, revision, submission), không thấy N+1 rõ ràng trong đường đọc đã audit.
- Benchmark xác nhận bốn hot query dùng index scan trên PostgreSQL local.
- `WebsiteController::show` tải toàn bộ pages và memberships bằng `get()`; chưa có pagination. Đây là rủi ro scale khi website có nhiều page/member, nhưng chưa có dataset chứng minh latency cao.
- Release/revision list cũng dùng `get()` không pagination; UI release chưa tồn tại, revision modal có thể tăng tải theo lịch sử dài.
- Media usage scan cố ý quét page/revision/template để fail-closed; đây là correctness trade-off, cần đo trên dataset lớn trước khi thêm index/cache.

## 5. Error handling và observability

Điểm tốt:

- Publishing exception được map thành mã lỗi cụ thể.
- Media scan fail-closed.
- Autosave có status state machine và retry hữu hạn.
- Health check và benchmark command tồn tại.

Khoảng trống:

- Registry fetch trong `EditorShell` nuốt lỗi hoàn toàn (`EditorShell.vue:238`), khiến inspector có thể thiếu metadata mà không có recovery rõ.
- Frontend error handling dựa trên chuỗi `message`, chưa có typed error contract dùng chung.
- Không có correlation/request ID hoặc structured event cho autosave conflict, publish duration và staging failure trong audit hiện tại.
- Không có client telemetry cho save latency, failed save rate hay editor startup.

## 6. Contract và type consistency

- PHP/TS có duplicate definitions cho component type, defaults, responsive allowlist và limits; một số có compatibility test nhưng renderer responsive vẫn lệch.
- API trả cả `error` và `error_code` tùy service. Frontend phải đọc `message` thay vì switch ổn định theo code.
- Không có machine-readable OpenAPI/Postman artifact; thay đổi API hiện phụ thuộc route/test/prose review.
- `PageStatus`, `WebsiteRole`, `MediaAssetStatus`, `PageReleaseStatus` được enum hóa tốt ở backend; frontend vẫn có một số union mở bằng `| string`.

## 7. Characterization tests cần có trước refactor

1. Publish không gửi request khi pre-save trả false/409/422/network error.
2. Responsive property matrix cho 12 component ở desktop/tablet/mobile, so sánh editor resolve và HTML class output.
3. Page route chỉ render một AppLayout.
4. Restore revision reset/branch history theo quyết định UX đã chọn.
5. Create page rollback toàn bộ nếu instantiate hoặc revision insert lỗi.
6. Shared HTTP helper giữ nguyên CSRF header, JSON parsing và error behavior của autosave/media/template/revision.

## 8. Tiêu chí hoàn thành review kiến trúc

- [x] Dependency direction và module boundary được map.
- [x] Coupling, duplication, large modules, type/contract và error handling được audit.
- [x] Query path và N+1 được kiểm tra bằng code + benchmark hiện có.
- [x] Các refactor đề xuất đều gắn với lỗi/rủi ro cụ thể.
- [x] Không thay đổi public API/schema/permission trong phase audit.
