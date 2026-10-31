# Phase 1 - UX/UI and Responsive Review

## 1. Tổng quan

Editor đã có nền tảng UX tốt hơn một MVP thô: bố cục library/canvas/inspector, search/category, selection/hover, toolbar, save state, viewport switcher, conflict modal, revision, media picker và SEO preview đều tồn tại. Vấn đề lớn nhất là độ tin cậy của mental model: những gì người dùng thấy trong editor chưa đảm bảo là thứ được publish.

Audit này dựa trên source, unit/feature test và build. Không có browser visual run, screenshot comparison hay screen-reader session; các nhận định hình học/visual chưa được đo trực tiếp được ghi là cần xác minh.

## 2. Page Builder workflow matrix

| Luồng | Trạng thái hiện tại | Đánh giá |
|---|---|---|
| Website/page CRUD | Có, có policy và IDOR | Cơ bản đầy đủ |
| Draft/published/archived | Có metadata state | Chưa có unpublish workflow rõ |
| Owner/Editor/Viewer | Có UI read-only và backend policy ở phần lớn endpoint | Form submission mutation vi phạm Viewer |
| System/custom template | Có create/instantiate API và save-as-template | Chưa có UI quản lý list/edit/delete template |
| 12 component | Registry, renderer Vue/PHP và test tồn tại | Responsive output không tương đương |
| Add/select/duplicate/delete/move | Có click insert, DnD từ library, toolbar node, move up/down | Chưa reorder/reparent node có sẵn bằng DnD |
| Nested tree | Có validation depth/node/children/cycle | Không có Layers panel; điều hướng cây sâu khó |
| Undo/redo | 30 snapshot, keyboard | Restore revision không reset history |
| Keyboard | Ctrl/Cmd+B/S/Z/Shift+Z/Y, Esc | Esc chạy cả trong input; focus flow chưa test |
| Viewports | 1280/768/375 | Canvas width đúng preset nhưng breakpoint CSS phụ thuộc browser window |
| Inspector | Dynamic theo registry, content/style/responsive | 1.099 dòng; thiếu required/client maxItems validation |
| Media | Browse/upload/search/filter/select/delete protection | Chưa có trang media riêng |
| Autosave/conflict | Dirty/saving/saved/error/conflict, retry, overwrite/reload | Publish có thể bỏ qua save failure |
| Revision | List, restore | Không có compare/diff; restore history semantics chưa rõ |
| SEO | Google/Social preview | Thiếu UI cho một số field backend hỗ trợ |
| Preview/publish/release | Preview, signed endpoint, publish success | Chưa có share-link button và release history/rollback UI |

## 3. Findings UX có impact cao

### UX-01 - Editor không phải WYSIWYG đối với responsive

- Severity: Critical/High.
- Editor áp `styles.tablet/mobile` cho padding, align, count, gap, size, variant, background... nhưng public renderer chỉ áp `display`.
- Người dùng có thể dành thời gian chỉnh mobile/tablet, preview canvas thấy đúng, rồi publish ra layout khác.
- Acceptance target: cùng một fixture phải cho kết quả property tương đương ở 375/768/1280 giữa canvas và public output.

### UX-02 - Viewport frame không tạo breakpoint context độc lập

- Severity: High.
- Canvas thu hẹp về 375/768 nhưng các class `sm:/md:/lg:` của component vẫn phản ứng theo window thật (`PENTAGON_SYSTEM_REFERENCE.md:571`).
- Trên màn hình desktop, frame mobile vẫn có thể render class desktop/tablet bên trong.
- Cần chọn một giải pháp đã thiết kế: container queries, iframe preview có viewport thật, hoặc renderer-specific class resolution. Không chỉ đổi width.

### UX-03 - Publish có thể xác nhận thành công cho nội dung cũ

- Severity: Critical.
- Nếu pre-save lỗi/conflict, publish vẫn chạy. Feedback hiện tại không nói rõ release dùng version nào.
- Nút Publish cần bị chặn khi `saving`, `error`, `conflict`, hoặc save mới nhất chưa được server xác nhận.
- Success modal nên hiển thị release/revision được publish để tăng khả năng truy vết.

### UX-04 - Image lỗi được phát hiện quá muộn

- Severity: High.
- `Image.src` rỗng qua validator nhưng production renderer fail-closed; preview chỉ hiện placeholder.
- Người dùng chỉ biết khi publish trả `render_failed`.
- Cần inline validation trong inspector, badge/lỗi trên node và pre-publish summary dẫn trực tiếp tới node cần sửa.

### UX-05 - Layout admin có nguy cơ lồng đôi

- Severity: High nếu xác nhận runtime.
- Inertia gán default `AppLayout`, trong khi năm page tự bọc cùng layout.
- Có thể tạo hai sidebar/header, giảm canvas/viewport hữu dụng và gây overflow.
- Cần browser verification trước khi sửa; static evidence đủ để đưa vào P1.

### UX-06 - Cây sâu thiếu navigation model

- Severity: Medium.
- Node toolbar chỉ move up/down; `moveNode` hỗ trợ reparent nhưng không có UI.
- Không có Layers panel, breadcrumb parent hay tree keyboard navigation.
- Trước khi thêm DnD reparent cần user story, insertion feedback, cycle/depth/child-limit validation và test keyboard fallback.

### UX-07 - Restore revision có thể tạo undo bất ngờ

- Severity: Medium.
- Restore set document mới nhưng không clear history (`EditorShell.vue:159`; `useEditorHistory.ts:111` tồn tại `clearHistory`).
- Người dùng có thể Undo và quay lại state trước restore mà không hiểu đây là local history khác với revision server.
- Cần quyết định product: clear history, hoặc tạo explicit history boundary có label.

### UX-08 - Keyboard/focus chưa hoàn chỉnh

- `Esc` clear selection kể cả khi đang nhập Inspector (`EditorShell.vue:257`).
- Không có automated focus-trap/focus-return test cho modal tự xây như Conflict/Publish/Revisions.
- Autosave/publish state hiển thị trực quan nhưng chưa thấy live region nhất quán cho screen reader.
- Node canvas không có keyboard tree navigation hoặc reorder equivalent.

### UX-09 - Error và recovery không đồng nhất

- Autosave có retry, conflict modal và banner.
- Registry lỗi trong shell bị nuốt; inspector có thể rỗng mà không nêu nguyên nhân.
- Preview banner hiện bị chèn trước DOCTYPE vì renderer tạo `<body class=...>` nhưng controller tìm literal `<body>` (`PagePreviewController.php:125`).
- Một số action dùng `window.confirm`, một số dùng modal; feedback pattern chưa thống nhất.

### UX-10 - Page management cần scale behavior

- `WebsiteController::show` tải toàn bộ pages/members; UI bảng chưa có pagination/search theo bằng chứng hiện tại.
- Với website lớn, thời gian tải và thao tác sẽ giảm; chưa có dataset để định lượng.
- Không nên thêm virtualization trước khi đo, nhưng pagination/filter server-side là candidate khi có ngưỡng thực.

## 4. Responsive inheritance audit

Contract được mô tả:

```text
desktop = props
tablet  = tablet override ?? desktop
mobile  = mobile override ?? tablet override ?? desktop
```

Hiện trạng:

- Client helper thực hiện cascade trên.
- `display` ở PHP dùng logic riêng, không dùng cascade chuẩn.
- `tablet=hidden`, mobile không override: editor ẩn mobile; public lại hiện mobile (`DocumentHtmlRenderer.php:666`).
- Override khác `display` bị bỏ khi render public.
- Desktop không có override `display`; chỉ base component behavior.

Nguồn sự thật đề xuất cho Phase 3:

1. Registry khai báo prop nào responsive và mapping token-to-class cho từng viewport.
2. Một resolver canonical có fixture chung được kiểm tra ở TS và PHP.
3. Public renderer không tự diễn giải khác với editor.
4. Visual test chụp editor/public cùng fixture tại 375/768/1280.

## 5. Accessibility review

Điểm tốt từ source:

- Viewport switcher dùng `radiogroup/radio` và `aria-checked`.
- Nhiều icon button có `aria-label`.
- Media card/dropzone có keyboard/status/error semantics.
- Focus-visible style được dùng rộng rãi.
- Public FAQ dùng semantic `details/summary`.

Khoảng trống/rủi ro:

- Chưa có axe/browser test và chưa có screen-reader test.
- Modal tự xây cần xác minh focus trap, initial focus, Escape và focus return.
- Save/publish/conflict feedback cần `aria-live` phù hợp.
- Canvas node selection/reorder chưa có semantic tree và keyboard navigation.
- Màu selected/hover/hidden cần contrast measurement thực tế, chưa thể kết luận bằng source.
- Motion transition chưa thấy rule reduced-motion riêng cho editor.

## 6. Form validation và feedback

- Backend validation mạnh và có mã lỗi chi tiết.
- Inspector dùng control theo schema, enum và maxlength, nhưng không kiểm tra required, URL safety đầy đủ, payload, root validity hay FAQ max 20 ở client.
- Client-side validation nên phục vụ hướng dẫn sớm, không thay server validation.
- Error từ `DocumentValidator` nên map `path` về node/field và cho phép click để select/focus lỗi.

## 7. Đề xuất UX theo mục tiêu, chưa triển khai

| Mục tiêu | Thay đổi candidate | Bằng chứng xác minh |
|---|---|---|
| Không publish nhầm draft cũ | Publish state machine phụ thuộc save acknowledgment | Test 409/422/network; không có publish request |
| Editor/public đồng nhất | Shared responsive contract + visual fixtures | Screenshot diff 375/768/1280 |
| Sửa lỗi publish nhanh | Preflight issue list, select/focus node lỗi | E2E Image src rỗng |
| Giảm quá tải inspector | Group Content/Layout/Style/Responsive/Advanced, giữ state | Component test switching viewport/node |
| Điều hướng tree sâu | Layers panel trước, DnD reparent sau | Keyboard + invalid-tree tests |
| Feedback accessible | Live region cho save/publish/error | Axe + screen-reader smoke |
| Layout ổn định | Một AppLayout owner; panel responsive/collapse | Browser checks desktop/mobile |

## 8. Tiêu chí đo UX cho phase triển khai

- Tỷ lệ task success cho tạo page, thêm component, chỉnh responsive, preview, publish.
- Số click/keystroke cho add -> edit -> publish và sửa một lỗi preflight.
- Thời gian từ click Publish đến feedback thành công/thất bại; tách save, render, activation.
- Số trường hợp publish bị chặn đúng do dirty/error/conflict.
- Số mismatch visual editor/public tại 375/768/1280.
- Keyboard-only completion cho select/edit/move/save/publish.
- Axe critical/serious violation count và focus-return pass rate.
- Không dùng điểm UX tổng hợp duy nhất; ghi từng task và môi trường đo.

## 9. Tiêu chí hoàn thành review UX

- [x] Editor, published output, breakpoint, inheritance và state UI được audit.
- [x] Keyboard, accessibility, validation, empty/error/conflict/success state được xem xét.
- [x] Mọi đề xuất đều gắn với issue hoặc mục tiêu UX cụ thể.
- [x] Không thay thiết kế theo cảm tính và không triển khai DnD/Layers trong phase audit.
