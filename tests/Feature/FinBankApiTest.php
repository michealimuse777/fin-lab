<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinBankApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->clearSecurityLog();
    }

    public function test_seeded_users_can_login_and_receive_token(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'michael@finbank.test',
            'password' => 'FinBankLab123!',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Login successful');
        $response->assertJsonStructure(['token']);

        $this->assertStringContainsString('login_success', $this->securityLogContents());
    }

    public function test_exactly_eight_lab_users_are_seeded(): void
    {
        $this->assertSame(8, User::count());
        $this->assertSame(5, User::where('role', 'user')->count());
        $this->assertSame(3, User::where('role', 'admin')->count());
    }

    public function test_normal_user_cannot_access_admin_endpoint(): void
    {
        $headers = $this->loginHeaders('michael@finbank.test');

        $response = $this->getJson('/api/admin/users', $headers);

        $response->assertForbidden();
        $this->assertStringContainsString('admin_access_attempt', $this->securityLogContents());
    }

    public function test_user_cannot_view_another_users_transaction(): void
    {
        $transaction = Transaction::create([
            'sender_id' => 1,
            'recipient_id' => 2,
            'amount' => 50000,
            'currency' => 'NGN',
            'status' => 'completed',
            'description' => 'Lab transfer',
        ]);

        $headers = $this->loginHeaders('grace@finbank.test');

        $response = $this->getJson('/api/transactions/'.$transaction->id, $headers);

        $response->assertForbidden();
        $this->assertStringContainsString('idor_attempt', $this->securityLogContents());
    }

    public function test_profile_update_does_not_change_role_or_status(): void
    {
        $headers = $this->loginHeaders('michael@finbank.test');

        $response = $this->putJson('/api/profile', [
            'name' => 'Michael Updated',
            'email' => 'michael.updated@finbank.test',
            'role' => 'admin',
            'is_active' => false,
        ], $headers);

        $response->assertOk();

        $user = User::find(1);

        $this->assertSame('Michael Updated', $user->name);
        $this->assertSame('michael.updated@finbank.test', $user->email);
        $this->assertSame('user', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertStringContainsString('mass_assignment_attempt', $this->securityLogContents());
        $this->assertStringContainsString('profile_updated', $this->securityLogContents());
    }

    public function test_large_transaction_writes_security_log_event(): void
    {
        $headers = $this->loginHeaders('michael@finbank.test');

        $response = $this->postJson('/api/transactions', [
            'recipient_id' => 2,
            'amount' => 1500000,
            'currency' => 'NGN',
            'description' => 'Large lab transfer',
        ], $headers);

        $response->assertCreated();

        $logContents = $this->securityLogContents();

        $this->assertStringContainsString('transaction_created', $logContents);
        $this->assertStringContainsString('large_transaction_detected', $logContents);
    }

    public function test_admin_can_change_role_and_account_status(): void
    {
        $headers = $this->loginHeaders('admin1@finbank.test');

        $roleResponse = $this->putJson('/api/admin/users/1/role', [
            'role' => 'admin',
        ], $headers);

        $statusResponse = $this->putJson('/api/admin/users/1/status', [
            'is_active' => false,
        ], $headers);

        $roleResponse->assertOk();
        $statusResponse->assertOk();

        $user = User::find(1);

        $this->assertSame('admin', $user->role);
        $this->assertFalse($user->is_active);

        $logContents = $this->securityLogContents();

        $this->assertStringContainsString('role_changed', $logContents);
        $this->assertStringContainsString('account_status_changed', $logContents);
    }

    public function test_invalid_transaction_input_is_logged(): void
    {
        $headers = $this->loginHeaders('michael@finbank.test');

        $response = $this->postJson('/api/transactions', [
            'recipient_id' => 2,
            'amount' => -10,
            'currency' => 'USD',
        ], $headers);

        $response->assertUnprocessable();
        $this->assertStringContainsString('validation_failed', $this->securityLogContents());
    }

    public function test_api_404_is_logged_as_endpoint_enumeration(): void
    {
        $response = $this->getJson('/api/debug');

        $response->assertNotFound();
        $this->assertStringContainsString('endpoint_enumeration', $this->securityLogContents());
    }

    public function test_sensitive_file_probe_is_logged(): void
    {
        $response = $this->get('/.env', [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(404);
        $this->assertStringContainsString('sensitive_file_probe', $this->securityLogContents());
    }

    private function loginHeaders(string $email): array
    {
        $response = $this->postJson('/api/login', [
            'email' => $email,
            'password' => 'FinBankLab123!',
        ]);

        $token = $response->json('token');

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }

    private function clearSecurityLog(): void
    {
        $path = storage_path('logs/security.log');

        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function securityLogContents(): string
    {
        $path = storage_path('logs/security.log');

        if (! file_exists($path)) {
            return '';
        }

        return file_get_contents($path);
    }
}
