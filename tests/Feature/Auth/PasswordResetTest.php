<?php

namespace Tests\Feature\Auth;

use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function requestCode(string $email = 'budi@example.com')
    {
        return $this->postJson('/lupa-sandi/kode', ['email' => $email]);
    }

    private function lastCode(): string
    {
        return Mail::sent(PasswordResetCodeMail::class)->last()->code;
    }

    private function verify(string $code, string $email = 'budi@example.com')
    {
        return $this->postJson('/lupa-sandi/verifikasi', ['email' => $email, 'code' => $code]);
    }

    private function verifiedUser(array $attrs = []): User
    {
        $user = User::factory()->create(array_merge(['email' => 'budi@example.com'], $attrs));
        $this->requestCode();
        $this->verify($this->lastCode())->assertOk();

        return $user;
    }

    private function wrongCode(string $real): string
    {
        return $real === '000000' ? '111111' : '000000';
    }

    public function test_page_renders_with_six_boxes_and_login_links_to_it(): void
    {
        $html = $this->get('/lupa-sandi')->assertOk()->assertSee('noindex, nofollow', false)->getContent();

        $this->assertSame(6, substr_count($html, 'aria-label="Digit'));
        $this->assertStringNotContainsString('Mode demo', $html);
        $this->get('/login')->assertSee(route('password.request'));
    }

    public function test_logged_in_users_are_redirected_away(): void
    {
        $this->actingAs(User::factory()->create())->get('/lupa-sandi')->assertRedirect();
    }

    public function test_code_is_emailed_to_a_registered_user_and_stored_hashed(): void
    {
        $user = User::factory()->create(['email' => 'budi@example.com']);

        $this->requestCode()->assertOk()->assertExactJson(['expires_in' => 300, 'resend_in' => 30]);

        Mail::assertSent(PasswordResetCodeMail::class, fn ($m) => $m->hasTo($user->email));
        $code = $this->lastCode();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'budi@example.com']);
        $this->assertDatabaseMissing('password_reset_tokens', ['token' => $code]);
    }

    public function test_email_is_normalized_to_lowercase(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);

        $this->requestCode('BUDI@Example.COM')->assertOk();

        Mail::assertSent(PasswordResetCodeMail::class, 1);
    }

    public function test_unknown_email_gets_identical_response_and_no_mail(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);

        $known = $this->requestCode('budi@example.com');
        $unknown = $this->requestCode('tidakada@example.com');

        $this->assertSame($known->status(), $unknown->status());
        $this->assertSame($known->json(), $unknown->json());
        Mail::assertSent(PasswordResetCodeMail::class, 1);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'tidakada@example.com']);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->requestCode('bukan-email')->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_resend_cooldown_applies_equally_to_known_and_unknown_emails(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);

        foreach (['budi@example.com', 'tidakada@example.com'] as $email) {
            $this->requestCode($email)->assertOk();
            $this->requestCode($email)->assertStatus(429)->assertJsonStructure(['message', 'retry_after']);
        }

        $this->travel(31)->seconds();
        $this->requestCode('budi@example.com')->assertOk();
    }

    public function test_at_most_five_codes_per_hour_per_email(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->requestCode()->assertOk();
            $this->travel(31)->seconds();
        }

        $this->requestCode()->assertStatus(429);
        Mail::assertSent(PasswordResetCodeMail::class, 5);
    }

    public function test_send_throttle_can_be_switched_off_outside_production(): void
    {
        config(['proakses.password_reset_throttle' => false]);
        User::factory()->create(['email' => 'budi@example.com']);

        for ($i = 0; $i < 7; $i++) {
            $this->requestCode()->assertOk()->assertJson(['resend_in' => 0]);
        }

        Mail::assertSent(PasswordResetCodeMail::class, 7);
    }

    public function test_send_throttle_cannot_be_switched_off_in_production(): void
    {
        config(['proakses.password_reset_throttle' => false]);
        $this->app->detectEnvironment(fn () => 'production');
        User::factory()->create(['email' => 'budi@example.com']);

        $this->withSession(['_token' => 'tes']);
        $send = fn () => $this->postJson('/lupa-sandi/kode', ['email' => 'budi@example.com'], ['X-CSRF-TOKEN' => 'tes']);

        $send()->assertOk()->assertJson(['resend_in' => 30]);
        $send()->assertStatus(429);
    }

    public function test_wrong_code_limit_still_applies_when_send_throttle_is_off(): void
    {
        config(['proakses.password_reset_throttle' => false]);
        User::factory()->create(['email' => 'budi@example.com']);
        $this->requestCode();
        $real = $this->lastCode();
        $wrong = $this->wrongCode($real);

        for ($i = 0; $i < 5; $i++) {
            $this->verify($wrong);
        }

        $this->verify($real)->assertStatus(429)->assertJson(['code' => 'locked']);
    }

    public function test_correct_code_unlocks_the_password_step_and_is_single_use(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);
        $this->requestCode();
        $code = $this->lastCode();

        $this->verify($code)->assertOk()->assertJson(['ok' => true])->assertSessionHas('password_reset.email', 'budi@example.com');
        $this->verify($code)->assertStatus(422);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'budi@example.com']);
    }

    public function test_wrong_code_reports_remaining_attempts_and_does_not_unlock(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);
        $this->requestCode();

        $this->verify($this->wrongCode($this->lastCode()))
            ->assertStatus(422)
            ->assertJson(['attempts_left' => 4])
            ->assertSessionMissing('password_reset');
    }

    public function test_code_must_be_six_digits(): void
    {
        $this->verify('123')->assertStatus(422)->assertJsonValidationErrors('code');
        $this->verify('abcdef')->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_code_is_locked_after_five_wrong_attempts_even_with_the_right_code(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);
        $this->requestCode();
        $real = $this->lastCode();
        $wrong = $this->wrongCode($real);

        for ($i = 0; $i < 4; $i++) {
            $this->verify($wrong)->assertStatus(422);
        }
        $this->verify($wrong)->assertStatus(429)->assertJson(['code' => 'locked']);

        $this->verify($real)->assertStatus(429)->assertSessionMissing('password_reset');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'budi@example.com']);
    }

    public function test_requesting_a_new_code_after_lock_gives_fresh_attempts(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);
        $this->requestCode();
        $wrong = $this->wrongCode($this->lastCode());
        for ($i = 0; $i < 5; $i++) {
            $this->verify($wrong);
        }

        $this->travel(31)->seconds();
        $this->requestCode()->assertOk();
        $this->verify($this->lastCode())->assertOk();
    }

    public function test_code_expires_after_five_minutes(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);
        $this->requestCode();
        $code = $this->lastCode();

        $this->travel(301)->seconds();

        $this->verify($code)->assertStatus(422)->assertSessionMissing('password_reset');
    }

    public function test_new_code_invalidates_the_previous_one(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);
        $this->requestCode();
        $old = $this->lastCode();
        $this->travel(31)->seconds();
        $this->requestCode();
        $new = $this->lastCode();

        if ($old !== $new) {
            $this->verify($old)->assertStatus(422);
        }
        $this->verify($new)->assertOk();
    }

    public function test_wrong_code_response_has_same_shape_for_unknown_email(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);
        $this->requestCode();
        $known = $this->verify($this->wrongCode($this->lastCode()));
        $unknown = $this->verify('123456', 'tidakada@example.com');

        $this->assertSame($known->status(), $unknown->status());
        $this->assertSame($known->json(), $unknown->json());
    }

    public function test_password_cannot_be_changed_without_verifying_a_code(): void
    {
        $user = User::factory()->create(['email' => 'budi@example.com']);

        $this->postJson('/lupa-sandi/password', ['password' => 'baru12345', 'password_confirmation' => 'baru12345'])
            ->assertStatus(422)
            ->assertJson(['code' => 'session_expired']);

        $this->assertTrue(password_verify('password', $user->fresh()->password));
    }

    public function test_full_flow_resets_the_password(): void
    {
        $user = $this->verifiedUser(['email_verified_at' => null]);
        $oldRemember = $user->remember_token;

        $this->postJson('/lupa-sandi/password', ['password' => 'baru12345', 'password_confirmation' => 'baru12345'])
            ->assertOk()
            ->assertSessionMissing('password_reset');

        $user->refresh();
        $this->assertTrue(password_verify('baru12345', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotSame($oldRemember, $user->remember_token);

        $this->post('/login', ['email' => 'budi@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'budi@example.com', 'password' => 'baru12345']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_reset_link_cannot_be_reused_after_the_password_is_changed(): void
    {
        $this->verifiedUser();
        $this->postJson('/lupa-sandi/password', ['password' => 'baru12345', 'password_confirmation' => 'baru12345'])->assertOk();

        $this->postJson('/lupa-sandi/password', ['password' => 'lain123456', 'password_confirmation' => 'lain123456'])
            ->assertStatus(422)
            ->assertJson(['code' => 'session_expired']);
    }

    public function test_google_only_account_can_set_a_password_and_keeps_google(): void
    {
        $user = $this->verifiedUser(['password' => null, 'google_id' => 'g-123']);

        $this->postJson('/lupa-sandi/password', ['password' => 'baru12345', 'password_confirmation' => 'baru12345'])->assertOk();

        $user->refresh();
        $this->assertSame('g-123', $user->google_id);
        $this->assertTrue(password_verify('baru12345', $user->password));
    }

    public function test_new_password_must_follow_the_rules(): void
    {
        $user = $this->verifiedUser();

        foreach ([
            ['pendek1', 'pendek1'],
            ['hurufsajaa', 'hurufsajaa'],
            ['1234567890', '1234567890'],
            ['baru12345', 'beda12345'],
            ['', ''],
        ] as [$password, $confirmation]) {
            $this->postJson('/lupa-sandi/password', ['password' => $password, 'password_confirmation' => $confirmation])
                ->assertStatus(422)
                ->assertJsonValidationErrors('password');
        }

        $this->assertTrue(password_verify('password', $user->fresh()->password));
    }

    public function test_verified_state_expires_after_ten_minutes(): void
    {
        $this->verifiedUser();

        $this->travel(11)->minutes();

        $this->postJson('/lupa-sandi/password', ['password' => 'baru12345', 'password_confirmation' => 'baru12345'])
            ->assertStatus(422)
            ->assertJson(['code' => 'session_expired']);
    }

    public function test_verification_for_one_email_cannot_change_another_account(): void
    {
        $other = User::factory()->create(['email' => 'korban@example.com']);
        $this->verifiedUser();

        $this->postJson('/lupa-sandi/password', ['email' => 'korban@example.com', 'password' => 'baru12345', 'password_confirmation' => 'baru12345'])->assertOk();

        $this->assertTrue(password_verify('password', $other->fresh()->password));
    }

    public function test_the_email_contains_the_code_in_readable_groups(): void
    {
        $html = (new PasswordResetCodeMail('Budi', '123456'))->render();

        $this->assertStringContainsString('123 456', $html);
        $this->assertStringContainsString('5 menit', $html);
        $this->assertStringContainsString('Budi', $html);
    }
}
