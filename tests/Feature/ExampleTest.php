<?php

it('redirects guests from root to login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
