# Design Document: Q-Les Backend

## Overview

Q-Les Backend adalah REST API berbasis Laravel 12 yang melayani aplikasi manajemen sekolah. Sistem ini mengekspos endpoint JSON untuk tiga peran pengguna (Admin, Guru, Murid) dan menangani autentikasi, manajemen kelas, tugas multi-kategori, mode ujian, penilaian, chat, dan manajemen profil.

**Stack Teknologi:**
- Framework: Laravel 12 (PHP 8.2+)
- Autentikasi Token: Laravel Sanctum (personal access tokens)
- OAuth: Laravel Socialite (Google OAuth 2.0)
- Database: MySQL / PostgreSQL (Eloquent ORM)
- File Storage: Laravel Storage (local/S3-compatible)
- Testing: PHPUnit + PestPHP + Laravel PestPHP Plugin untuk property-based testing

**Keputusan Desain Utama:**
- Menggunakan Laravel Sanctum (bukan Passport) karena lebih ringan untuk SPA/mobile token-based auth
- Token dicabut dengan menghapus entri dari tabel `personal_access_tokens`
- Google OAuth menggunakan Socialite; token Google diverifikasi server-side, bukan client-side
- File foto profil dan screenshot disimpan di `storage/app/public` dengan symlink ke `public/storage`
- Gesture log disimpan sebagai JSON column di tabel submissions
- Chat tidak menggunakan WebSocket (polling-based); real-time dapat ditambahkan di iterasi berikutnya

---

## Architecture

Sistem mengikuti arsitektur **Layered MVC** dengan pemisahan tanggung jawab yang jelas:

```
Client (Mobile/Web)
        │
        ▼
┌─────────────────────────────────────────────────────┐
│                   API Layer (Routes)                 │
│              routes/api.php                          │
└──────────────────────┬──────────────────────────────┘
                       │
        ┌──────────────▼──────────────┐
        │   Middleware Layer           │
        │  - auth:sanctum             │
        │  - role (Admin/Guru/Murid)  │
        │  - throttle                 │
        └──────────────┬──────────────┘
                       │
        ┌──────────────▼──────────────┐
        │   Controller Layer           │
        │  (HTTP request handling)    │
        └──────────────┬──────────────┘
                       │
        ┌──────────────▼──────────────┐
        │   Service Layer              │
        │  (Business Logic)           │
        └──────────────┬──────────────┘
                       │
        ┌──────────────▼──────────────┐
        │   Repository / Model Layer   │
        │  (Eloquent ORM)             │
        └──────────────┬──────────────┘
                       │
        ┌──────────────▼──────────────┐
        │   Database (MySQL/Postgres)  │
        └─────────────────────────────┘
```

**Namespace Struktur Direktori:**
```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── ClassController.php
│   │   ├── AssignmentController.php
│   │   ├── SubmissionController.php
│   │   ├── GradeController.php
│   │   ├── ChatController.php
│   │   └── ProfileController.php
│   ├── Middleware/
│   │   └── RoleMiddleware.php
│   └── Requests/          (Form Request validation)
├── Models/
│   ├── User.php
│   ├── Classroom.php
│   ├── ClassMember.php
│   ├── Assignment.php
│   ├── Question.php
│   ├── Submission.php
│   ├── Answer.php
│   ├── Grade.php
│   ├── Message.php
│   └── GestureLog.php
├── Services/
│   ├── AuthService.php
│   ├── ClassService.php
│   ├── AssignmentService.php
│   ├── ExamService.php
│   ├── GradeService.php
│   ├── ChatService.php
│   └── ProfileService.php
└── Enums/
    ├── UserRole.php
    ├── QuestionType.php
    └── SubmissionStatus.php
```

---

## Components and Interfaces

### Auth Component

**AuthController** menangani semua endpoint autentikasi.

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/api/auth/register` | Daftar dengan email/password |
| POST | `/api/auth/login` | Login email/password |
| POST | `/api/auth/google` | Login/register via Google OAuth token |
| POST | `/api/auth/logout` | Logout (cabut token) |

**AuthService** interface:
```php
interface AuthServiceInterface {
    public function loginWithCredentials(string $email, string $password): array;
    public function loginWithGoogle(string $googleToken): array;
    public function logout(User $user): void;
    public function register(array $data): array;
}
```

### Class Component

**ClassController** menangani manajemen kelas.

| Method | Endpoint | Role | Deskripsi |
|--------|----------|------|-----------|
| POST | `/api/classes` | Guru | Buat kelas baru |
| GET | `/api/classes` | All | Daftar kelas (dengan search) |
| GET | `/api/classes/{id}` | Member | Detail kelas + kode |
| GET | `/api/classes/{id}/members` | Guru | Daftar anggota |
| DELETE | `/api/classes/{id}/members/{userId}` | Guru | Keluarkan murid |
| POST | `/api/classes/join` | Murid | Bergabung via kode |

**ClassService** interface:
```php
interface ClassServiceInterface {
    public function create(User $guru, array $data): Classroom;
    public function generateUniqueCode(): string;
    public function join(User $murid, string $code): Classroom;
    public function removeMember(Classroom $class, int $userId): void;
    public function search(User $user, ?string $keyword, int $page, int $perPage): LengthAwarePaginator;
}
```

### Assignment Component

**AssignmentController** menangani tugas dan soal.

| Method | Endpoint | Role | Deskripsi |
|--------|----------|------|-----------|
| POST | `/api/classes/{id}/assignments` | Guru | Buat tugas |
| GET | `/api/classes/{id}/assignments` | Member | Daftar tugas |
| GET | `/api/assignments/{id}` | Member | Detail tugas + soal |
| POST | `/api/assignments/{id}/submissions` | Murid | Kumpulkan tugas |
| GET | `/api/assignments/{id}/submissions` | Guru | Daftar submission |
| GET | `/api/assignments/{id}/submissions/{subId}` | Guru/Murid | Detail submission |

### Grade Component

**GradeController** menangani penilaian.

| Method | Endpoint | Role | Deskripsi |
|--------|----------|------|-----------|
| POST | `/api/submissions/{id}/grades` | Guru | Nilai submission |
| GET | `/api/submissions/{id}/grades` | Guru/Murid | Lihat nilai |

### Chat Component

**ChatController** menangani pesan kelas dan tugas.

| Method | Endpoint | Role | Deskripsi |
|--------|----------|------|-----------|
| POST | `/api/classes/{id}/messages` | Member | Kirim pesan kelas |
| GET | `/api/classes/{id}/messages` | Member | Riwayat chat kelas |
| POST | `/api/assignments/{id}/messages` | Member | Kirim pesan tugas |
| GET | `/api/assignments/{id}/messages` | Member | Riwayat chat tugas |

### Profile Component

**ProfileController** menangani profil pengguna.

| Method | Endpoint | Role | Deskripsi |
|--------|----------|------|-----------|
| GET | `/api/profile` | Auth | Lihat profil |
| POST | `/api/profile/avatar` | Auth | Update foto profil |

---

## Data Models

### Entity Relationship Diagram

```mermaid
erDiagram
    users {
        bigint id PK
        string name
        string email UK
        string password nullable
        string google_id nullable
        enum role "admin,guru,murid"
        string avatar_url nullable
        timestamps created_at
        timestamps updated_at
    }

    classrooms {
        bigint id PK
        string name
        string code UK "6-8 char alphanumeric"
        bigint teacher_id FK
        timestamps created_at
        timestamps updated_at
    }

    class_members {
        bigint id PK
        bigint classroom_id FK
        bigint user_id FK
        timestamps joined_at
    }

    assignments {
        bigint id PK
        bigint classroom_id FK
        string title
        text description nullable
        boolean exam_mode "default false"
        timestamp due_at nullable
        timestamps created_at
        timestamps updated_at
    }

    questions {
        bigint id PK
        bigint assignment_id FK
        text body
        enum type "pilihan_ganda,pilihan_ganda_kompleks,uraian"
        json options nullable "array of {text, is_correct}"
        decimal weight
        int order
    }

    submissions {
        bigint id PK
        bigint assignment_id FK
        bigint user_id FK
        enum status "dikumpulkan,terlambat"
        string screenshot_path nullable
        json gesture_log nullable
        decimal total_grade nullable
        timestamps submitted_at
    }

    answers {
        bigint id PK
        bigint submission_id FK
        bigint question_id FK
        json selected_options nullable
        text essay_answer nullable
    }

    grades {
        bigint id PK
        bigint submission_id FK
        bigint question_id FK
        decimal score
        text feedback nullable
        timestamps created_at
    }

    messages {
        bigint id PK
        bigint user_id FK
        bigint classroom_id nullable FK
        bigint assignment_id nullable FK
        text body
        timestamps created_at
    }

    users ||--o{ classrooms : "teaches"
    users ||--o{ class_members : "joins"
    classrooms ||--o{ class_members : "has"
    classrooms ||--o{ assignments : "has"
    assignments ||--o{ questions : "contains"
    assignments ||--o{ submissions : "receives"
    submissions ||--o{ answers : "contains"
    submissions ||--o{ grades : "has"
    questions ||--o{ grades : "graded_by"
    users ||--o{ submissions : "submits"
    users ||--o{ messages : "sends"
    classrooms ||--o{ messages : "has"
    assignments ||--o{ messages : "has"
```

### Model Detail

**User**
- `role`: enum `['admin', 'guru', 'murid']`, default `murid`
- `google_id`: nullable, diisi saat login via Google
- `password`: nullable (user Google-only tidak punya password)
- Relasi: `hasMany(Classroom)`, `belongsToMany(Classroom, class_members)`, `hasMany(Submission)`, `hasMany(Message)`

**Classroom**
- `code`: 6–8 karakter alfanumerik uppercase, unique
- Relasi: `belongsTo(User, teacher_id)`, `belongsToMany(User, class_members)`, `hasMany(Assignment)`, `hasMany(Message)`

**ClassMember** (pivot table dengan timestamps)
- `joined_at`: timestamp saat murid bergabung
- Unique constraint: `(classroom_id, user_id)`

**Assignment**
- `exam_mode`: boolean, default `false`
- `due_at`: nullable timestamp untuk batas waktu
- Relasi: `belongsTo(Classroom)`, `hasMany(Question)`, `hasMany(Submission)`, `hasMany(Message)`

**Question**
- `type`: enum `['pilihan_ganda', 'pilihan_ganda_kompleks', 'uraian']`
- `options`: JSON array `[{"text": "...", "is_correct": true/false}]`, null untuk uraian
- `weight`: decimal, bobot soal untuk perhitungan nilai
- Validasi: pilihan_ganda tepat 1 `is_correct=true`; pilihan_ganda_kompleks ≥1 `is_correct=true`

**Submission**
- `status`: enum `['dikumpulkan', 'terlambat']`
- `screenshot_path`: path relatif ke storage
- `gesture_log`: JSON, wajib diisi jika `assignment.exam_mode = true`
- `total_grade`: dihitung otomatis setelah semua soal dinilai
- Unique constraint: `(assignment_id, user_id)`

**Grade**
- `score`: decimal, tidak boleh melebihi `question.weight`
- Unique constraint: `(submission_id, question_id)`

**Message**
- Salah satu dari `classroom_id` atau `assignment_id` harus diisi (check constraint)
- Diurutkan berdasarkan `created_at ASC`

### Formula Nilai Keseluruhan

```
total_grade = SUM(grade.score × question.weight) / SUM(question.weight) × 100
```

---
