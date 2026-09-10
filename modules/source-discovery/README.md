# Unified Web Sumber Bridge

This directory contains the passive discovery engines and the isolated Web Sumber bridge.

## Safety contract

- Source Sync remains unchanged.
- The bridge does not write the Source Sync queue or seen history.
- The bridge uses a dedicated AJAX action and nonce.
- The loader only requires the bridge file when readable.
- UI wiring and core loading are intentionally separate follow-up steps.

## Discovery chain

Sitemap -> Category/REST -> RSS/Atom -> Adapter -> Unified result
