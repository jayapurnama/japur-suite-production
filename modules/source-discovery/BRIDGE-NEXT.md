# Next Stage: Web Sumber UI Wiring

The next implementation should attach the existing Web Sumber panel to `japur_unified_discover`.

Required behavior:

1. Keep the existing source scan available.
2. Prefer Unified Discovery when the bridge is loaded.
3. If Unified Discovery returns an error or no usable items, keep the existing scanner path available.
4. Reuse the existing article selection and extraction flow.
5. Do not duplicate queue entries.
6. Do not mutate `seen` from the bridge.
7. Do not modify the Article Extractor engine.
8. Keep the change isolated to the discovery module and its UI adapter.

Do not merge into production until runtime WordPress testing is completed.
