<?php
if(!defined('ABSPATH')) exit;
class JAF_Prompt {
 public static function default_prompt(){ return <<<'PROMPT'
ATURAN BAHASA:
Seluruh artikel wajib ditulis dalam bahasa Indonesia yang natural, rapi, informatif, dan mudah dipahami. Aturan berlaku untuk judul, deskripsi, lead, pembuka, isi, subjudul, bullet, kesimpulan, FAQ, dan metadata. Jangan menulis artikel dalam bahasa Inggris. Gunakan bahasa Indonesia yang natural dan tidak kaku. Gunakan kata transisi secara alami, jangan dipaksakan.

ATURAN PALING PENTING TENTANG MATERI:
Materi adalah bahan isi artikel. Gunakan hanya sebagai dasar fakta, informasi, data, konteks, cerita, opini, spesifikasi atau detail. Jangan pernah membahas keberadaan materi. Jangan pernah mengatakan artikel dibuat berdasarkan materi. Jangan menyebut input pengguna sebagai sumber tulisan.
Dilarang menggunakan makna seperti: "berdasarkan materi yang diberikan", "berdasarkan materi", "menurut materi", "materi menyebutkan", "materi tersebut", "dalam materi", "dari materi", "sesuai materi", "informasi yang diberikan", "informasi yang tersedia", "informasi di atas", "sumber yang diberikan", "berdasarkan sumber", "teks yang diberikan", atau "data yang diberikan".
Jika materi memiliki atribusi nyata seperti menurut pemerintah, menurut perusahaan, ujar pejabat, menurut pengamat, atau berdasarkan keterangan resmi, atribusi tersebut boleh dipertahankan. Jangan menciptakan atribusi baru. Jika informasi tidak tersedia, jangan membahasnya dan jangan mengarang. Jangan membuat fakta, angka, nama, tanggal, lokasi, kutipan, spesifikasi, harga, jabatan, statistik, peristiwa, pengalaman, atau klaim baru. Jangan menjelaskan proses penulisan dan jangan menyebut AI.

KETENTUAN SEO:
Tentukan SATU frasa utama paling relevan. Frasa utama wajib muncul natural pada judul, paragraf pertama, minimal satu H2, dan meta description. Gunakan variasi natural dan hindari keyword stuffing.
Judul maksimal 15 kata dan ideal sekitar 50–60 karakter bila memungkinkan. Meta description sekitar 140–155 karakter dan harus memuat frasa utama.

KETENTUAN READABILITY:
Utamakan kalimat aktif. Minimalkan kalimat pasif. Rata-rata kalimat idealnya tidak lebih dari 20 kata. Paragraf 2–4 kalimat. Gunakan transisi secara alami. Hindari kalimat terlalu panjang, berbelit, dan repetitif.

ATURAN LEAD:
Pembuka harus langsung membahas topik. Untuk artikel berita, lead mengikuti format: <strong>{{TARGET_DOMAIN}}</strong> - <strong>LOKASI</strong>, lalu ringkasan inti berita. WAJIB gunakan token {{TARGET_DOMAIN}} persis pada posisi nama website/domain; plugin akan menggantinya otomatis sesuai tujuan publikasi. Jangan membuat lokasi jika tidak tersedia dalam materi; gunakan lokasi yang benar-benar disebutkan.

ATURAN JENIS ARTIKEL:
BERITA: target 300–400 kata, pembuka ringkas, isi utama padat, dan minimal 3 H2 bila tetap proporsional. Gaya jurnalistik populer, jelas, faktual, tidak sensasional. Gunakan bullet bila memang membantu.
PANDUAN: target 300–400 kata, minimal 3 H2 bila tetap proporsional, langkah dan tips dapat menggunakan bullet/nomor. Wajib ada pembuka, pembahasan utama, tips tambahan bila relevan, kesimpulan, dan FAQ.
PENGALAMAN: target 300–400 kata, sudut pandang orang pertama, cerita mengalir alami, jangan membuat pengalaman baru, minimal 3 H2 bila tetap proporsional. Wajib ada pembuka pengalaman, pengalaman utama, pelajaran, kesimpulan, FAQ.
OPINI: target 300–400 kata, argumentatif, logis, santun dan menarik, minimal 3 H2 bila tetap proporsional. Pisahkan fakta dan pendapat. Jangan menyajikan opini sebagai fakta dan jangan mengarang data. Wajib ada argumen, analisis, sudut pandang, kesimpulan, FAQ.
REVIEW: target 300–400 kata, minimal 4 H2 bila tetap proporsional. Jelaskan produk, fitur, kelebihan, kekurangan, dan siapa yang cocok. Jangan mengarang spesifikasi, fitur, harga, rating, pengalaman, performa, atau informasi teknis. Jika harga tidak tercantum, jangan membahas harga. Link hanya boleh dipertahankan jika memang ada di bahan, terutama affiliate Shopee/TikTok Shop. Jika ada affiliate, tambahkan disclaimer singkat. Jangan menambahkan link eksternal baru.

ATURAN PANJANG ARTIKEL:
Target isi artikel adalah 300–400 kata ATAU sekitar 2.500–3.000 karakter. Utamakan rentang 300–400 kata sebagai ukuran utama dan usahakan mendekati 2.500–3.000 karakter bila tetap natural. Jangan mengorbankan fakta penting, konteks, atau ketepatan hanya untuk mengejar jumlah. Padatkan pengulangan, basa-basi, dan kalimat yang tidak diperlukan. Jika kedua ukuran tidak dapat tercapai sekaligus karena karakteristik bahasa atau materi, prioritaskan kelengkapan fakta dan batas 300–400 kata.

ATURAN HTML CONTENT:
Gunakan hanya <h2>, <h3>, <p>, <strong>, <ul>, <ol>, <li>. Jangan gunakan <html>, <head>, <body>, <article>, div, style, script, code fence, atau Markdown. Untuk output, HTML CONTENT wajib di-escape: < menjadi &lt; dan > menjadi &gt;. Jangan double escaping.

OUTPUT:
Kembalikan JSON VALID dengan field persis: title, description, focus_keyword, content_html, excerpt, slug, image_prompt.
content_html berisi artikel lengkap termasuk lead. Jangan memasukkan label metadata ke content_html. Jangan menambahkan field lain. image_prompt adalah arahan visual thumbnail yang relevan dengan artikel.
PROMPT; }
 public static function default_blogger_prompt(){ return <<<'PROMPT'
ATURAN BAHASA BLOGGER:
Seluruh artikel wajib ditulis dalam bahasa Indonesia yang natural, rapi, informatif, enak dibaca, dan tidak kaku. Aturan berlaku untuk judul, deskripsi, lead, pembuka, isi, subjudul, bullet, kesimpulan, FAQ, dan metadata. Jangan menulis bagian artikel dalam bahasa Inggris kecuali nama diri, istilah resmi, merek, atau kutipan yang memang ada pada bahan.

ATURAN MATERI:
Materi adalah bahan fakta dan informasi artikel. Gunakan hanya fakta yang tersedia. Jangan pernah menyebut materi, bahan, input, proses penulisan, AI, atau mengatakan artikel dibuat berdasarkan materi. Jangan mengarang nama, angka, tanggal, lokasi, kutipan, spesifikasi, harga, jabatan, statistik, pengalaman, tautan, atau fakta baru. Atribusi nyata yang ada pada bahan boleh dipertahankan, tetapi jangan membuat atribusi baru.

ATURAN SEO BLOGGER:
Tentukan SATU focus keyword paling relevan. Gunakan secara natural pada judul, paragraf pertama, minimal satu H2, dan deskripsi. Hindari keyword stuffing. Judul ringkas dan menarik tanpa clickbait berlebihan. Slug harus pendek, lowercase, memakai tanda hubung, dan hanya kata yang relevan.

ATURAN READABILITY:
Utamakan kalimat aktif. Rata-rata kalimat idealnya tidak lebih dari 20 kata. Gunakan paragraf pendek 2–4 kalimat. Gunakan kata transisi secara alami seperti namun, selain itu, sementara itu, karena itu, oleh karena itu, kemudian, selanjutnya, dan dengan demikian. Jangan memaksakan transisi.

ATURAN LEAD:
Pembuka langsung membahas inti topik. Untuk berita, lead dimulai dengan format <strong>{{TARGET_DOMAIN}}</strong> - <strong>LOKASI</strong>, lalu ringkasan inti berita.
Nama domain pada lead wajib menggunakan kapitalisasi natural dengan huruf awal kapital, misalnya Ruangojol.com, Swan.my.id, atau Sikom.id. Jangan menulis domain lead dengan ALL CAPS atau www.
Lokasi pada lead juga wajib menggunakan kapitalisasi natural, misalnya Jakarta, Jakarta Selatan, atau Jawa Barat. Jangan menulis lokasi dengan ALL CAPS. Jangan mengarang lokasi.

WAJIB menggunakan {{TARGET_DOMAIN}} sebagai nama domain tujuan persis pada posisi tersebut. Jangan menambahkan atau mengubah token menjadi www.{{TARGET_DOMAIN}}, https://{{TARGET_DOMAIN}}, http://{{TARGET_DOMAIN}}, {{TARGET_DOMAIN}}/, atau URL lengkap lainnya. Jangan mengambil domain dari materi sebagai pengganti {{TARGET_DOMAIN}}.

PENTING: nilai {{TARGET_DOMAIN}} berasal dari profil Blogger yang dipilih pengguna. Gunakan nilai tersebut apa adanya. Plugin akan memastikan domain profil dinormalisasi tanpa www, protokol, port, dan slash. Jangan membuat domain lain.

Contoh: jika profil Blogger memiliki https://www.blogkeren.com/, lead WAJIB menjadi <strong><a href="https://blogkeren.com">blogkeren.com</a></strong> - <strong>LOKASI</strong> - ...

Jangan membuat lokasi jika tidak tersedia dalam materi.

ATURAN TAUTAN LEAD BLOGGER:
- Nama domain pada awal lead WAJIB berupa tautan aktif.
- href WAJIB menggunakan HTTPS dan domain profil Blogger yang sudah dinormalisasi tanpa www.
- Format wajib: <strong><a href="https://{{TARGET_DOMAIN}}">{{TARGET_DOMAIN}}</a></strong> - <strong>LOKASI</strong> - ...
- Jangan menggunakan http://, https://www., www., slash akhir, domain lain, atau URL dari materi sebagai href lead.
- Plugin akan melakukan validasi akhir terhadap href dan domain lead sebelum artikel dipublikasikan.

ATURAN TAUTAN LEAD BLOGGER:
- Nama domain pada awal lead WAJIB berupa tautan aktif.
- href WAJIB menggunakan HTTPS dan domain profil Blogger yang sudah dinormalisasi tanpa www.
- Format wajib: <strong><a href="https://{{TARGET_DOMAIN}}">{{TARGET_DOMAIN}}</a></strong> - <strong>LOKASI</strong> - ...
- Jangan menggunakan http://, https://www., www., slash akhir, domain lain, atau URL dari materi sebagai href lead.
- Plugin akan melakukan validasi akhir terhadap href dan domain lead sebelum artikel dipublikasikan.

ATURAN JENIS ARTIKEL:
BERITA: target 300–400 kata, pembuka ringkas, isi utama padat, dan minimal 3 H2 bila tetap proporsional. Gaya jurnalistik populer, jelas, faktual, dan tidak sensasional.
PANDUAN: target 300–400 kata, minimal 3 H2 bila tetap proporsional, langkah dan tips dapat menggunakan bullet atau nomor. Wajib ada pembuka, pembahasan utama, tips tambahan bila relevan, kesimpulan, dan FAQ.
PENGALAMAN: target 300–400 kata, sudut pandang orang pertama hanya jika materi memang memuat pengalaman. Jangan menciptakan pengalaman baru. Minimal 3 H2 bila tetap proporsional dan sertakan pelajaran/kesimpulan serta FAQ.
OPINI: target 300–400 kata, argumentatif, logis, santun. Pisahkan fakta dan pendapat. Jangan menyajikan opini sebagai fakta dan jangan mengarang data. Minimal 3 H2 bila tetap proporsional, kesimpulan, dan FAQ.
REVIEW: minimal 600 kata, minimal 4 H2. Bahas produk, fitur, kelebihan, kekurangan, dan siapa yang cocok hanya berdasarkan informasi yang tersedia. Jika harga tidak tercantum, jangan membahas harga. Jangan mengarang spesifikasi, fitur, rating, pengalaman, performa, atau informasi teknis. Pertahankan tautan hanya jika memang ada di bahan. Jangan menambahkan tautan eksternal baru.

ATURAN PANJANG ARTIKEL:
Target isi artikel adalah 300–400 kata ATAU sekitar 2.500–3.000 karakter. Utamakan rentang 300–400 kata sebagai ukuran utama dan usahakan mendekati 2.500–3.000 karakter bila tetap natural. Jangan mengorbankan fakta penting, konteks, atau ketepatan hanya untuk mengejar jumlah. Padatkan pengulangan, basa-basi, dan kalimat yang tidak diperlukan. Jika kedua ukuran tidak dapat tercapai sekaligus karena karakteristik bahasa atau materi, prioritaskan kelengkapan fakta dan batas 300–400 kata.

ATURAN HTML BLOGGER:
content_html harus berupa HTML siap publish ke Blogger, BUKAN Markdown dan BUKAN HTML entity escaped. Gunakan hanya <h2>, <h3>, <p>, <strong>, <ul>, <ol>, <li>, <blockquote>, dan <a> bila memang ada pada bahan. Jangan gunakan <html>, <head>, <body>, <article>, <div>, <style>, <script>, code fence, atau Markdown. Jangan membungkus seluruh artikel dengan container tambahan. HTML harus valid dan bersih.

ATURAN THUMBNAIL:
image_prompt boleh berisi arahan visual yang benar-benar relevan dengan artikel, tetapi plugin akan memakai Prompt Thumbnail Global. Jangan menambahkan teks, tulisan, logo, watermark, atau elemen yang tidak diperlukan.

ATURAN PUBLIKASI BLOGGER:
Hasil artikel akan dikirim ke Blogger hanya setelah pengguna menekan tombol "Upload ke Blogger" setelah thumbnail selesai dibuat. Judul dan label yang dipilih pengguna akan dikirim otomatis ke Blogger. Jangan membuat label baru di luar label yang diberikan plugin. Artikel harus siap dipublikasikan, bukan draft.

OUTPUT:
Kembalikan JSON VALID dengan field persis: title, description, focus_keyword, content_html, excerpt, slug, image_prompt.
content_html berisi artikel lengkap termasuk lead. Jangan memasukkan label metadata ke content_html. Jangan menambahkan field lain.
PROMPT; }
 public static function build($base,$material,$type,$location,$category_text='',$tag_text='',$target_domain='',$language='id',$title_guard='',$word_min=300,$word_max=400){
  $domain=$target_domain ?: wp_parse_url(home_url(),PHP_URL_HOST);
  $lang=sanitize_key($language)==='en'?'en':'id';
  $language_guard=$lang==='en' ? "\n\nFINAL LANGUAGE OVERRIDE — ENGLISH:\nThe entire final output MUST be in natural English. This rule applies to title, description, focus keyword, excerpt, slug, content_html, and all article text. Keep proper names, official terms, brands, and genuine quotations unchanged when necessary.\n" : "\n\nFINAL LANGUAGE OVERRIDE — INDONESIAN:\nThe entire final output MUST be in natural Indonesian. This rule applies to title, description, focus keyword, excerpt, slug, content_html, and all article text. Keep proper names, official terms, brands, and genuine quotations unchanged when necessary.\n";
  $word_min=max(100,(int)$word_min); $word_max=max($word_min,(int)$word_max);
  $length_guard="\n\nFINAL ARTICLE LENGTH GUARD — WAJIB:\nTarget artikel sekitar {$word_min}–{$word_max} kata. Instruksi ini adalah pengaturan panjang aktif dari Mode Biaya & Kualitas dan menggantikan target panjang generik lain yang mungkin tertulis di prompt. Usahakan berada dalam rentang tersebut secara natural. Jangan mengorbankan fakta penting, konteks, ketepatan, struktur, atau readability hanya demi mengejar jumlah kata. Jangan memperpendek artikel hanya karena target lama 300–400 kata masih muncul di aturan sebelumnya. Jangan memperpanjang dengan pengulangan, basa-basi, atau fakta baru.\n\n";
  $title_guard_text='';
  if(trim($title_guard)!==''){ $title_guard_text="\n\nATURAN JUDUL UNTUK BATCH MULTI WEBSITE — WAJIB:\nJudul artikel ini harus berbeda dari judul berikut yang sudah dipakai pada batch yang sama:\n".sanitize_textarea_field($title_guard)."\nJangan menyalin atau hanya mengubah tanda baca dari judul-judul tersebut. Buat judul baru yang tetap akurat, relevan dengan isi, natural, dan sesuai karakter website tujuan.\n"; }
  $extra="\n\nKONFIGURASI PUBLIKASI:\nBahasa artikel: ".($lang==='en'?'English':'Bahasa Indonesia')."\nJenis artikel: ".sanitize_text_field($type)."\nWebsite tujuan: ".($domain?:get_bloginfo('name'))."\nKategori/Label: ".sanitize_text_field($category_text)."\nTag: ".sanitize_text_field($tag_text)."\nLokasi lead: ".($location?:'Tidak disebutkan').$title_guard_text."\nBAHAN ISI:\n".$material;
  return str_replace('{{TARGET_DOMAIN}}',sanitize_text_field($domain),$base).$language_guard.$length_guard.$extra;
 }
}
require_once dirname(__FILE__).'/class-prompt.php';
