<?php

use App\Models\User;
use function Pest\Laravel\actingAs;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('delete profile route is no longer available', function () {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response->assertStatus(405);

    $this->assertNotNull($user->fresh());
});

test('usuario autenticado puede generar codigo de vinculacion de telegram', function () {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->post(route('profile.telegram.generate-code'));

    $response
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('telegram_link_code');

    $user->refresh();

    expect($user->telegram_link_code_hash)->not->toBeNull();
    expect($user->telegram_link_code_expires_at)->not->toBeNull();
});
