# Implementation Plan: Q-Les Backend

## Overview

Implementasi REST API Laravel 12 untuk aplikasi manajemen sekolah Q-Les secara bertahap, dimulai dari fondasi database dan autentikasi, lalu fitur kelas, tugas, ujian, penilaian, chat, hingga profil.

## Tasks

- [x] 1. Setup fondasi proyek: migrasi database, enum, dan model Eloquent
  - [x] 1.1 Buat file migrasi untuk semua tabel
    - Buat migrasi: `classrooms`, `class_members`, `assignments`, `questions`, `submissions`, `answers`, `grades`, `messages`
    - Tambahkan kolom enum `role` (`admin`, `guru`, `murid`) ke tabel `users` dengan default `murid`
    - Tambahkan kolom `google_id` (nullable) dan `avatar_url` (nullable) ke tabel `users`
    - Tambahkan unique constraint `(classroom_id, user_id)` pada `class_members`
    - Tambahkan unique constraint `(assignment_id, user_id)` pada `submissions`
    - Tambahkan unique constraint `(submission_id, question_id)` pada `grades`
    - _Requirements: 1.1, 2.1, 3.1, 6.1, 7.1, 9.1_

  - [x] 1.2 Buat PHP Enum classes
    - Buat `app/Enums/UserRole.php` dengan case: `Admin`, `Guru`, `Murid`
    - Buat `app/Enums/QuestionType.php` dengan case: `PilihanGanda`, `PilihanGandaKompleks`, `Uraian`
    - Buat `app/Enums/SubmissionStatus.php` dengan case: `Dikumpulkan`, `Terlambat`
    - _Requirements: 2.1, 6.2, 7.1_

  - [x] 1.3 Buat Eloquent Model untuk semua entitas
    - Update `User` model: tambahkan cast untuk `role` ke `UserRole`, fillable, relasi `hasMany(Classroom)`, `belongsToMany(Classroom, class_members)`, `hasMany(Submission)`, `hasMany(Message)`
    - Buat `Classroom` model dengan relasi `belongsTo(User, teacher_id)`, `belongsToMany(User, class_members)`, `hasMany(Assignment)`, `hasMany(Message)`
    - Buat `ClassMember` model (pivot dengan timestamps, kolom `joined_at`)
    - Buat `Assignment` model dengan cast `exam_mode` ke boolean, relasi ke `Question`, `Submission`, `Message`
    - Buat `Question` model dengan cast `options` ke array/JSON, cast `type` ke `QuestionType`
    - Buat `Submission` model dengan cast `gesture_log` ke array, cast `status` ke `SubmissionStatus`
    - Buat `Answer`, `Grade`, `Message` model dengan relasi yang sesuai
    - _Requirements: 2.1, 3.1, 6.1, 7.1, 8.4, 9.1_

- [x] 2. Autentikasi: Sanctum, Google OAuth, dan middleware peran
  - [x] 2.1 Install dan konfigurasi Laravel Sanctum dan Socialite
    - Jalankan `php artisan install:api` untuk Sanctum
    - Tambahkan konfigurasi Google OAuth di `config/services.php`
    - Pastikan `HasApiTokens` trait ada di `User` model
    - _Requirements: 1.1, 1.3, 1.6_

  - [x] 2.2 Buat `AuthService` dan `AuthController`
    - Buat `app/Services/AuthService.php` dengan method: `register`, `loginWithCredentials`, `loginWithGoogle`, `logout`
    - Method `loginWithGoogle`: verifikasi token Google via Socialite, buat atau temukan user, terbitkan Sanctum token
    - Method `logout`: cabut token aktif user (`$user->currentAccessToken()->delete()`)
    - Buat `app/Http/Controllers/AuthController.php` dengan endpoint: `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/google`, `POST /api/auth/logout`
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6_

  - [x] 2.3 Buat Form Request untuk validasi autentikasi
    - Buat `LoginRequest`, `RegisterRequest`, `GoogleAuthRequest`
    - _Requirements: 1.1, 1.2_

  - [x] 2.4 Tulis unit test untuk AuthService
    - Test login berhasil mengembalikan token
    - Test login dengan kredensial salah mengembalikan HTTP 401
    - Test Google OAuth token tidak valid mengembalikan HTTP 401
    - Test logout mencabut token
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

  - [x] 2.5 Buat `RoleMiddleware` untuk otorisasi berbasis peran
    - Buat `app/Http/Middleware/RoleMiddleware.php`
    - Middleware menerima parameter peran (contoh: `role:guru`, `role:admin`)
    - Kembalikan HTTP 403 jika peran user tidak sesuai
    - Daftarkan middleware di `bootstrap/app.php`
    - _Requirements: 2.4, 2.5, 2.6_

  - [x] 2.6 Tulis property test untuk RoleMiddleware
    - **Property 1: Setiap user dengan peran X tidak dapat mengakses endpoint yang membutuhkan peran Y (X ≠ Y)**
    - **Validates: Requirements 2.4, 2.5**
    - _Requirements: 2.4, 2.5_

- [x] 3. Checkpoint — Pastikan semua test lulus, tanyakan jika ada pertanyaan.

- [x] 4. Manajemen Kelas
  - [x] 4.1 Buat `ClassService` dan `ClassController`
    - Buat `app/Services/ClassService.php` dengan method: `create`, `generateUniqueCode`, `join`, `removeMember`, `search`
    - `generateUniqueCode`: hasilkan kode 6–8 karakter alfanumerik uppercase yang unik (loop hingga tidak ada duplikat)
    - `join`: validasi kode, cek duplikat keanggotaan (HTTP 409), simpan `ClassMember` dengan `joined_at`
    - `removeMember`: hapus keanggotaan, kembalikan HTTP 404 jika user bukan anggota
    - `search`: filter berdasarkan nama atau kode (case-insensitive), support pagination `page` dan `per_page`
    - Buat `app/Http/Controllers/ClassController.php` dengan semua endpoint kelas
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 4.1, 4.2, 4.3, 4.4, 4.5, 5.1, 5.2, 13.1, 13.2, 13.3, 13.4_

  - [x] 4.2 Buat Form Request untuk validasi kelas
    - Buat `CreateClassRequest`, `JoinClassRequest`
    - `JoinClassRequest`: validasi format kode (6–8 karakter alfanumerik), kembalikan HTTP 422 jika tidak valid
    - _Requirements: 3.1, 4.4_

  - [x] 4.3 Tulis property test untuk ClassService
    - **Property 2: Kode kelas yang dihasilkan selalu unik — tidak ada dua kelas dengan kode yang sama**
    - **Validates: Requirements 3.2**
    - **Property 3: Murid yang bergabung ke kelas yang sama dua kali selalu mendapat HTTP 409**
    - **Validates: Requirements 4.3**
    - _Requirements: 3.2, 4.3_

  - [x] 4.4 Tulis unit test untuk ClassController
    - Test pencarian kelas case-insensitive
    - Test pagination daftar kelas
    - Test hasil pencarian kosong mengembalikan array kosong dengan HTTP 200
    - _Requirements: 13.2, 13.3, 13.4_

- [ ] 5. Tugas dan Soal
  - [-] 5.1 Buat `AssignmentService` dan `AssignmentController`
    - Buat `app/Services/AssignmentService.php` dengan method: `create` (simpan tugas + soal), `getSubmissions`, `submitAssignment`
    - Validasi soal: `pilihan_ganda` tepat 1 `is_correct=true`; `pilihan_ganda_kompleks` ≥1 `is_correct=true`; `uraian` tanpa validasi pilihan
    - Kembalikan HTTP 422 jika daftar soal kosong
    - Buat `app/Http/Controllers/AssignmentController.php` dengan semua endpoint tugas dan submission
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 7.1, 7.2, 7.3, 7.4, 7.5_

  - [~] 5.2 Buat Form Request untuk validasi tugas dan submission
    - Buat `CreateAssignmentRequest`: validasi soal tidak kosong, validasi per tipe soal
    - Buat `SubmitAssignmentRequest`: validasi jawaban, validasi screenshot
    - _Requirements: 6.3, 6.4, 6.7, 7.1, 7.2_

  - [~] 5.3 Tulis property test untuk AssignmentService
    - **Property 4: Soal pilihan_ganda selalu memiliki tepat satu jawaban benar**
    - **Validates: Requirements 6.3**
    - **Property 5: Soal pilihan_ganda_kompleks selalu memiliki minimal satu jawaban benar**
    - **Validates: Requirements 6.4**
    - _Requirements: 6.3, 6.4_

  - [~] 5.4 Tulis unit test untuk submission
    - Test submission duplikat mengembalikan HTTP 409
    - Test submission setelah due_at ditandai "terlambat"
    - Test murid bukan anggota kelas mendapat HTTP 403
    - _Requirements: 7.3, 7.4, 7.5_

- [ ] 6. Mode Ujian
  - [ ] 6.1 Buat `ExamService` dan integrasikan ke submission flow
    - Buat `app/Services/ExamService.php` dengan method: `validateGestureLog`, `isExamMode`
    - Saat submission diterima: cek `assignment.exam_mode`; jika `true` dan `gesture_log` tidak ada, kembalikan HTTP 422 dengan pesan "Rekap gestur wajib disertakan pada mode ujian"
    - Simpan `gesture_log` sebagai JSON di tabel `submissions`
    - Sertakan `gesture_log` dalam respons detail submission untuk Guru
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

  - [ ] 6.2 Tulis property test untuk ExamService
    - **Property 6: Submission pada tugas exam_mode tanpa gesture_log selalu ditolak dengan HTTP 422**
    - **Validates: Requirements 8.3**
    - _Requirements: 8.2, 8.3_

- [ ] 7. Checkpoint — Pastikan semua test lulus, tanyakan jika ada pertanyaan.

- [ ] 8. Penilaian
  - [ ] 8.1 Buat `GradeService` dan `GradeController`
    - Buat `app/Services/GradeService.php` dengan method: `gradeSubmission`, `calculateTotalGrade`, `autoGradeObjective`
    - `autoGradeObjective`: hitung nilai otomatis untuk `pilihan_ganda` dan `pilihan_ganda_kompleks` berdasarkan kesesuaian jawaban dengan kunci
    - `calculateTotalGrade`: hitung `SUM(score × weight) / SUM(weight) × 100`, simpan ke `submissions.total_grade`
    - Validasi: `score` tidak boleh melebihi `question.weight`, kembalikan HTTP 422 jika melebihi
    - Buat `app/Http/Controllers/GradeController.php` dengan endpoint: `POST /api/submissions/{id}/grades`, `GET /api/submissions/{id}/grades`
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6_

  - [ ] 8.2 Buat Form Request untuk validasi penilaian
    - Buat `GradeSubmissionRequest`: validasi score tidak melebihi weight soal
    - _Requirements: 9.6_

  - [ ] 8.3 Tulis property test untuk GradeService
    - **Property 7: total_grade selalu dalam rentang 0–100 untuk semua kombinasi score dan weight yang valid**
    - **Validates: Requirements 9.4**
    - **Property 8: Score per soal tidak pernah melebihi weight soal tersebut**
    - **Validates: Requirements 9.6**
    - _Requirements: 9.4, 9.6_

  - [ ] 8.4 Tulis unit test untuk kalkulasi nilai
    - Test formula total_grade dengan berbagai kombinasi bobot
    - Test auto-grade pilihan_ganda: jawaban benar = full score, salah = 0
    - Test auto-grade pilihan_ganda_kompleks: partial credit jika sebagian benar
    - _Requirements: 9.4, 9.5_

- [ ] 9. Chat Kelas dan Chat Tugas
  - [ ] 9.1 Buat `ChatService` dan `ChatController`
    - Buat `app/Services/ChatService.php` dengan method: `sendClassMessage`, `getClassMessages`, `sendAssignmentMessage`, `getAssignmentMessages`
    - Validasi keanggotaan: non-anggota mendapat HTTP 403
    - Pesan diurutkan `created_at ASC`
    - Support pagination dengan parameter `limit` dan `offset` untuk chat kelas
    - Buat `app/Http/Controllers/ChatController.php` dengan semua endpoint chat
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 11.1, 11.2, 11.3_

  - [ ] 9.2 Tulis unit test untuk ChatService
    - Test non-anggota mendapat HTTP 403 saat kirim/baca pesan
    - Test urutan pesan dari terlama ke terbaru
    - Test pagination chat kelas
    - _Requirements: 10.2, 10.3, 10.4_

- [ ] 10. Profil Pengguna
  - [ ] 10.1 Buat `ProfileService` dan `ProfileController`
    - Buat `app/Services/ProfileService.php` dengan method: `getProfile`, `updateAvatar`
    - `updateAvatar`: validasi format (JPEG, PNG, WebP) dan ukuran (maks 2MB), simpan ke `storage/app/public/avatars`, update `users.avatar_url`, kembalikan URL publik
    - Kembalikan HTTP 422 dengan pesan yang sesuai untuk format tidak valid atau ukuran melebihi batas
    - Buat `app/Http/Controllers/ProfileController.php` dengan endpoint: `GET /api/profile`, `POST /api/profile/avatar`
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5_

  - [ ] 10.2 Buat Form Request untuk validasi upload avatar
    - Buat `UpdateAvatarRequest`: validasi `mimes:jpeg,png,webp` dan `max:2048`
    - _Requirements: 12.1, 12.2, 12.3_

  - [ ] 10.3 Tulis unit test untuk ProfileService
    - Test upload format tidak valid mengembalikan HTTP 422
    - Test upload ukuran melebihi 2MB mengembalikan HTTP 422
    - Test upload berhasil mengembalikan URL publik
    - _Requirements: 12.1, 12.2, 12.3, 12.4_

- [ ] 11. Wiring: Daftarkan semua route dan middleware
  - [ ] 11.1 Definisikan semua API route di `routes/api.php`
    - Grup route publik: `POST /auth/register`, `POST /auth/login`, `POST /auth/google`
    - Grup route terautentikasi (`auth:sanctum`): logout, profile, semua endpoint kelas, tugas, submission, grade, chat
    - Terapkan `RoleMiddleware` pada endpoint yang membutuhkan peran spesifik (Guru, Admin)
    - _Requirements: 1.5, 2.4, 2.5, 2.6, 3.1, 4.1, 6.1, 7.1, 9.1, 10.1, 11.1, 12.1_

  - [ ] 11.2 Jalankan `php artisan storage:link` dan pastikan konfigurasi filesystem
    - Pastikan `FILESYSTEM_DISK=public` atau konfigurasi S3 di `.env`
    - _Requirements: 7.2, 12.1_

- [ ] 12. Checkpoint Final — Pastikan semua test lulus, tanyakan jika ada pertanyaan.

## Notes

- Task bertanda `*` bersifat opsional dan dapat dilewati untuk MVP yang lebih cepat
- Setiap task mereferensikan requirement spesifik untuk keterlacakan
- Property test menggunakan PestPHP dengan plugin property-based testing
- Unit test menggunakan PHPUnit/PestPHP dengan database in-memory (SQLite)
- Checkpoint memastikan validasi inkremental sebelum lanjut ke fase berikutnya
