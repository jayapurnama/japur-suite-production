v1.3.275
* Phase 1 Master Workflow Control Center: centralized status overview and safe default reset. — Multi Post Foreground Smart Retry + Publication Diagnostics diperkuat; dibangun dari baseline stabil v1.3.263 tanpa mengubah Master System Audit baseline.

v1.3.263 — UI polish aman: tombol Hentikan Proses diposisikan di tengah dan label Website Ini ditampilkan sebagai nama domain tanpa mengubah identifier internal, worker, Background, Foreground, atau Retry.
v1.3.262 — Form Guard & Smart Retry Fix: validasi Jenis Artikel sebelum proses, System Check ikut memeriksa Jenis Artikel, Background tidak dapat membuat job tanpa Jenis Artikel, dan Retry tahap Artikel dapat memakai Jenis Artikel yang baru dipilih.
= 1.3.257 =
* Perbaikan Multi Website: Website WordPress lokal memakai domain aktif pada daftar tujuan dan System Check mengenalinya sebagai WordPress Website Ini.
* Publication Diagnostics Multi kini menyediakan Retry per tujuan gagal untuk Background.
* Retry Multi Background hanya mengulang tujuan yang gagal dan melewati tujuan yang sudah sukses/gagal lainnya.
* Perbaikan kontrol retry tanpa mengubah jalur WordPress/Blogger Single yang sudah tervalidasi.


= 1.3.161 =
* Perbaikan Guided Workflow mobile: auto-scroll mengikuti urutan form aktual dan tidak melompati kolom baru.
* Perbaikan transisi Tujuan → Profil → Jenis Artikel → Kategori/Label → Pengaturan → Sumber Materi.
* Extractor AI dinaikkan ke v3.0.105.
=== JaPur Suite Production – WordPress Tools ===
Contributors: japurganteng
Tags: wordpress, gutenberg, webp, watermark, related posts, popup, seo
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.3.265
License: GPL-2.0-or-later

Menggabungkan 5 plugin menjadi satu plugin modular:
- Javanese Auto Post Importer v2.5.3
- Auto Update Post Month & Year v1.6
- Auto WebP Optimizer PRO + Watermark v5.2
- Popup Promo Artikel Random v4.2
- RPI Pro v2.0

Kompatibilitas:
- Option legacy dipertahankan.
- AJAX action Javanese Auto Post Importer tetap japi_v25_import.
- Meta Yoast _yoast_wpseo_focuskw dan _yoast_wpseo_metadesc dipertahankan.
- Option rpi_settings dipertahankan.
- Option auto_date_allowed_categories dipertahankan.
- Metadata _exclude_auto_date dipertahankan.

Catatan:
Nonaktifkan plugin lama setelah memastikan Japur Suite berjalan. Jangan mengaktifkan versi lama dan versi gabungan secara bersamaan karena beberapa hook dapat berjalan ganda.

Japur Auto Index PRO v1.3.6
- Modul tambahan yang terintegrasi dengan Japur Suite.
- Kontrol ON/OFF tersedia dari Dashboard Japur Suite.
- Fungsi inti IndexNow, queue, retry, log, Google Search Console, sitemap, URL Inspection, dan Search Analytics dipertahankan.


Pengaturan Auto WebP + Watermark:
- Target ukuran WebP dan maksimal lebar gambar dapat diatur.
- Watermark dapat diaktifkan/nonaktifkan, diubah teks, posisi, dan transparansinya.
- Nama file serta metadata attachment dapat dikontrol per bagian.


JaPur Suite Production 1.3.227
- Workflow feedback Japur Extractor AI diperbarui menjadi Progress Card, stepper, Result Workspace, dan Publish Card.
- Versi modul Extractor: 3.0.44.

JaPur Suite Production — Changelog 1.3.229–1.3.256

1.3.229 — Article Manager loaded by core.
1.3.230–1.3.231 — Article Manager grid/layout refinements.
1.3.234 — System Check and Publication Diagnostics.
1.3.235–1.3.236 — Background processing and worker-state fixes.
1.3.237 — Background Single auto-publish and Multi background workflow.
1.3.243 — Master Workflow Settings.
1.3.247 — Background emergency stop/cancel control.
1.3.249 — Blogger Single Background direct-publish reliability path.
1.3.250 — Master System Audit.
1.3.251 — Master Reunified release.
1.3.252 — Blogger Foreground Hide Workflow fix.
1.3.256 — Final audited reunification; preserves all baseline files and cumulative features.

Original baseline: 1.3.228.
Current master: 1.3.256.

- v1.3.256: Background Single workflow progress smoothing and stage normalization; proven four-way Hide Alur Kerja behavior retained.


## v1.3.275 — Master Workflow UI Wrapper Fix
- Memperbaiki pembungkus kartu status Master Workflow agar seluruh kartu menggunakan wrapper HTML yang valid dan konsisten.
- Status Aktif/Nonaktif/Siap ditampilkan sebagai badge yang terpisah dan lebih mudah dibaca di mobile.
- Menghapus label fase yang tampil pada UI; tidak ada teks “Phase 7” pada halaman Master Workflow.
- Hanya presentation/UI layer yang diubah; engine, option, hook, endpoint, workflow, dan data lama dipertahankan.

## v1.3.272 — Master Workflow Diagnostics Controller + Stage Controller
- Added centralized diagnostics display controls.
- Preserves existing retry, foreground, background, and workflow behavior.


1.3.272 — Master Workflow Safety Guard Controller
- Added centralized Safety Guard settings for preflight, duplicate-start protection, active background protection, and final validation.
- Preserves existing workflow engines and settings.

- v1.3.272: Master Workflow Profiles (Standard, Safe, Fast, Auto, Custom).


## v1.3.275 — Developer Mode / Advanced Diagnostics
- Read-only technical diagnostics: Workflow Run ID, Job ID, target, current stage, retry count, HTTP status, timestamp, job state, and diagnostic detail.
- Browser-session Developer Log with bounded entries.
- Copy Diagnostic and Clear/Reset temporary diagnostics.
- Developer Mode is OFF by default and does not add server/database mutation controls.
