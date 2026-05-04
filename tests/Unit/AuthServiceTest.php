<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = new AuthService();
    }

    #[Test]
    public function test_login_success_returns_token()
    {
        // Arrange: Create a user with email and password
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role' => UserRole::Murid,
        ]);

        // Act: Login with correct credentials
        $result = $this->authService->loginWithCredentials('test@example.com', 'password123');

        // Assert: Token is returned and user matches
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertEquals($user->id, $result['user']->id);
        $this->assertIsString($result['token']);
        $this->assertNotEmpty($result['token']);
    }

    #[Test]
    public function test_login_with_wrong_credentials_returns_http_401()
    {
        // Arrange: Create a user
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Act & Assert: Login with wrong password should throw ValidationException
        $this->expectException(ValidationException::class);
        
        $this->authService->loginWithCredentials('test@example.com', 'wrongpassword');
    }

    #[Test]
    public function test_login_with_nonexistent_email_returns_http_401()
    {
        // Act & Assert: Login with non-existent email should throw ValidationException
        $this->expectException(ValidationException::class);
        
        $this->authService->loginWithCredentials('nonexistent@example.com', 'password123');
    }

    #[Test]
    public function test_login_with_google_only_user_returns_http_401()
    {
        // Arrange: Create a Google-only user (no password)
        User::factory()->create([
            'email' => 'google@example.com',
            'password' => null,
            'google_id' => 'google123',
        ]);

        // Act & Assert: Login with password should throw ValidationException
        $this->expectException(ValidationException::class);
        
        $this->authService->loginWithCredentials('google@example.com', 'password123');
    }

    #[Test]
    public function test_google_oauth_with_invalid_token_returns_http_401()
    {
        // Arrange: Mock Socialite driver to throw exception
        $socialiteMock = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $socialiteMock->shouldReceive('userFromToken')
            ->with('invalid_token')
            ->andThrow(new \Exception('Invalid token'));
        
        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($socialiteMock);

        // Act & Assert: Invalid Google token should throw ValidationException
        $this->expectException(ValidationException::class);
        
        $this->authService->loginWithGoogle('invalid_token');
    }

    #[Test]
    public function test_google_oauth_with_valid_token_creates_or_logs_in_user()
    {
        // Arrange: Mock Socialite to return a valid Google user
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google123');
        $googleUser->shouldReceive('getEmail')->andReturn('google@example.com');
        $googleUser->shouldReceive('getName')->andReturn('Google User');
        $googleUser->shouldReceive('getAvatar')->andReturn('https://avatar.url');
        
        $socialiteMock = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $socialiteMock->shouldReceive('userFromToken')
            ->with('valid_token')
            ->andReturn($googleUser);
        
        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($socialiteMock);

        // Act: Login with valid Google token
        $result = $this->authService->loginWithGoogle('valid_token');

        // Assert: User is created and token is returned
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertEquals('google@example.com', $result['user']->email);
        $this->assertEquals('google123', $result['user']->google_id);
        $this->assertEquals(UserRole::Murid, $result['user']->role);
        $this->assertIsString($result['token']);
        $this->assertNotEmpty($result['token']);
    }

    #[Test]
    public function test_google_oauth_with_existing_user_by_google_id_logs_in()
    {
        // Arrange: Create existing user with google_id
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'google_id' => 'google123',
            'role' => UserRole::Murid,
        ]);

        // Mock Socialite to return same Google user
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google123');
        $googleUser->shouldReceive('getEmail')->andReturn('existing@example.com');
        $googleUser->shouldReceive('getName')->andReturn('Existing User');
        $googleUser->shouldReceive('getAvatar')->andReturn('https://avatar.url');
        
        $socialiteMock = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $socialiteMock->shouldReceive('userFromToken')
            ->with('valid_token')
            ->andReturn($googleUser);
        
        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($socialiteMock);

        // Act: Login with Google token
        $result = $this->authService->loginWithGoogle('valid_token');

        // Assert: Existing user is logged in (same ID)
        $this->assertEquals($existingUser->id, $result['user']->id);
        $this->assertArrayHasKey('token', $result);
        $this->assertNotEmpty($result['token']);
    }

    #[Test]
    public function test_logout_revokes_token()
    {
        // Arrange: Create a mock token that expects delete() to be called
        $mockToken = Mockery::mock();
        $mockToken->shouldReceive('delete')->once();
        
        // Create a mock user that returns the mock token from currentAccessToken()
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('currentAccessToken')->andReturn($mockToken);

        // Act: Logout
        $this->authService->logout($user);

        // Assert: delete() was called on the token (verified by Mockery)
    }

    #[Test]
    public function test_register_creates_user_with_default_murid_role()
    {
        // Arrange: Registration data
        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        // Act: Register new user
        $result = $this->authService->register($data);

        // Assert: User is created with Murid role and token is returned
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertEquals('Test User', $result['user']->name);
        $this->assertEquals('test@example.com', $result['user']->email);
        $this->assertEquals(UserRole::Murid, $result['user']->role);
        $this->assertTrue(Hash::check('password123', $result['user']->password));
        $this->assertIsString($result['token']);
        $this->assertNotEmpty($result['token']);
    }

    protected function tearDown(): void
    {
        // Clear Socialite facade mocks
        Socialite::clearResolvedInstances();
        Socialite::swap(new \Laravel\Socialite\SocialiteManager(app()));
        
        Mockery::close();
        parent::tearDown();
    }
}