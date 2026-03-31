<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});

test('new users can not register when registration is disabled', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertNotFound();
});
