# Requirements Document

## Introduction

Q-Les adalah aplikasi manajemen sekolah berbasis web yang dibangun menggunakan Laravel sebagai backend REST API. Sistem ini mendukung tiga peran pengguna: Admin, Guru, dan Murid. Fitur utama mencakup manajemen kelas, tugas, ujian, chat, penilaian, dan autentikasi multi-metode.

## Glossary

- **System**: Backend Laravel Q-Les secara keseluruhan
- **Auth_Service**: Komponen yang menangani autentikasi dan otorisasi pengguna
- **Class_Service**: Komponen yang menangani manajemen kelas
- **Assignment_Service**: Komponen yang menangani tugas dan pengumpulan jawaban
- **Chat_Service**: Komponen yang menangani pesan kelas dan kolom chat tugas
- **Exam_Service**: Komponen yang menangani mode ujian
- **Grade_Service**: Komponen yang menangani penilaian per soal dan keseluruhan
- **Profile_Service**: Komponen yang menangani data profil pengguna
- **User**: Pengguna terdaftar dalam sistem (Admin, Guru, atau Murid)
- **Admin**: Pengguna dengan hak akses penuh untuk mengelola sistem
- **Guru**: Pengguna dengan peran pengajar yang dapat membuat dan mengelola kelas serta tugas
- **Murid**: Pengguna dengan peran pelajar yang dapat bergabung ke kelas dan mengerjakan tugas
- **Kelas**: Ruang belajar virtual yang memiliki kode unik dan dikelola oleh Guru
- **Kode_Kelas**: Kode unik yang digunakan Murid untuk bergabung ke Kelas
- **Tugas**: Pekerjaan yang diberikan Guru kepada Murid dalam sebuah Kelas
- **Soal**: Pertanyaan individual dalam sebuah Tugas
- **Jawaban**: Respons Murid terhadap sebuah Soal
- **Submission**: Kumpulan Jawaban Murid untuk satu Tugas
- **Mode_Ujian**: Kondisi khusus pada Tugas yang mengaktifkan rekap gestur layar
- **Gesture_Log**: Catatan aktivitas gestur layar Murid selama Mode_Ujian aktif
- **Screenshot**: Tangkapan layar yang diambil sistem saat Murid mengumpulkan Tugas
- **Chat_Kelas**: Fitur pesan real-time dalam sebuah Kelas
- **Chat_Tugas**: Kolom diskusi yang terlampir pada sebuah Tugas
- **Token**: JWT atau token autentikasi yang diterbitkan setelah login berhasil

---

## Requirements

### Requirement 1: Autentikasi dan Manajemen Akun

**User Story:** Sebagai pengguna, saya ingin dapat mendaftar dan masuk menggunakan email/password atau akun Google, sehingga saya dapat mengakses sistem dengan aman.

#### Acceptance Criteria

1. WHEN pengguna mengirimkan email dan password yang valid, THE Auth_Service SHALL menerbitkan Token autentikasi dan mengembalikan data profil pengguna.
2. WHEN pengguna mengirimkan email yang tidak terdaftar atau password yang salah, THE Auth_Service SHALL mengembalikan respons error dengan kode HTTP 401 dan pesan yang deskriptif.
3. WHEN pengguna mengirimkan OAuth token Google yang valid, THE Auth_Service SHALL membuat akun baru atau masuk ke akun yang sudah ada, lalu menerbitkan Token autentikasi.
4. WHEN pengguna mengirimkan OAuth token Google yang tidak valid atau kedaluwarsa, THE Auth_Service SHALL mengembalikan respons error dengan kode HTTP 401.
5. WHEN pengguna melakukan permintaan logout dengan Token yang valid, THE Auth_Service SHALL mencabut Token tersebut sehingga tidak dapat digunakan kembali.
6. THE Auth_Service SHALL mendukung metode login: email/password dan Google OAuth 2.0.

---

### Requirement 2: Verifikasi Peran Pengguna

**User Story:** Sebagai Admin, saya ingin memastikan setiap pengguna memiliki peran yang sesuai dengan identitas sungguhan mereka, sehingga hak akses dalam sistem terjaga dengan benar.

#### Acceptance Criteria

1. THE System SHALL menetapkan tepat satu peran kepada setiap User dari daftar peran yang tersedia: Admin, Guru, atau Murid.
2. WHEN pengguna baru mendaftar, THE Auth_Service SHALL menetapkan peran default Murid kecuali ditentukan lain oleh Admin.
3. WHEN Admin mengubah peran seorang User, THE Auth_Service SHALL memperbarui peran User tersebut dan mencabut Token aktif milik User tersebut.
4. WHEN pengguna dengan peran Murid mencoba mengakses endpoint yang hanya diizinkan untuk Guru, THE System SHALL mengembalikan respons error dengan kode HTTP 403.
5. WHEN pengguna dengan peran Guru mencoba mengakses endpoint yang hanya diizinkan untuk Admin, THE System SHALL mengembalikan respons error dengan kode HTTP 403.
6. THE System SHALL memvalidasi peran pengguna pada setiap permintaan API yang memerlukan otorisasi.

---

### Requirement 3: Manajemen Kelas

**User Story:** Sebagai Guru, saya ingin membuat dan mengelola kelas, sehingga saya dapat mengorganisir pembelajaran untuk murid-murid saya.

#### Acceptance Criteria

1. WHEN Guru mengirimkan permintaan pembuatan kelas dengan nama yang valid, THE Class_Service SHALL membuat Kelas baru dan menghasilkan Kode_Kelas yang unik sepanjang 6–8 karakter alfanumerik.
2. THE Class_Service SHALL memastikan setiap Kode_Kelas bersifat unik di seluruh sistem pada saat pembuatan.
3. WHEN Guru meminta daftar kelas miliknya, THE Class_Service SHALL mengembalikan daftar Kelas beserta jumlah anggota Murid aktif di setiap Kelas.
4. WHEN pengguna mengirimkan kata kunci pencarian kelas, THE Class_Service SHALL mengembalikan daftar Kelas yang nama atau kodenya mengandung kata kunci tersebut.
5. WHEN Guru meminta daftar anggota sebuah Kelas, THE Class_Service SHALL mengembalikan daftar seluruh Murid yang terdaftar beserta data nama dan foto profil mereka.
6. WHEN Guru mengirimkan permintaan untuk mengeluarkan seorang Murid dari Kelas, THE Class_Service SHALL menghapus keanggotaan Murid tersebut dari Kelas dan mencabut akses Murid ke seluruh konten Kelas tersebut.
7. IF Guru mencoba mengeluarkan User yang bukan anggota Kelas, THEN THE Class_Service SHALL mengembalikan respons error dengan kode HTTP 404.

---

### Requirement 4: Bergabung ke Kelas via Kode

**User Story:** Sebagai Murid, saya ingin bergabung ke kelas menggunakan kode kelas, sehingga saya dapat mengakses materi dan tugas dari guru saya.

#### Acceptance Criteria

1. WHEN Murid mengirimkan Kode_Kelas yang valid dan belum pernah bergabung ke Kelas tersebut, THE Class_Service SHALL mendaftarkan Murid sebagai anggota Kelas dan mengembalikan data Kelas.
2. WHEN Murid mengirimkan Kode_Kelas yang tidak ditemukan dalam sistem, THE Class_Service SHALL mengembalikan respons error dengan kode HTTP 404 dan pesan "Kode kelas tidak ditemukan".
3. WHEN Murid yang sudah terdaftar di sebuah Kelas mengirimkan Kode_Kelas yang sama, THE Class_Service SHALL mengembalikan respons error dengan kode HTTP 409 dan pesan "Anda sudah terdaftar di kelas ini".
4. THE Class_Service SHALL memvalidasi format Kode_Kelas sebelum melakukan pencarian ke database, dan mengembalikan HTTP 422 jika format tidak valid.
5. WHEN Murid berhasil bergabung ke Kelas, THE Class_Service SHALL mencatat waktu bergabung Murid tersebut.

---

### Requirement 5: Salin Kode Kelas

**User Story:** Sebagai Guru atau Murid, saya ingin dapat menyalin kode kelas dengan mudah, sehingga saya dapat membagikannya kepada orang lain.

#### Acceptance Criteria

1. WHEN pengguna yang merupakan anggota atau pemilik Kelas meminta detail Kelas, THE Class_Service SHALL menyertakan Kode_Kelas dalam respons API.
2. THE Class_Service SHALL mengembalikan Kode_Kelas dalam format teks biasa (plain text) yang dapat langsung disalin oleh klien.

---

### Requirement 6: Tugas dengan Kategori Soal

**User Story:** Sebagai Guru, saya ingin membuat tugas dengan berbagai jenis soal, sehingga saya dapat mengevaluasi pemahaman murid secara komprehensif.

#### Acceptance Criteria

1. WHEN Guru mengirimkan permintaan pembuatan Tugas dengan daftar Soal yang valid, THE Assignment_Service SHALL menyimpan Tugas beserta seluruh Soal-nya.
2. THE Assignment_Service SHALL mendukung tepat tiga kategori Soal: pilihan_ganda, pilihan_ganda_kompleks, dan uraian.
3. WHEN Soal bertipe pilihan_ganda dikirimkan, THE Assignment_Service SHALL memvalidasi bahwa Soal memiliki tepat satu jawaban benar dari minimal dua pilihan.
4. WHEN Soal bertipe pilihan_ganda_kompleks dikirimkan, THE Assignment_Service SHALL memvalidasi bahwa Soal memiliki satu atau lebih jawaban benar dari minimal dua pilihan.
5. WHEN Soal bertipe uraian dikirimkan, THE Assignment_Service SHALL menyimpan Soal tanpa validasi pilihan jawaban.
6. THE Assignment_Service SHALL menyimpan bobot nilai untuk setiap Soal dalam sebuah Tugas.
7. IF Guru mengirimkan Tugas dengan daftar Soal kosong, THEN THE Assignment_Service SHALL mengembalikan respons error dengan kode HTTP 422.

---

### Requirement 7: Pengumpulan Tugas

**User Story:** Sebagai Murid, saya ingin mengumpulkan jawaban tugas saya, sehingga Guru dapat menilai pekerjaan saya.

#### Acceptance Criteria

1. WHEN Murid mengirimkan Submission yang berisi jawaban untuk seluruh Soal wajib dalam sebuah Tugas, THE Assignment_Service SHALL menyimpan Submission dan menandainya sebagai "dikumpulkan".
2. WHEN Murid mengumpulkan Tugas, THE Assignment_Service SHALL menyimpan Screenshot layar Murid yang dikirimkan bersama Submission.
3. WHEN Murid mencoba mengumpulkan Tugas yang sudah pernah dikumpulkan, THE Assignment_Service SHALL mengembalikan respons error dengan kode HTTP 409.
4. IF Murid mengirimkan Submission setelah batas waktu Tugas, THEN THE Assignment_Service SHALL menyimpan Submission dan menandainya sebagai "terlambat".
5. WHEN Murid yang bukan anggota Kelas mencoba mengumpulkan Tugas, THE Assignment_Service SHALL mengembalikan respons error dengan kode HTTP 403.

---

### Requirement 8: Mode Ujian

**User Story:** Sebagai Guru, saya ingin mengaktifkan mode ujian pada tugas tertentu, sehingga aktivitas gestur layar murid dapat direkam sebagai bukti integritas ujian.

#### Acceptance Criteria

1. WHEN Guru mengaktifkan Mode_Ujian pada sebuah Tugas, THE Exam_Service SHALL menandai Tugas tersebut sebagai mode ujian aktif.
2. WHILE Mode_Ujian aktif pada sebuah Tugas, THE Exam_Service SHALL mewajibkan klien untuk mengirimkan Gesture_Log bersama setiap Submission.
3. WHEN Murid mengirimkan Submission pada Tugas dengan Mode_Ujian aktif tanpa menyertakan Gesture_Log, THE Exam_Service SHALL mengembalikan respons error dengan kode HTTP 422 dan pesan "Rekap gestur wajib disertakan pada mode ujian".
4. WHEN Submission dengan Mode_Ujian diterima, THE Exam_Service SHALL menyimpan Gesture_Log yang terkait dengan Submission tersebut.
5. WHEN Guru meminta detail Submission pada Tugas Mode_Ujian, THE Exam_Service SHALL mengembalikan Gesture_Log bersama data Submission.

---

### Requirement 9: Penilaian Per Soal dan Keseluruhan

**User Story:** Sebagai Murid, saya ingin melihat nilai saya per soal dan nilai keseluruhan setelah mengerjakan tugas, sehingga saya dapat mengetahui performa saya.

#### Acceptance Criteria

1. WHEN Guru menilai sebuah Submission, THE Grade_Service SHALL menyimpan nilai untuk setiap Soal secara individual.
2. WHEN nilai seluruh Soal dalam sebuah Submission telah disimpan, THE Grade_Service SHALL menghitung dan menyimpan nilai keseluruhan Submission sebagai jumlah tertimbang dari nilai per Soal.
3. WHEN Murid meminta hasil penilaian Submission miliknya, THE Grade_Service SHALL mengembalikan nilai per Soal dan nilai keseluruhan.
4. THE Grade_Service SHALL menghitung nilai keseluruhan menggunakan formula: (jumlah nilai per soal × bobot soal) / total bobot seluruh soal × 100.
5. WHEN Soal bertipe pilihan_ganda atau pilihan_ganda_kompleks dinilai, THE Grade_Service SHALL menghitung nilai secara otomatis berdasarkan kesesuaian jawaban Murid dengan kunci jawaban.
6. IF nilai yang dikirimkan untuk sebuah Soal melebihi bobot maksimum Soal tersebut, THEN THE Grade_Service SHALL mengembalikan respons error dengan kode HTTP 422.

---

### Requirement 10: Chat Kelas

**User Story:** Sebagai anggota kelas, saya ingin dapat mengirim dan membaca pesan dalam kelas, sehingga saya dapat berkomunikasi dengan guru dan sesama murid.

#### Acceptance Criteria

1. WHEN anggota Kelas mengirimkan pesan teks yang valid ke Chat_Kelas, THE Chat_Service SHALL menyimpan pesan beserta identitas pengirim dan waktu pengiriman.
2. WHEN anggota Kelas meminta riwayat Chat_Kelas, THE Chat_Service SHALL mengembalikan daftar pesan yang diurutkan berdasarkan waktu pengiriman dari yang terlama ke terbaru.
3. WHEN pengguna yang bukan anggota Kelas mencoba mengirim atau membaca pesan Chat_Kelas, THE Chat_Service SHALL mengembalikan respons error dengan kode HTTP 403.
4. THE Chat_Service SHALL mendukung pagination pada riwayat Chat_Kelas dengan parameter limit dan offset.

---

### Requirement 11: Chat Tugas

**User Story:** Sebagai Guru atau Murid, saya ingin dapat berdiskusi langsung pada kolom chat sebuah tugas, sehingga pertanyaan dan klarifikasi terkait tugas dapat terdokumentasi.

#### Acceptance Criteria

1. WHEN anggota Kelas mengirimkan pesan ke Chat_Tugas pada sebuah Tugas, THE Chat_Service SHALL menyimpan pesan beserta identitas pengirim, ID Tugas, dan waktu pengiriman.
2. WHEN anggota Kelas meminta riwayat Chat_Tugas, THE Chat_Service SHALL mengembalikan daftar pesan yang terkait dengan Tugas tersebut, diurutkan berdasarkan waktu pengiriman.
3. WHEN pengguna yang bukan anggota Kelas mencoba mengakses Chat_Tugas, THE Chat_Service SHALL mengembalikan respons error dengan kode HTTP 403.

---

### Requirement 12: Update Foto Profil

**User Story:** Sebagai pengguna, saya ingin dapat memperbarui foto profil saya, sehingga identitas visual saya dalam aplikasi selalu terkini.

#### Acceptance Criteria

1. WHEN pengguna mengirimkan file gambar dengan format JPEG, PNG, atau WebP dan ukuran tidak melebihi 2MB, THE Profile_Service SHALL menyimpan file gambar dan memperbarui URL foto profil pengguna.
2. WHEN pengguna mengirimkan file dengan format selain JPEG, PNG, atau WebP, THE Profile_Service SHALL mengembalikan respons error dengan kode HTTP 422 dan pesan "Format file tidak didukung".
3. WHEN pengguna mengirimkan file gambar yang ukurannya melebihi 2MB, THE Profile_Service SHALL mengembalikan respons error dengan kode HTTP 422 dan pesan "Ukuran file melebihi batas 2MB".
4. WHEN foto profil berhasil diperbarui, THE Profile_Service SHALL mengembalikan URL publik foto profil yang baru.
5. WHEN pengguna meminta data profil, THE Profile_Service SHALL menyertakan URL foto profil terkini dalam respons.

---

### Requirement 13: Dropdown dan Pencarian Kelas

**User Story:** Sebagai pengguna, saya ingin dapat mencari dan memilih kelas dari daftar yang tersedia, sehingga saya dapat menemukan kelas yang saya butuhkan dengan cepat.

#### Acceptance Criteria

1. WHEN pengguna meminta daftar kelas tanpa parameter pencarian, THE Class_Service SHALL mengembalikan seluruh Kelas yang relevan dengan peran pengguna tersebut dalam format yang sesuai untuk dropdown.
2. WHEN pengguna mengirimkan parameter pencarian berupa teks, THE Class_Service SHALL mengembalikan Kelas yang nama atau Kode_Kelas-nya mengandung teks tersebut (case-insensitive).
3. THE Class_Service SHALL mendukung pagination pada daftar kelas dengan parameter page dan per_page.
4. WHEN parameter pencarian menghasilkan nol hasil, THE Class_Service SHALL mengembalikan array kosong dengan kode HTTP 200.
