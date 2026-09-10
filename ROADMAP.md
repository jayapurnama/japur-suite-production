# JaPur Suite Production — Roadmap

## Prinsip Utama

- Pengembangan dilakukan dari baseline yang sudah terbukti berjalan.
- Upgrade bersifat additive: fitur lama tidak boleh hilang atau berubah perilakunya.
- Jika refactor arsitektur diperlukan, wajib menjaga backward compatibility.
- `main` adalah jalur produksi dan tidak digunakan sebagai area eksperimen.
- Eksperimen dilakukan pada branch development dan dipisahkan bila berisiko.
- Setiap integrasi wajib melewati audit fitur lama, syntax check, regression check, dan pemeriksaan risiko fatal error sebelum dianggap siap.

## Fase 1 — Source Sync Stable ✅

Baseline: Source Sync v1.2.3.

- Domain sumber.
- Deteksi sitemap index/urlset.
- Dukungan sitemap `post`, `posts`, `news`, dan `web`.
- Filter sitemap non-artikel.
- Artikel terbaru.
- Queue artikel.
- Extract → Materi.
- Konsumsi queue setelah Extract berhasil.

## Fase 2 — Category Source Engine 🟢

Mesin terisolasi yang sudah diuji berhasil.

- WordPress REST API publik.
- RSS/Atom fallback.
- Filter kategori.
- Urut berdasarkan artikel terbaru.
- Maksimal 10 kandidat pada discovery.
- Tidak membutuhkan API key.
- Validasi URL dan domain sumber.

Status: prototype berhasil diuji; belum menggantikan Source Sync.

## Fase 3 — JaPur Sitemap Hunter 🟡

JaPur Sitemap Hunter diposisikan sebagai **Discovery Engine**, bukan pengganti Source Sync.

Kemampuan yang dipertahankan dari Hunter:

- URL normalizer.
- Safe HTTP.
- XML parser.
- Direct URLSET detection.
- Sitemap index follower.
- Post detector.
- Lastmod.
- Limit kandidat.
- Pemeriksaan beberapa pola sitemap.

Target integrasi:

`Domain → Hunter → kandidat sitemap → artikel/post → normalisasi → discovery result`

Catatan: paket yang diunggah bernama `JaPur-Sitemap-Hunter-2.0.1-Safe.zip`, sementara header kode internal saat audit menunjukkan versi `2.0.0`. Nomor internal akan diselaraskan saat integrasi resmi agar tidak membingungkan versi.

## Fase 4 — Unified Web Sumber 🔵

Semua mesin discovery masuk ke satu UI **Web Sumber** di workflow Buat Artikel.

```text
WEB SUMBER
├── Sitemap
│   └── Source Sync + Sitemap Hunter
├── Kategori
│   ├── REST API
│   └── RSS / Atom
└── Discovery Engine
```

Semua hasil menggunakan pipeline yang sama:

`Artikel terbaru → Pilih → Gunakan → Ekstrak Materi → Materi Artikel → Generate → Thumbnail → Preview → Publish`

## Fase 5 — Smart Discovery 🟣

Target jangka lanjut:

- Pemeringkatan kandidat.
- Deduplicate lintas provider.
- Deteksi artikel terbaru berdasarkan tanggal.
- Fallback otomatis antar sumber.
- Cache hasil discovery.
- Kontrol jumlah request.
- Proteksi timeout dan resource.
- Riwayat sumber dan status discovery.

## Fase 6 — Production Hardening 🔒

Sebelum masuk production:

- PHP syntax audit seluruh PHP.
- Audit hook/action/filter.
- Audit AJAX nonce/capability.
- Audit SSRF/public URL validation.
- Regression check Source Sync.
- Regression check Buat Artikel.
- Regression check Queue → Extract → Materi.
- Pemeriksaan duplicate class/function.
- Pemeriksaan kompatibilitas WordPress/PHP.
- Uji staging sebelum merge ke production.

## Status Saat Ini

**Aktif dikerjakan:** Fase 2 → Fase 3 → Fase 4.

**Baseline aman:** Source Sync v1.2.3.

**Aturan merge:** tidak ada fitur eksperimen yang boleh masuk `main` sebelum lulus pengujian dan regression check.
