# Struktur Database Q-Les

## Tabel-tabel yang dibuat (Task 1.1)

### 1. `users` (diperbarui)
- `id` (bigint, primary key)
- `name` (string)
- `email` (string, unique)
- `email_verified_at` (timestamp, nullable)
- `password` (string, nullable) - untuk user Google-only
- `remember_token` (string)
- **`role` (enum: 'admin', 'guru', 'murid', default: 'murid')** - ✅ Task 1.1
- **`google_id` (string, nullable, unique)** - ✅ Task 1.1
- **`avatar_url` (string, nullable)** - ✅ Task 1.1
- `created_at` (timestamp)
- `updated_at` (timestamp)

### 2. `classrooms`
- `id` (bigint, primary key)
- `name` (string)
- `code` (string(8), unique) - 6-8 karakter alfanumerik
- `teacher_id` (foreign key ke `users.id`)
- `created_at` (timestamp)
- `updated_at` (timestamp)

### 3. `class_members`
- `id` (bigint, primary key)
- `classroom_id` (foreign key ke `classrooms.id`, onDelete: cascade)
- `user_id` (foreign key ke `users.id`, onDelete: cascade)
- `joined_at` (timestamp, default: current timestamp)
- **Unique constraint: `(classroom_id, user_id)`** - ✅ Task 1.1
- `created_at` (timestamp)
- `updated_at` (timestamp)

### 4. `assignments`
- `id` (bigint, primary key)
- `classroom_id` (foreign key ke `classrooms.id`, onDelete: cascade)
- `title` (string)
- `description` (text, nullable)
- `exam_mode` (boolean, default: false)
- `due_at` (timestamp, nullable)
- `created_at` (timestamp)
- `updated_at` (timestamp)

### 5. `questions`
- `id` (bigint, primary key)
- `assignment_id` (foreign key ke `assignments.id`, onDelete: cascade)
- `body` (text)
- `type` (enum: 'pilihan_ganda', 'pilihan_ganda_kompleks', 'uraian')
- `options` (json, nullable) - array of {text, is_correct}
- `weight` (decimal(5,2), default: 1.00)
- `order` (integer, default: 0)
- `created_at` (timestamp)
- `updated_at` (timestamp)

### 6. `submissions`
- `id` (bigint, primary key)
- `assignment_id` (foreign key ke `assignments.id`, onDelete: cascade)
- `user_id` (foreign key ke `users.id`, onDelete: cascade)
- `status` (enum: 'dikumpulkan', 'terlambat', default: 'dikumpulkan')
- `screenshot_path` (string, nullable)
- `gesture_log` (json, nullable)
- `total_grade` (decimal(5,2), nullable)
- `submitted_at` (timestamp, default: current timestamp)
- **Unique constraint: `(assignment_id, user_id)`** - ✅ Task 1.1
- `created_at` (timestamp)
- `updated_at` (timestamp)

### 7. `answers`
- `id` (bigint, primary key)
- `submission_id` (foreign key ke `submissions.id`, onDelete: cascade)
- `question_id` (foreign key ke `questions.id`, onDelete: cascade)
- `selected_options` (json, nullable)
- `essay_answer` (text, nullable)
- `created_at` (timestamp)
- `updated_at` (timestamp)

### 8. `grades`
- `id` (bigint, primary key)
- `submission_id` (foreign key ke `submissions.id`, onDelete: cascade)
- `question_id` (foreign key ke `questions.id`, onDelete: cascade)
- `score` (decimal(5,2))
- `feedback` (text, nullable)
- **Unique constraint: `(submission_id, question_id)`** - ✅ Task 1.1
- `created_at` (timestamp)
- `updated_at` (timestamp)

### 9. `messages`
- `id` (bigint, primary key)
- `user_id` (foreign key ke `users.id`, onDelete: cascade)
- `classroom_id` (foreign key ke `classrooms.id`, nullable, onDelete: cascade)
- `assignment_id` (foreign key ke `assignments.id`, nullable, onDelete: cascade)
- `body` (text)
- `created_at` (timestamp)
- `updated_at` (timestamp)
- *Catatan: Salah satu dari `classroom_id` atau `assignment_id` harus diisi (validasi di application level)*

## Konfigurasi Database

### Untuk Development/Testing (default)
```env
DB_CONNECTION=sqlite
```

### Untuk Production (sesuai requirements task 1.1)
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=q_les
DB_USERNAME=root
DB_PASSWORD=
```

## Status Task 1.1
✅ **SEMUA REQUIREMENTS TELAH TERPENUHI**

1. ✅ File migrasi untuk semua tabel sudah dibuat
2. ✅ Kolom enum `role` (`admin`, `guru`, `murid`) dengan default `murid` ditambahkan ke tabel `users`
3. ✅ Kolom `google_id` (nullable) dan `avatar_url` (nullable) ditambahkan ke tabel `users`
4. ✅ Unique constraint `(classroom_id, user_id)` pada `class_members`
5. ✅ Unique constraint `(assignment_id, user_id)` pada `submissions`
6. ✅ Unique constraint `(submission_id, question_id)` pada `grades`
7. ✅ Database siap untuk MySQL (konfigurasi di `.env.example`)

## Migrasi yang dijalankan
Semua migrasi berhasil dijalankan dengan perintah `php artisan migrate:fresh --force`.