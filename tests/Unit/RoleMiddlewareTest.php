<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Http\Middleware\RoleMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private RoleMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RoleMiddleware();
    }

    /**
     * Property 1: Setiap user dengan peran X tidak dapat mengakses endpoint yang membutuhkan peran Y (X ≠ Y)
     * Validates: Requirements 2.4, 2.5
     * 
     * This property test verifies that for any user with role X, and any endpoint requiring role Y where X ≠ Y,
     * the user should receive HTTP 403.
     */
    #[Test]
    public function test_user_with_role_x_cannot_access_endpoint_requiring_role_y_when_x_not_equal_y()
    {
        // Define all possible roles
        $allRoles = [UserRole::Admin, UserRole::Guru, UserRole::Murid];
        
        // Test all combinations of user role (X) and required role (Y) where X ≠ Y
        foreach ($allRoles as $userRole) {
            foreach ($allRoles as $requiredRole) {
                if ($userRole === $requiredRole) {
                    continue; // Skip when X = Y (this is the success case)
                }
                
                // Create a user with role X
                $user = User::factory()->create(['role' => $userRole]);
                
                // Authenticate the user
                Auth::login($user);
                
                // Create a mock request
                $request = Request::create('/test-endpoint', 'GET');
                $request->setUserResolver(fn () => $user);
                
                // Define the next closure that should not be called
                $nextCalled = false;
                $next = function ($request) use (&$nextCalled) {
                    $nextCalled = true;
                    return response('Success');
                };
                
                // Act: Call middleware with required role Y
                try {
                    $response = $this->middleware->handle($request, $next, $requiredRole->value);
                    
                    // If we reach here, middleware didn't abort (which is a failure for this test)
                    $this->fail(
                        "User with role {$userRole->value} should not be able to access endpoint requiring role {$requiredRole->value}. " .
                        "Expected HTTP 403 but got status: " . ($response ? $response->getStatusCode() : 'no response')
                    );
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                    // Assert: Should throw HTTP 403 exception
                    $this->assertEquals(403, $e->getStatusCode());
                    $this->assertStringContainsString(
                        "Unauthorized. User role: {$userRole->value}. Required role(s): {$requiredRole->value}",
                        $e->getMessage()
                    );
                    $this->assertFalse($nextCalled, 'Next closure should not be called when access is denied');
                }
            }
        }
    }

    /**
     * Complementary test: User with role X CAN access endpoint requiring role X (success case)
     * This ensures the middleware allows access when roles match
     */
    #[Test]
    public function test_user_with_role_x_can_access_endpoint_requiring_same_role_x()
    {
        // Define all possible roles
        $allRoles = [UserRole::Admin, UserRole::Guru, UserRole::Murid];
        
        foreach ($allRoles as $role) {
            // Create a user with role X
            $user = User::factory()->create(['role' => $role]);
            
            // Authenticate the user
            Auth::login($user);
            
            // Create a mock request
            $request = Request::create('/test-endpoint', 'GET');
            $request->setUserResolver(fn () => $user);
            
            // Define the next closure that should be called
            $nextCalled = false;
            $nextResponse = response('Success', 200);
            $next = function ($request) use (&$nextCalled, $nextResponse) {
                $nextCalled = true;
                return $nextResponse;
            };
            
            // Act: Call middleware with required role X
            $response = $this->middleware->handle($request, $next, $role->value);
            
            // Assert: Next closure was called and response is returned
            $this->assertTrue($nextCalled, 'Next closure should be called when access is granted');
            $this->assertSame($nextResponse, $response);
        }
    }

    /**
     * Test that middleware handles multiple required roles correctly
     * User should have access if they have at least one of the required roles
     */
    #[Test]
    public function test_user_can_access_endpoint_when_they_have_at_least_one_of_multiple_required_roles()
    {
        // Test cases: user has one of the required roles
        $testCases = [
            [UserRole::Admin, [UserRole::Admin->value, UserRole::Guru->value]], // Admin can access Admin OR Guru endpoint
            [UserRole::Guru, [UserRole::Guru->value, UserRole::Murid->value]], // Guru can access Guru OR Murid endpoint
            [UserRole::Murid, [UserRole::Murid->value, UserRole::Admin->value]], // Murid can access Murid OR Admin endpoint
        ];
        
        foreach ($testCases as [$userRole, $requiredRoles]) {
            // Create a user with specific role
            $user = User::factory()->create(['role' => $userRole]);
            
            // Authenticate the user
            Auth::login($user);
            
            // Create a mock request
            $request = Request::create('/test-endpoint', 'GET');
            $request->setUserResolver(fn () => $user);
            
            // Define the next closure that should be called
            $nextCalled = false;
            $nextResponse = response('Success', 200);
            $next = function ($request) use (&$nextCalled, $nextResponse) {
                $nextCalled = true;
                return $nextResponse;
            };
            
            // Act: Call middleware with multiple required roles
            $response = $this->middleware->handle($request, $next, ...$requiredRoles);
            
            // Assert: Next closure was called (access granted)
            $this->assertTrue($nextCalled, "User with role {$userRole->value} should be able to access endpoint requiring roles: " . implode(', ', $requiredRoles));
            $this->assertSame($nextResponse, $response);
        }
    }

    /**
     * Test that middleware denies access when user has none of the required roles
     */
    #[Test]
    public function test_user_cannot_access_endpoint_when_they_have_none_of_multiple_required_roles()
    {
        // Test cases: user doesn't have any of the required roles
        $testCases = [
            [UserRole::Murid, [UserRole::Admin->value, UserRole::Guru->value]], // Murid cannot access Admin OR Guru endpoint
            [UserRole::Guru, [UserRole::Admin->value]], // Guru cannot access Admin endpoint
            [UserRole::Admin, [UserRole::Guru->value, UserRole::Murid->value]], // Admin cannot access Guru OR Murid endpoint
        ];
        
        foreach ($testCases as [$userRole, $requiredRoles]) {
            // Create a user with specific role
            $user = User::factory()->create(['role' => $userRole]);
            
            // Authenticate the user
            Auth::login($user);
            
            // Create a mock request
            $request = Request::create('/test-endpoint', 'GET');
            $request->setUserResolver(fn () => $user);
            
            // Define the next closure that should not be called
            $nextCalled = false;
            $next = function ($request) use (&$nextCalled) {
                $nextCalled = true;
                return response('Success');
            };
            
            // Act: Call middleware with required roles that user doesn't have
            try {
                $response = $this->middleware->handle($request, $next, ...$requiredRoles);
                
                // If we reach here, middleware didn't abort (which is a failure for this test)
                $this->fail(
                    "User with role {$userRole->value} should not be able to access endpoint requiring roles: " . 
                    implode(', ', $requiredRoles) . ". Expected HTTP 403."
                );
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                // Assert: Should throw HTTP 403 exception
                $this->assertEquals(403, $e->getStatusCode());
                $this->assertStringContainsString(
                    "Unauthorized. User role: {$userRole->value}. Required role(s): " . implode(', ', $requiredRoles),
                    $e->getMessage()
                );
                $this->assertFalse($nextCalled, 'Next closure should not be called when access is denied');
            }
        }
    }

    /**
     * Test that unauthenticated users receive HTTP 401
     */
    #[Test]
    public function test_unauthenticated_user_receives_http_401()
    {
        // Create a mock request without authenticated user
        $request = Request::create('/test-endpoint', 'GET');
        $request->setUserResolver(fn () => null); // No user authenticated
        
        // Define the next closure that should not be called
        $nextCalled = false;
        $next = function ($request) use (&$nextCalled) {
            $nextCalled = true;
            return response('Success');
        };
        
        // Act: Call middleware
        try {
            $this->middleware->handle($request, $next, UserRole::Admin->value);
            $this->fail('Unauthenticated user should receive HTTP 401');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Assert: Should throw HTTP 401 exception
            $this->assertEquals(401, $e->getStatusCode());
            $this->assertEquals('Unauthenticated.', $e->getMessage());
            $this->assertFalse($nextCalled, 'Next closure should not be called for unauthenticated user');
        }
    }

    /**
     * Test that invalid role parameter results in HTTP 403 with descriptive message
     */
    #[Test]
    public function test_invalid_role_parameter_returns_http_403_with_descriptive_message()
    {
        // Create a user
        $user = User::factory()->create(['role' => UserRole::Admin]);
        
        // Authenticate the user
        Auth::login($user);
        
        // Create a mock request
        $request = Request::create('/test-endpoint', 'GET');
        $request->setUserResolver(fn () => $user);
        
        // Define the next closure that should not be called
        $nextCalled = false;
        $next = function ($request) use (&$nextCalled) {
            $nextCalled = true;
            return response('Success');
        };
        
        // Act: Call middleware with invalid role
        try {
            $this->middleware->handle($request, $next, 'invalid_role');
            $this->fail('Invalid role parameter should result in HTTP 403');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Assert: Should throw HTTP 403 exception with descriptive message
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertStringContainsString('Invalid role specified: invalid_role', $e->getMessage());
            $this->assertStringContainsString('Valid roles are: admin, guru, murid', $e->getMessage());
            $this->assertFalse($nextCalled, 'Next closure should not be called for invalid role');
        }
    }
}