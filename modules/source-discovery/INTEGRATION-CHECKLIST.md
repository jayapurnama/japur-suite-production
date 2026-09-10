# Integration Safety Checklist

- [x] Existing Source Sync file untouched by this bridge stage.
- [x] Existing Source Sync queue untouched.
- [x] Existing Source Sync seen history untouched.
- [x] Existing Article Extractor files untouched.
- [x] No database writes from the Unified Discovery bridge.
- [x] Dedicated AJAX action and nonce.
- [x] `edit_posts` capability check.
- [x] URL validation before discovery.
- [x] Unified engine loaded only when available.
- [x] Production `main` untouched.
- [ ] Web Sumber UI wiring — next stage.
- [ ] Runtime WordPress integration test — required before production merge.
