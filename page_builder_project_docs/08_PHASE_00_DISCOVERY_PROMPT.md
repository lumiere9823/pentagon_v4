# 08 — Prompt: Khảo sát repository và lập kế hoạch Phase 00

Sao chép prompt này vào coding agent đang mở đúng repository. Prompt yêu cầu agent khảo sát và lập plan, chưa triển khai feature.

---

Bạn là Senior Software Architect + Business Analyst + Laravel/Vue Technical Lead. Nhiệm vụ hiện tại là **khảo sát repository và lập kế hoạch Phase 00 cho một Page Builder Admin**, chưa code feature và chưa tự ý thay đổi kiến trúc.

## Bối cảnh sản phẩm
Mục tiêu là xây dựng Page Builder Admin với:
- Laravel + Vue 3 + TypeScript + Inertia + Vite là stack dự kiến, nhưng phải xác minh stack thật trong repo.
- PostgreSQL là database dự kiến.
- Nginx + PHP-FPM.
- Server mục tiêu có thể chỉ 2GB RAM; phải xác minh DB chạy cùng host hay riêng.
- Public pages ưu tiên static HTML khi publish; dynamic features dùng Laravel/API riêng.
- Không mặc định thêm Redis, Octane, SSR server, Elasticsearch, Kubernetes hay microservices.
- MVP: login/permission, website/page management, component palette, canvas drag-drop, inspector, undo/redo, responsive preview, draft autosave, preview, publish, revision history/rollback, SEO cơ bản.

Component ban đầu dự kiến: Section, Container, Heading, Rich Text, Image, Button, Hero, Columns, Card Grid, FAQ, Spacer, Divider.

## Nguyên tắc bắt buộc
1. Bắt đầu bằng kiểm tra repository thực tế: git status/branch, README, dependency manifests, framework versions, routes, migrations/models, auth, frontend structure, tests, CI, deployment files và existing conventions.
2. Không giả định code chưa có; tìm và phân loại phần đã có, một phần có, chưa có, hoặc không rõ.
3. Không sửa code, không tạo migration, không cài package, không đổi dependency, không chạy lệnh phá hủy.
4. Không đọc hoặc in secrets. Chỉ xác minh tên biến môi trường; không tiết lộ giá trị nhạy cảm.
5. Không coi 2GB RAM là đủ nếu chưa tính OS, Nginx, PHP-FPM, PostgreSQL và process nền.
6. Không chốt số worker/DB settings như benchmark. Ghi rõ cần đo peak RSS và workload.
7. Nếu thiếu thông tin, ghi assumption và câu hỏi cần xác nhận; không dừng toàn bộ plan chỉ vì thiếu thông tin.
8. Tách rõ facts từ repo, assumptions, recommendations và open questions.

## Công việc cần làm
### A. Repository discovery
- Tóm tắt stack thực tế và phiên bản.
- Vẽ cấu trúc repo liên quan.
- Tóm tắt auth, authorization, frontend entry points, DB conventions, testing, CI và deploy.
- Chạy baseline checks an toàn nếu hợp lý; ghi chính xác command và kết quả.
- Liệt kê những module/page builder tương tự đã tồn tại.
- Nêu rủi ro khi thêm tính năng vào code hiện hữu.

### B. Requirements analysis
- Đối chiếu yêu cầu MVP với repo.
- Lập bảng: requirement, trạng thái (existing/partial/missing/unknown), evidence path, gap, risk.
- Tách MVP khỏi out-of-scope.
- Liệt kê câu hỏi nghiệp vụ quan trọng, ưu tiên câu hỏi blocking.

### C. Architecture review
Đánh giá các quyết định đề xuất:
- Modular monolith.
- PostgreSQL + JSONB document schema.
- Allowlisted component registry.
- Immutable revisions.
- Optimistic locking cho draft.
- Static-first publishing.
- Temporary release + validation + atomic switch.
- Dynamic content qua endpoint riêng.
- Không thêm Redis/Octane/SSR nếu chưa có bằng chứng.

Với từng quyết định, ghi: fit với repo, ưu điểm, trade-off, evidence cần kiểm tra, recommendation (accept/revise/defer).

### D. Data model review
Đánh giá nhu cầu cho:
- websites
- website_memberships
- pages
- page_revisions
- page_templates
- media_assets
- publish_events
- audit_logs

Không tạo migration. Xác định keys, unique constraints, FK/delete policy, indexes, slug policy, revision immutability và publish consistency.

### E. Phase 00 plan
Lập danh sách task nhỏ theo thứ tự; mỗi task phải có:
- ID
- Mục tiêu
- Dependencies
- Files/areas dự kiến kiểm tra hoặc chỉnh sửa
- Steps
- Acceptance criteria kiểm chứng được
- Test/evidence
- Risk/rollback
- Estimate tương đối (S/M/L), không đưa ra độ chính xác giả

### F. Phase 01 readiness
Đưa ra checklist để biết khi nào có thể bắt đầu Phase 01 Foundation & CI. Nêu các ADR phải chốt trước, các câu hỏi có thể defer và các blocker.

## Output bắt buộc
Tạo báo cáo có các mục:
1. Executive summary
2. Repository facts and evidence
3. Existing capability matrix
4. Requirements gaps
5. Architecture review and proposed ADR status
6. Data model review
7. Security/performance/deployment risks
8. Blocking questions
9. Phase 00 task plan
10. Phase 01 readiness checklist
11. Commands executed and results
12. Explicit statement confirming whether any files/code were changed

Nếu môi trường cho phép tạo docs, đề xuất vị trí tài liệu nhưng **không ghi file** nếu chưa được người dùng cho phép. Kết luận bằng recommendation rõ ràng: Proceed / Proceed with conditions / Blocked, kèm lý do.

---

## Prompt tiếp theo sau khi review
Chỉ sau khi người dùng review kết quả và chốt các ADR, mới yêu cầu agent triển khai Phase 01. Mỗi phase tiếp theo phải được chia thành task nhỏ và qua acceptance criteria trước khi chuyển phase.
