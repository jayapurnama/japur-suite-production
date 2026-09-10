# Unified Web Sumber — Integration Status

Status: SAFE BRIDGE STAGE

## Included
- Sitemap Discovery Engine
- Category/REST Discovery Engine
- RSS/Atom Discovery Engine
- Discovery Adapter
- Unified Discovery Coordinator
- Isolated AJAX bridge
- Isolated module entrypoint

## Deliberately not changed
- `modules/source-sync.php` / Source Sync implementation
- Source Sync queue
- Source Sync `seen` history
- Article Extractor workflow
- Production `main` branch

## Next stage
Wire the existing Web Sumber UI to the bridge, while retaining the existing Source Sync scan as a fallback. The UI integration must be additive and must not replace the stable scanner until the new path is proven.
