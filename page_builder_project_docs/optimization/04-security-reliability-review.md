# Phase 1 - Security and Reliability Review

## 1. Tổng quan

Codebase có nhiều control production-grade: policy/IDOR, server-side document validation, HTML sanitizer, safe URL allowlist, upload magic-byte validation, media reference safety, optimistic lock, advisory lock, immutable revision/release, manifest hash, atomic release activation, security headers và rate limit form.

Hai blocker cần ưu tiên trước traffic thật là mutation quyền Viewer ở form submission và publish tiếp tục sau save thất bại. Các rủi ro còn lại chủ yếu thuộc responsive correctness, CI production parity, backup và cleanup.

### Cập nhật remediation Phase 2A - 2026-10-24

| Finding | Trạng thái | Thay đổi và bằng chứng |
|---|---|---|
| S-01 | Đã sửa | Mutation submission dùng policy Owner/Editor; Viewer 403, cross-tenant 404, Owner/Editor pass. |
| S-02 | Đã sửa | `parse_url()` trả `false` bị từ chối fail-closed; sanitizer regression test pass. |
| S-04 | Đã sửa | Publish dừng khi draft save false/conflict/error và khóa double-submit trong cả giai đoạn save. |
| S-05 | Đã sửa cấu hình và đã chạy local disposable | CI có PostgreSQL 18 service `pentagon_test`; bootstrap từ chối tên DB khác; suite pass 36/36 trên container tạm. GitHub-hosted run chờ lần push/PR tiếp theo. |
| S-10 | Đã sửa | Backup lấy `private/releases`, lỗi archive trả non-zero; fixture kiểm tra contents, checksum và extract restore pass. |

Các phần Phase 2B+ trong báo cáo này vẫn là finding mở; Phase 2A không thay đổi responsive semantics, cleanup lifecycle hay publish/rollback concurrency contract.

## 2. Authorization và tenant isolation

### Điểm đạt

- Website/page/media/template/revision/release kiểm tra membership/policy ở backend.
- Nhiều endpoint kiểm tra resource con thuộc website/page và trả 404 cho cross-tenant.
- Viewer bị chặn save content, publish, delete page và manage member theo test authorization matrix.
- Signed preview có signature/expiration và private preview cần auth.

### S-01 - Viewer có thể update/delete form submission

- Severity: Critical.
- Bằng chứng: mutation dùng `Gate::authorize('view', $website)` tại `FormSubmissionAdminController.php:42` và `FormSubmissionAdminController.php:65`.
- IDOR website vẫn được chặn, nhưng role Viewer trong đúng website có quyền ghi/xóa.
- Thiếu test trong authorization matrix cho hai endpoint này.
- Remediation: policy/action mutation riêng, Owner/Editor only; test Viewer 403 và cross-tenant 404.

## 3. Validation, sanitization và injection

### Điểm đạt

- Document type/component/prop/URL/tree limits được validate server-side.
- RichText và FAQ HTML dùng Symfony sanitizer khi render public.
- Text/attribute được escape theo context.
- URL scheme chỉ cho http/https/mailto/tel, relative và anchor; protocol-relative/control char bị chặn.
- Client sanitizer fail-closed và loại script/event/id nguy hiểm.
- Public renderer fail-closed ngoài preview.

### S-02 - `parse_url` false path chưa được xử lý đúng kiểu

- Severity: Medium.
- PHPStan báo `HtmlSanitizer.php:64`: `strtolower` nhận `string|false`.
- Đây có thể chỉ là type defect, nhưng nằm trong security-sensitive URL validation; cần test payload khiến `parse_url` trả false và xử lý fail-closed.

### S-03 - Raw document được lưu thay vì sanitized document

- Severity: Medium.
- Save/template validate nhưng persist input raw.
- Public output hiện vẫn sanitize/escape, nên audit không xác nhận XSS public.
- Rủi ro là consumer mới hoặc admin UI khác đọc raw HTML và render không qua sanitizer.
- Thay đổi representation có thể ảnh hưởng hash/revision, nên cần ADR/compatibility plan trước khi chuẩn hóa khi ghi.

## 4. Media integrity

### Điểm đạt

- Upload kiểm tra size, magic bytes, MIME/header match, dimensions và raster allowlist.
- Filename server-generated và path containment được kiểm tra khi prune.
- Delete dùng advisory lock, scan fail-closed và grace period.
- Save/template/publish lock hợp reference cũ/mới và kiểm tra asset active/ownership.
- Revision/template bảo vệ media khỏi bị purge trong tương lai.

### Rủi ro

- External image URL được cho phép; availability/privacy/CSP của host ngoài không được bảo đảm.
- Rollback release Case B recompile revision mà không chạy lại media availability preflight (`PagePublishingService.php:227`). DB row có thể còn do revision reference, nhưng file vật lý có thể thiếu/corrupt; rollback có thể activate HTML với ảnh hỏng.
- Xóa website/page cascade DB không dọn file media/release; tạo orphan storage và tăng backup footprint.

## 5. Concurrency và lost update

### Điểm đạt

- Draft dùng `lock_version` và trả 409.
- Media protocol dùng advisory lock có thứ tự.
- Publish Phase 1 dùng per-page lock; active release có partial unique index.
- Active release cũ không bị supersede trước khi candidate sealed.

### S-04 - Publish từ UI không tôn trọng conflict/save failure

- Severity: Critical.
- Frontend có thể publish server draft cũ sau khi save trả false.
- Backend publish đúng dữ liệu đang lưu, nên đây là integration invariant bị thiếu, không phải transaction bug trong service.
- Fix phải có regression test ở frontend và ideally expected lock/hash ở API để chống client khác bỏ qua precondition.

### S-05 - PostgreSQL concurrency không được bảo vệ trong CI

- Severity: High.
- Default test dùng SQLite; 26 test skip.
- Workflow không có PostgreSQL service/suite.
- Config pgsql chuyên biệt dùng `page_builder_dev`, không phải disposable test DB.
- Đây là gap giữa production mechanism và verification, đặc biệt advisory lock/partial unique.

### S-06 - Rollback release không serialize cùng publish

- Severity: Medium.
- Publish lock chỉ ở preflight; rollback không lấy PUB lock. Phase activation dựa row lock/unique index và có thể last-writer-wins.
- Chưa có acceptance rule rõ khi publish và rollback chạy đồng thời. Cần product decision: serialize toàn operation hay cho phép action hoàn thành cuối thắng.

## 6. Publish, preview và release reliability

### Điểm đạt

- Candidate release tách khỏi active.
- Staging path được validate; manifest/hash được verify.
- Public route có ETag và 304.
- Chaos tests bảo vệ active release khi failure.

### S-07 - Empty Image fail ở publish thay vì validation/preflight

- Severity: High UX/reliability.
- `src` required nhưng empty string được safe URL validator chấp nhận; production renderer ném exception.
- Không làm mất active release, nhưng publish thất bại muộn và khó sửa trên page lớn.
- Cần validator rule/semantic preflight và node-path error; giữ preview placeholder.

### S-08 - Preview response tạo markup sai vị trí

- Severity: Medium.
- Controller tìm `<body>`, renderer tạo `<body class="...">`; banner bị prepend trước `<!DOCTYPE html>` (`PagePreviewController.php:125`).
- Có thể kích hoạt quirks/parser recovery khác nhau và làm preview không tương đương public.

### S-09 - Existing release trước fix CSS có thể vẫn lỗi

- Severity: Operational.
- Commit hiện tại sửa stylesheet bằng inline CSS, nhưng release bất biến đã publish trước fix không tự đổi.
- Cần inventory/re-publish có kiểm soát; audit không thay đổi release.

## 7. Backup, restore và data lifecycle

### S-10 - Backup bỏ sót release và nuốt lỗi

- Severity: High operational.
- `deploy/scripts/backup.sh:42` archive `page_releases`, trong khi storage thật là `private/releases`; `|| true` làm command vẫn báo thành công.
- Release có thể rebuild từ revision trong Case B, nhưng RTO tăng và backup report không trung thực.
- Verify: chạy backup trên fixture và assert archive chứa media + private/releases + checksum; tar failure phải fail command.

### Data cleanup

- Media có pruner/watchdog.
- Superseded release có pruner; pending có reconcile.
- Không thấy cleanup orphan storage khi website/page bị xóa.
- Failed sealed directory và cascade lifecycle cần inventory command trước khi thêm delete behavior.

## 8. Rate limits, sensitive data và headers

- Public form route: 5 request/phút, honeypot, 64 KB payload.
- Security headers middleware tồn tại; production smoke tests bao phủ một phần.
- Logs chưa được audit bằng runtime sample; không thấy chủ đích log document/token trong các path chính đã đọc.
- Signed preview URL là bearer capability trong 60 phút; cần tránh log/query analytics lộ URL nếu chia sẻ ngoài hệ thống.
- Public form data là dữ liệu cá nhân tiềm năng; retention/export/delete policy chưa thấy trong tài liệu/code audit.

## 9. Dependency và supply chain

- `npm audit --omit=dev`: 0 vulnerability tại thời điểm audit.
- `composer audit --locked`: 0 advisory và 0 abandoned package.
- GitHub Actions được pin SHA, là điểm tốt.
- CI `coverage: none`; không phải security flaw trực tiếp nhưng giảm evidence.
- Workflow push branch mismatch (`main` vs `master`) có thể bỏ qua direct push.

## 10. Security regression suite cần bổ sung

1. Viewer PATCH/DELETE submission -> 403; Owner/Editor allowed; cross-tenant -> 404.
2. Invalid URL khiến `parse_url` fail -> reject/fail-closed.
3. Publish request không được gửi khi save 409/422/500/network.
4. Server publish với expected lock/hash stale -> 409 nếu contract được thêm.
5. Empty Image -> validator/preflight error có node path, active release giữ nguyên.
6. Concurrent publish/rollback semantics trên PostgreSQL disposable DB.
7. Rollback Case B với media row/file thiếu -> không activate release hỏng.
8. Backup archive verification và restore rehearsal đúng path.
9. Signed preview expiration/tampering và no-store/noindex tiếp tục pass.
10. Sanitization corpus cho RichText/FAQ/URL ở save, preview và publish.

## 11. Tiêu chí hoàn thành review security/reliability

- [x] Authorization backend, tenant isolation, validation, XSS, upload và media reference được audit.
- [x] Preview, publish atomicity, release immutability và concurrency được đối chiếu.
- [x] Rate limit, backup, cleanup và CI parity được xem xét.
- [x] Không nới sanitizer, permission, lock hay test để đạt kết quả.
