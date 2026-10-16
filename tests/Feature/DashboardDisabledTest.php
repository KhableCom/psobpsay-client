<?php

test('dashboard routes are absent until explicitly enabled with middleware', function () {
    psobpsayConfigured();
    $this->get('/admin/psobpsay')->assertNotFound();
});
