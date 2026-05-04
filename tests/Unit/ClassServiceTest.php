<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\ClassMember;
use App\Models\Classroom;
use App\Models\User;
use App\Services\ClassService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClassServiceTest extends TestCase
{
    use RefreshDatabase;

    private ClassService $classService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classService = new ClassService();
    }

    #[Test]
    public function test_create_classroom_success_for_guru()
    {
        // Arrange: Create a Guru user
        $guru = User::factory()->create(['role' => UserRole::Guru]);

        $data = ['name' => 'Mathematics Class'];

        // Act: Create classroom
        $classroom = $this->classService->create($guru, $data);

        // Assert: Classroom is created with correct attributes
        $this->assertInstanceOf(Classroom::class, $classroom);
        $this->assertEquals('Mathematics Class', $classroom->name);
        $this->assertEquals($guru->id, $classroom->teacher_id);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6,8}$/', $classroom->code);
        $this->assertDatabaseHas('classrooms', [
            'id' => $classroom->id,
            'name' => 'Mathematics Class',
            'teacher_id' => $guru->id,
        ]);
    }

    #[Test]
    public function test_create_classroom_fails_for_non_guru()
    {
        // Arrange: Create a Murid user
        $murid = User::factory()->create(['role' => UserRole::Murid]);

        $data = ['name' => 'Mathematics Class'];

        // Act & Assert: Should throw ValidationException
        $this->expectException(ValidationException::class);

        $this->classService->create($murid, $data);
    }

    #[Test]
    public function test_generate_unique_code_creates_unique_codes()
    {
        // Arrange: Create some classrooms with codes
        $codes = [];
        $maxAttempts = 20;

        // Act: Generate multiple codes
        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = $this->classService->generateUniqueCode();
            $codes[] = $code;
            
            // Create a classroom with this code to simulate existing codes
            Classroom::factory()->create(['code' => $code]);
        }

        // Assert: All codes are unique
        $uniqueCodes = array_unique($codes);
        $this->assertCount(count($codes), $uniqueCodes, 'All generated codes should be unique');
        
        // Assert: All codes match the required format
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{6,8}$/', $code);
        }
    }

    #[Test]
    public function test_join_classroom_success_for_murid()
    {
        // Arrange: Create a Murid and a Classroom
        $murid = User::factory()->create(['role' => UserRole::Murid]);
        $classroom = Classroom::factory()->create(['code' => 'ABCD123']);

        // Act: Join classroom
        $joinedClassroom = $this->classService->join($murid, 'ABCD123');

        // Assert: Classroom is returned and member is created
        $this->assertInstanceOf(Classroom::class, $joinedClassroom);
        $this->assertEquals($classroom->id, $joinedClassroom->id);
        $this->assertDatabaseHas('class_members', [
            'classroom_id' => $classroom->id,
            'user_id' => $murid->id,
        ]);
        
        // Check that joined_at is set
        $member = ClassMember::where('classroom_id', $classroom->id)
            ->where('user_id', $murid->id)
            ->first();
        $this->assertNotNull($member->joined_at);
    }

    #[Test]
    public function test_join_classroom_fails_for_non_murid()
    {
        // Arrange: Create a Guru and a Classroom
        $guru = User::factory()->create(['role' => UserRole::Guru]);
        Classroom::factory()->create(['code' => 'ABCD123']);

        // Act & Assert: Should throw ValidationException
        $this->expectException(ValidationException::class);

        $this->classService->join($guru, 'ABCD123');
    }

    #[Test]
    public function test_join_classroom_fails_with_invalid_code_format()
    {
        // Arrange: Create a Murid
        $murid = User::factory()->create(['role' => UserRole::Murid]);

        // Act & Assert: Should throw ValidationException
        $this->expectException(ValidationException::class);

        $this->classService->join($murid, 'invalid-code');
    }

    #[Test]
    public function test_join_classroom_fails_with_nonexistent_code()
    {
        // Arrange: Create a Murid
        $murid = User::factory()->create(['role' => UserRole::Murid]);

        // Act & Assert: Should throw ValidationException
        $this->expectException(ValidationException::class);

        $this->classService->join($murid, 'NONEXIST');
    }

    #[Test]
    public function test_join_classroom_fails_when_already_member()
    {
        // Arrange: Create a Murid and a Classroom, and make Murid a member
        $murid = User::factory()->create(['role' => UserRole::Murid]);
        $classroom = Classroom::factory()->create(['code' => 'ABCD123']);
        ClassMember::create([
            'classroom_id' => $classroom->id,
            'user_id' => $murid->id,
            'joined_at' => now(),
        ]);

        // Act & Assert: Should throw ValidationException
        $this->expectException(ValidationException::class);

        $this->classService->join($murid, 'ABCD123');
    }

    #[Test]
    public function test_remove_member_success()
    {
        // Arrange: Create a Classroom and a member
        $classroom = Classroom::factory()->create();
        $member = User::factory()->create(['role' => UserRole::Murid]);
        ClassMember::create([
            'classroom_id' => $classroom->id,
            'user_id' => $member->id,
            'joined_at' => now(),
        ]);

        // Act: Remove member
        $this->classService->removeMember($classroom, $member->id);

        // Assert: Member is removed
        $this->assertDatabaseMissing('class_members', [
            'classroom_id' => $classroom->id,
            'user_id' => $member->id,
        ]);
    }

    #[Test]
    public function test_remove_member_fails_when_not_member()
    {
        // Arrange: Create a Classroom and a non-member user
        $classroom = Classroom::factory()->create();
        $nonMember = User::factory()->create(['role' => UserRole::Murid]);

        // Act & Assert: Should throw ValidationException
        $this->expectException(ValidationException::class);

        $this->classService->removeMember($classroom, $nonMember->id);
    }

    #[Test]
    public function test_search_classrooms_for_guru_shows_only_their_classes()
    {
        // Arrange: Create a Guru and some classrooms
        $guru = User::factory()->create(['role' => UserRole::Guru]);
        $otherGuru = User::factory()->create(['role' => UserRole::Guru]);
        
        // Create 3 classrooms for the Guru
        $classrooms = Classroom::factory()->count(3)->create(['teacher_id' => $guru->id]);
        // Create 2 classrooms for another Guru
        Classroom::factory()->count(2)->create(['teacher_id' => $otherGuru->id]);

        // Act: Search classrooms for the Guru
        $result = $this->classService->search($guru, null, 1, 10);

        // Assert: Only shows the Guru's classrooms
        $this->assertCount(3, $result->items());
        foreach ($result->items() as $classroom) {
            $this->assertEquals($guru->id, $classroom->teacher_id);
        }
    }

    #[Test]
    public function test_search_classrooms_for_murid_shows_only_their_enrolled_classes()
    {
        // Arrange: Create a Murid and some classrooms
        $murid = User::factory()->create(['role' => UserRole::Murid]);
        
        // Create 2 classrooms the Murid is enrolled in
        $enrolledClassrooms = Classroom::factory()->count(2)->create();
        foreach ($enrolledClassrooms as $classroom) {
            ClassMember::create([
                'classroom_id' => $classroom->id,
                'user_id' => $murid->id,
                'joined_at' => now(),
            ]);
        }
        
        // Create 3 classrooms the Murid is NOT enrolled in
        Classroom::factory()->count(3)->create();

        // Act: Search classrooms for the Murid
        $result = $this->classService->search($murid, null, 1, 10);

        // Assert: Only shows enrolled classrooms
        $this->assertCount(2, $result->items());
        foreach ($result->items() as $classroom) {
            $this->assertTrue($classroom->members()->where('user_id', $murid->id)->exists());
        }
    }

    #[Test]
    public function test_search_classrooms_with_keyword_case_insensitive()
    {
        // Arrange: Create an Admin user and classrooms with different names/codes
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        
        // Create a teacher for all classrooms to avoid creating new teachers
        $teacher = User::factory()->create(['role' => UserRole::Guru]);
        
        Classroom::factory()->forTeacher($teacher)->create(['name' => 'Mathematics Advanced', 'code' => 'MATH101']);
        Classroom::factory()->forTeacher($teacher)->create(['name' => 'Physics Basic', 'code' => 'PHYS101']);
        Classroom::factory()->forTeacher($teacher)->create(['name' => 'Advanced Chemistry', 'code' => 'CHEM101']);
        Classroom::factory()->forTeacher($teacher)->create(['name' => 'Biology', 'code' => 'BIO101']);

        // Act: Search with keyword "math" (lowercase)
        $result = $this->classService->search($admin, 'math', 1, 10);

        // Assert: Should find "Mathematics Advanced" and "MATH101"
        $this->assertCount(2, $result->items());
        
        $foundNames = array_map(fn($c) => $c->name, $result->items());
        $foundCodes = array_map(fn($c) => $c->code, $result->items());
        
        $this->assertContains('Mathematics Advanced', $foundNames);
        $this->assertContains('MATH101', $foundCodes);
    }

    #[Test]
    public function test_search_classrooms_with_empty_keyword_returns_all_for_admin()
    {
        // Arrange: Create an Admin user and some classrooms
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $totalClassrooms = 5;
        Classroom::factory()->count($totalClassrooms)->create();

        // Act: Search without keyword
        $result = $this->classService->search($admin, null, 1, 10);

        // Assert: Returns all classrooms
        $this->assertCount($totalClassrooms, $result->items());
    }

    #[Test]
    public function test_search_classrooms_pagination_works()
    {
        // Arrange: Create an Admin user and 15 classrooms
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Classroom::factory()->count(15)->create();

        // Act: Search with pagination (page 1, 5 per page)
        $result = $this->classService->search($admin, null, 1, 5);

        // Assert: Pagination metadata is correct
        $this->assertCount(5, $result->items());
        $this->assertEquals(1, $result->currentPage());
        $this->assertEquals(5, $result->perPage());
        $this->assertEquals(15, $result->total());
        $this->assertEquals(3, $result->lastPage());
    }

    #[Test]
    public function test_get_members_returns_members_with_user_info()
    {
        // Arrange: Create a Classroom and some members
        $classroom = Classroom::factory()->create();
        $members = User::factory()->count(3)->create(['role' => UserRole::Murid]);
        
        foreach ($members as $member) {
            ClassMember::create([
                'classroom_id' => $classroom->id,
                'user_id' => $member->id,
                'joined_at' => now(),
            ]);
        }

        // Act: Get members
        $result = $this->classService->getMembers($classroom);

        // Assert: Returns all members with user info
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertCount(3, $result);
        
        foreach ($result as $memberData) {
            $this->assertArrayHasKey('id', $memberData);
            $this->assertArrayHasKey('name', $memberData);
            $this->assertArrayHasKey('avatar_url', $memberData);
            $this->assertArrayHasKey('joined_at', $memberData);
            
            // Verify the user exists
            $user = User::find($memberData['id']);
            $this->assertNotNull($user);
            $this->assertEquals($user->name, $memberData['name']);
            $this->assertEquals($user->avatar_url, $memberData['avatar_url']);
        }
    }

    #[Test]
    public function test_get_details_returns_classroom_info_with_code()
    {
        // Arrange: Create a Classroom and a Guru who teaches it
        $guru = User::factory()->create(['role' => UserRole::Guru]);
        $classroom = Classroom::factory()->create([
            'teacher_id' => $guru->id,
            'code' => 'TEST123',
        ]);

        // Add some members
        $members = User::factory()->count(2)->create(['role' => UserRole::Murid]);
        foreach ($members as $member) {
            ClassMember::create([
                'classroom_id' => $classroom->id,
                'user_id' => $member->id,
                'joined_at' => now(),
            ]);
        }

        // Act: Get details as the Guru
        $result = $this->classService->getDetails($classroom, $guru);

        // Assert: Returns correct details
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('code', $result);
        $this->assertArrayHasKey('teacher_id', $result);
        $this->assertArrayHasKey('teacher_name', $result);
        $this->assertArrayHasKey('member_count', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('updated_at', $result);
        
        $this->assertEquals($classroom->id, $result['id']);
        $this->assertEquals($classroom->name, $result['name']);
        $this->assertEquals('TEST123', $result['code']);
        $this->assertEquals($guru->id, $result['teacher_id']);
        $this->assertEquals($guru->name, $result['teacher_name']);
        $this->assertEquals(2, $result['member_count']);
    }

    #[Test]
    public function test_get_details_fails_for_unauthorized_murid()
    {
        // Arrange: Create a Classroom and a Murid who is NOT a member
        $murid = User::factory()->create(['role' => UserRole::Murid]);
        $classroom = Classroom::factory()->create();

        // Act & Assert: Should throw ValidationException
        $this->expectException(ValidationException::class);

        $this->classService->getDetails($classroom, $murid);
    }

    #[Test]
    public function test_get_details_succeeds_for_member_murid()
    {
        // Arrange: Create a Classroom and a Murid who IS a member
        $murid = User::factory()->create(['role' => UserRole::Murid]);
        $classroom = Classroom::factory()->create();
        
        ClassMember::create([
            'classroom_id' => $classroom->id,
            'user_id' => $murid->id,
            'joined_at' => now(),
        ]);

        // Act: Get details as the member Murid
        $result = $this->classService->getDetails($classroom, $murid);

        // Assert: Returns details successfully
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('code', $result);
        $this->assertEquals($classroom->id, $result['id']);
        $this->assertEquals($classroom->code, $result['code']);
    }

    #[Test]
    public function test_get_details_succeeds_for_admin()
    {
        // Arrange: Create a Classroom and an Admin
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $classroom = Classroom::factory()->create();

        // Act: Get details as Admin
        $result = $this->classService->getDetails($classroom, $admin);

        // Assert: Returns details successfully
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('code', $result);
        $this->assertEquals($classroom->id, $result['id']);
        $this->assertEquals($classroom->code, $result['code']);
    }
}