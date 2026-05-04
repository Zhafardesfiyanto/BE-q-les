<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ClassMember;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClassService
{
    /**
     * Create a new classroom.
     *
     * @param User $guru
     * @param array $data
     * @return Classroom
     * @throws ValidationException
     */
    public function create(User $guru, array $data): Classroom
    {
        // Validate that user is a Guru
        if ($guru->role !== UserRole::Guru) {
            throw ValidationException::withMessages([
                'role' => ['Only Guru can create classrooms.'],
            ])->status(403);
        }

        // Generate unique code
        $code = $this->generateUniqueCode();

        // Create classroom
        $classroom = Classroom::create([
            'name' => $data['name'],
            'code' => $code,
            'teacher_id' => $guru->id,
        ]);

        return $classroom;
    }

    /**
     * Generate a unique 6-8 character alphanumeric uppercase code.
     *
     * @return string
     */
    public function generateUniqueCode(): string
    {
        $maxAttempts = 100;
        $attempt = 0;

        do {
            $length = random_int(6, 8);
            $code = Str::upper(Str::random($length));
            $attempt++;

            // Check if code already exists
            $exists = Classroom::where('code', $code)->exists();
        } while ($exists && $attempt < $maxAttempts);

        if ($attempt >= $maxAttempts) {
            throw new \RuntimeException('Failed to generate unique classroom code after ' . $maxAttempts . ' attempts.');
        }

        return $code;
    }

    /**
     * Join a classroom using code.
     *
     * @param User $murid
     * @param string $code
     * @return Classroom
     * @throws ValidationException
     */
    public function join(User $murid, string $code): Classroom
    {
        // Validate that user is a Murid
        if ($murid->role !== UserRole::Murid) {
            throw ValidationException::withMessages([
                'role' => ['Only Murid can join classrooms.'],
            ])->status(403);
        }

        // Validate code format (6-8 alphanumeric uppercase)
        if (!preg_match('/^[A-Z0-9]{6,8}$/', $code)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid classroom code format. Code must be 6-8 alphanumeric uppercase characters.'],
            ])->status(422);
        }

        // Find classroom by code
        $classroom = Classroom::where('code', $code)->first();

        if (!$classroom) {
            throw ValidationException::withMessages([
                'code' => ['Classroom code not found.'],
            ])->status(404);
        }

        // Check if user is already a member
        $existingMember = ClassMember::where('classroom_id', $classroom->id)
            ->where('user_id', $murid->id)
            ->exists();

        if ($existingMember) {
            throw ValidationException::withMessages([
                'code' => ['You are already a member of this classroom.'],
            ])->status(409);
        }

        // Add user as member
        ClassMember::create([
            'classroom_id' => $classroom->id,
            'user_id' => $murid->id,
            'joined_at' => now(),
        ]);

        return $classroom;
    }

    /**
     * Remove a member from classroom.
     *
     * @param Classroom $classroom
     * @param int $userId
     * @return void
     * @throws ValidationException
     */
    public function removeMember(Classroom $classroom, int $userId): void
    {
        // Find the member
        $member = ClassMember::where('classroom_id', $classroom->id)
            ->where('user_id', $userId)
            ->first();

        if (!$member) {
            throw ValidationException::withMessages([
                'user_id' => ['User is not a member of this classroom.'],
            ])->status(404);
        }

        // Delete the member
        $member->delete();
    }

    /**
     * Search classrooms with pagination.
     *
     * @param User $user
     * @param string|null $keyword
     * @param int $page
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function search(User $user, ?string $keyword, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $query = Classroom::query();

        // Apply keyword search (case-insensitive)
        if ($keyword) {
            $lowerKeyword = strtolower($keyword);
            $query->where(function ($q) use ($lowerKeyword) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$lowerKeyword}%"])
                  ->orWhereRaw('LOWER(code) LIKE ?', ["%{$lowerKeyword}%"]);
            });
        }

        // For Guru: show only classrooms they teach
        if ($user->role === UserRole::Guru) {
            $query->where('teacher_id', $user->id);
        }
        // For Murid: show only classrooms they are members of
        elseif ($user->role === UserRole::Murid) {
            $query->whereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }
        // For Admin: show all classrooms

        // Order by creation date (newest first)
        $query->orderBy('created_at', 'desc');

        // Paginate results
        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get classroom members.
     *
     * @param Classroom $classroom
     * @return \Illuminate\Support\Collection
     */
    public function getMembers(Classroom $classroom): \Illuminate\Support\Collection
    {
        return $classroom->members()
            ->select('users.id', 'users.name', 'users.avatar_url', 'class_members.joined_at')
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'avatar_url' => $member->avatar_url,
                    'joined_at' => $member->joined_at,
                ];
            });
    }

    /**
     * Get classroom details with code.
     *
     * @param Classroom $classroom
     * @param User $user
     * @return array
     * @throws ValidationException
     */
    public function getDetails(Classroom $classroom, User $user): array
    {
        // Check if user has access to this classroom
        $hasAccess = false;

        if ($user->role === UserRole::Guru && $classroom->teacher_id === $user->id) {
            $hasAccess = true;
        } elseif ($user->role === UserRole::Murid) {
            $hasAccess = $classroom->members()->where('user_id', $user->id)->exists();
        } elseif ($user->role === UserRole::Admin) {
            $hasAccess = true;
        }

        if (!$hasAccess) {
            throw ValidationException::withMessages([
                'classroom' => ['You do not have access to this classroom.'],
            ])->status(403);
        }

        // Get member count
        $memberCount = $classroom->members()->count();

        return [
            'id' => $classroom->id,
            'name' => $classroom->name,
            'code' => $classroom->code,
            'teacher_id' => $classroom->teacher_id,
            'teacher_name' => $classroom->teacher->name,
            'member_count' => $memberCount,
            'created_at' => $classroom->created_at,
            'updated_at' => $classroom->updated_at,
        ];
    }
}