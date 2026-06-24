<?php

it('renders the Upload Inertia page at / with a 200 response', function () {
    // The real Upload.vue page is delivered by REQ-004; assert by component name.
    config()->set('inertia.testing.ensure_pages_exist', false);

    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Upload'));
});
