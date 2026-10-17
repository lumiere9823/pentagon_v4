# Known Issues & Open Questions

Chỉ ghi issue đã quan sát được hoặc câu hỏi chưa giải quyết; không ghi suy đoán như lỗi đã xác nhận.

| ID | Type | Description | Impact | Workaround | Owner | Status |
|---|---|---|---|---|---|---|
| KI-001 | Open question | Xác minh PostgreSQL chạy cùng host hay riêng | Ảnh hưởng RAM budget | Chưa có | | OPEN |
| KI-002 | Open question | Xác minh hosting có hỗ trợ atomic release switch | Ảnh hưởng publish safety | Chưa có | | OPEN |
| KI-003 | Open question | Xác định các dynamic components thuộc MVP | Ảnh hưởng API/cache/security | Chưa có | | OPEN |
| KI-004 | Open question | Xác định multi-tenant/custom-domain requirement | Ảnh hưởng data model/routing | Chưa có | | OPEN |
| KI-005 | Phase boundary | Visual Drag-and-Drop & Live Canvas Node Rendering chưa có trong Phase 04 | Editor Canvas hiển thị placeholder kiến trúc | Triển khai hoàn tất trong Phase 05 | Tech Lead | RESOLVED |
| KI-006 | Phase boundary | Document Persistence, Autosave & Page Revisions chưa có trong Phase 05 | State chỉnh sửa nằm trên client reactive memory | Triển khai trong Phase 07 (Persistence & Revisions); Responsive styles/presets trong Phase 06 | Tech Lead | DOCUMENTED |
| KI-007 | Confirmed defect | InspectorPanel emits string values for integer enums (`Heading.level`, `Columns.count`, `CardGrid.columns`) | Server `DocumentValidator` rejects with `invalid_type` | Parse as integer before emitting `updateProp` in Phase 06 Sprint 0 | Tech Lead | OPEN |
| KI-008 | Confirmed defect | InspectorPanel checks hardcoded `selectedNode.id !== 'root'` instead of root check | Root node (`root_section_default`) shows Delete/Duplicate buttons; clicking them triggers error | Replace with `!isRoot` or `id !== document.root.id` in Phase 06 Sprint 0 | Tech Lead | OPEN |
| KI-009 | Confirmed defect | DropZone `insertIndex` ignored in `EditorShell.vue`, always appending to container end | Dropping into top or middle drop zone appends at bottom | Forward `payload.index` to `insertNode` in Phase 06 Sprint 0 | Tech Lead | OPEN |
| KI-010 | Security risk | Client `domSanitizer.ts` allows `id` attributes and uses bypassable regex fallback in non-browser environments | Potential DOM clobbering in active editor; SSR regex bypass | Disallow `id` attribute and remove regex fallback in Phase 06 Sprint 0 | Security Reviewer | OPEN |
| KI-011 | Confirmed defect | Deleting container does not reset `selectedNodeId` if a child was selected | Leaves stale selectedNodeId string in state | Check `isDescendant` in `deleteNode` in Phase 06 Sprint 0 | Tech Lead | OPEN |

