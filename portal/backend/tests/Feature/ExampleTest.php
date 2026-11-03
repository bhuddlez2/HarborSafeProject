<?php

// A smoke test that the application boots. /up is Laravel's built-in health
// route (bootstrap/app.php) - it needs no session, panel or database, so it
// fails only when something is broken at boot. What / does for an anonymous
// visitor is covered in tests/Feature/Staff/ContentPagesRenderTest.php.
test('the application boots and answers its health check', function () {
    $this->get('/up')->assertOk();
});
