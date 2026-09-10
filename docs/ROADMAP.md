# JaPur Suite Production — Roadmap

## Prinsip Upgrade
- Baseline yang sudah terbukti berjalan tidak boleh dihapus atau rusak.
- Fitur baru ditambahkan secara modular dan backward-compatible.
- `main` adalah jalur produksi dan tidak digunakan untuk eksperimen.
- Eksperimen dilakukan di branch development.
- Setiap kandidat rilis wajib melalui audit fitur lama, syntax check, regression check, dan pemeriksaan risiko fatal error sebelum dianggap siap.

## Discovery & Source Workflow

### Fase 1 — Source Sync Stable
**Status: STABLE**
- Post sitemap / sitemap index
- Deteksi sitemap post/news/web
- Artikel terbaru
- Queue
- Extract → Materi
- Integrasi ke workflow Buat Artikel

### Fase 2 — Category Source Engine
**Status: PROTOTYPE TERUJI**
- WordPress REST API publik
- RSS / Atom
- Filter kategori
- Urut terbaru
- Maksimal 10 hasil
- Tanpa API key
- Fallback ke sumber alternatif

### Fase 3 — Sitemap Hunter Discovery Engine
**Status: AUDIT / ROADMAP**
Sumber referensi: `JaPur Sitemap Hunter 2.0.1-Safe.zip`.

Fungsi yang diprioritaskan untuk diadopsi secara modular:
- URL normalizer
- Safe HTTP fetch
- XML parser dengan proteksi `LIBXML_NONET`
- Direct URLSET detection
- Sitemap index follower
- Post-like URL detector
- Lastmod extraction
- Candidate limit
- Multi-domain quick testing

Catatan audit awal:
- File referensi hanya satu PHP file.
- `php -l` berhasil tanpa error.
- Paket bernama 2.0.1, tetapi header/plugin constant di dalam file menyatakan 2.0.0. Ini harus dinormalisasi sebelum dijadikan rilis resmi.
- Implementasi saat ini masih merupakan plugin mandiri dan belum dihubungkan ke Source Sync.
- Jangan menyalin seluruh plugin ke core. Ambil engine yang diperlukan melalui adapter/modul terisolasi.

### Fase 4 — Unified Web Sumber
Target alur:

`Web Sumber → pilih artikel terbaru → Gunakan → Ekstrak Materi → Materi Artikel → Generate → Thumbnail → Preview → Publish`

Provider discovery yang direncanakan:
1. Sitemap / Sitemap Hunter
2. REST API kategori
3. RSS / Atom
4. Provider discovery tambahan bila benar-benar diperlukan

Semua provider harus menghasilkan format kandidat yang seragam sebelum masuk Queue.

### Fase 5 — Production Hardening
- Regression test seluruh workflow lama
- Error isolation per provider
- Timeout dan response-size limits
- Validasi domain sumber
- Deduplication
- Logging yang aman
- Graceful fallback
- Dokumentasi versi dan migration notes

## Baseline Proteksi
Source Sync v1.2.3 tetap menjadi baseline stabil sampai integrasi baru terbukti aman di staging.
