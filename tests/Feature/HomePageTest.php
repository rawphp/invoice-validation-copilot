<?php

it('renders the placeholder Inertia page at / with a 200 response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Welcome'));
});
