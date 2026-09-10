v1.3.188 — WordPress local profile UI: hide “Website ini —” presentation prefix while retaining the empty profile value and all internal local publish/taxonomy behavior.

Japur Extractor AI module v3.0.61

Perubahan v3.0.61 — Security hardening kredensial.
- API Key OpenAI, OAuth Client Secret, access token, dan refresh token disimpan terenkripsi menggunakan AES-256-GCM dengan kunci berbasis WordPress salts.
- Secret lama dimigrasikan otomatis tanpa perlu memasukkan ulang.
- Nilai secret tidak pernah dikirim kembali ke field HTML setelah tersimpan.
- Pengaturan kredensial dibatasi ke administrator (`manage_options`); workspace pembuatan artikel tetap memakai `edit_posts`.
- Test API dan pengelolaan koneksi/daftar blog Blogger ikut memakai capability administrator.
- Copy plaintext OAuth legacy `jaf_blogger_oauth_tokens` dihapus setelah migrasi.

Japur Extractor AI module v3.0.44

Persyaratan: WordPress 5.8 atau lebih baru; PHP 7.4 atau lebih baru.
Author: Japur Ganteng
Plugin URI: https://jayapurnama.com

v3.0.44 — Workflow notification UI.
- Mengganti feedback klasik OK:/ERROR:/⏳ pada workflow utama dengan Progress Card modern.
- Menampilkan progress persentase visual yang tidak mengklaim progress internal API.
- Menampilkan step Ekstrak → Artikel → Thumbnail → Terbitkan.
- Hasil artikel dan thumbnail ditata dalam Result Workspace berbasis card/grid.
- Area publikasi dan status sukses/gagal dibuat konsisten dengan UI Suite.
- Mesin ekstraksi, AJAX action, transient job, thumbnail staging, dan publisher tetap dipertahankan.

Perbaikan metadata versi dan kompatibilitas tanpa mengubah fungsi generator yang sudah berjalan.

v3.0.33 — Blogger blog list endpoint and zero-result handling fix.
- Memperbaiki endpoint daftar blog memakai endpoint resmi Blogger API.
- Menangani respons blogUserInfos sebagai fallback.
- Menampilkan penjelasan jika Google mengembalikan 0 blog.
- Sinkronisasi otomatis tetap membuat profil Blogger tanpa input manual.
- Memperbaiki nomor versi plugin menjadi 3.0.33.
- Status Google mengikuti refresh token/access token aktif.

Japur Extractor AI v3.0.15 — WordPress

Peran: mesin pencipta artikel + thumbnail. Tidak mengambil alih Auto WebP/Watermark.

Perubahan:
- Opsi sumber materi: tempel langsung atau ekstrak dari URL.
- Artikel mengikuti Master Prompt dan format field Javanese Auto Post Importer.
- AI tidak lagi menghasilkan metadata attachment seperti title/caption/description/alt/name; metadata gambar diserahkan ke Auto WebP + Watermark.
- Thumbnail tetap dibuat AI dan distaging sebelum Apply.
- Saat Apply, attachment hanya dikaitkan ke post dan dijadikan featured image; metadata tidak ditimpa oleh Extractor.
- Fungsi lain dipertahankan.


v3.0.15 — Blogger Publisher:
- Opsi tujuan publikasi WordPress atau Blogger pada workflow.
- OAuth 2.0 Google untuk Blogger API.
- Publish judul + konten + thumbnail ke Blogger.
- Kategori/tag terpilih dikirim sebagai Blogger labels.
- Alur WordPress lama tetap dipertahankan.


3.0.39 - Local URL extractor upgraded: article-body-only extraction, boilerplate removal, partial-extraction rejection, extraction stats/preview, and no source title/description sent to AI. Extraction uses PHP/DOM only; AI is used after clean material is ready.

## v3.0.73
- Workflow WordPress kini dapat memuat kategori dan tag dari website tujuan yang dipilih pada profil WordPress.
- Pemilihan Website WordPress otomatis menyegarkan daftar kategori dan tag melalui REST API.
- Website lokal juga menggunakan kategori dan tag WordPress aktual, bukan daftar hardcoded.
- Pagination taxonomy remote menggunakan header X-WP-TotalPages dan tetap memakai Application Password profil tujuan.
