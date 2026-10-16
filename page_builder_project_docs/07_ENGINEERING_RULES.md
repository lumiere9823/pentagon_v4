# 07 — Engineering Rules for Developers & AI Agents

## 1. Trước khi sửa code
1. Đọc README, `composer.json`, `package.json`, cấu trúc thư mục, routes, migrations, tests và CI.
2. Xác định framework/runtime versions từ repo thực tế; không tự giả định.
3. Chạy baseline tests/lint/typecheck nếu môi trường cho phép.
4. Xác định các file liên quan và dependency hiện hữu trước khi thêm package.
5. Với task lớn, viết plan và acceptance criteria trước khi triển khai.

## 2. Kiến trúc
- Ưu tiên modular monolith, module boundaries rõ.
- Controller mỏng; domain/application logic nằm trong lớp phù hợp với convention của repo.
- Không tạo abstraction không có use case cụ thể.
- Không thêm package/service mới nếu native framework đủ dùng.
- Tách public static rendering khỏi dynamic/user-specific endpoints.

## 3. Document & component safety
- Document JSON là data, không phải executable code.
- Chỉ chấp nhận component types trong registry.
- Validate document ở server; client validation chỉ phục vụ UX.
- Giới hạn payload size, nesting depth, node count, text length và URL schemes.
- Sanitize rich text; không cho phép inline event handlers hoặc arbitrary scripts.
- Version hóa schema và có migration/tests cho version cũ.

## 4. Authorization & validation
- Mọi endpoint có resource scope phải authorize ở server.
- Không tin `website_id`, `page_id`, `user_id`, role hoặc permission từ client.
- Enforce uniqueness/foreign keys ở DB khi phù hợp.
- Có test cho forbidden/unauthorized và IDOR scenarios.

## 5. Draft, revision, publish
- Báo Saved chỉ khi server xác nhận.
- Không âm thầm ghi đè draft stale; dùng optimistic locking.
- Revision lịch sử là immutable.
- Rollback tạo revision mới.
- Publish phải idempotent, log được trạng thái và giữ nguyên release hiện tại nếu build/switch thất bại.
- Không xóa assets/release còn được published revision tham chiếu.

## 6. Performance / low-resource server
- Đo trước khi tối ưu hoặc tăng worker.
- Không thêm Redis, SSR server, Octane, Elasticsearch hay service nền nếu chưa chứng minh nhu cầu.
- Tránh N+1, query không index, render toàn canvas khi không cần.
- Asset tĩnh có hash và cache policy đúng.
- Ghi rõ memory/latency evidence cho quyết định tuning.

## 7. Testing
- Unit tests cho schema/commands/domain rules.
- Feature tests cho authorization, persistence, revision và publish.
- Browser/E2E tests cho luồng editor quan trọng.
- Security tests cho XSS, unauthorized access, upload validation và preview token.
- Test failure path và retry, không chỉ happy path.

## 8. Git & delivery
- Không sửa file ngoài phạm vi nếu không giải thích lý do.
- Không xóa/sửa migration đã áp dụng trong môi trường dùng chung để thay thế bằng migration mới.
- Không commit secrets, `.env`, credential hoặc dump dữ liệu nhạy cảm.
- Kết thúc task phải báo: thay đổi chính, files, tests đã chạy, kết quả, giới hạn/rủi ro còn lại.
- Nếu chưa chạy được test, nói rõ lý do; không khẳng định test pass.

## 9. Completion checklist
- [ ] Acceptance criteria được đáp ứng.
- [ ] Tests phù hợp đã chạy.
- [ ] Authorization/validation server-side.
- [ ] Error states và failure paths được xử lý.
- [ ] Không có secret hoặc log nhạy cảm.
- [ ] Docs/status cập nhật.
- [ ] Có hướng dẫn test thủ công nếu cần.
