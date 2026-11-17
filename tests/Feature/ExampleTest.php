<?php

test('home redirects guests to login', function () {
    $this->get('/')->assertRedirect('/studio');
    $this->get('/studio')->assertRedirect(route('login'));
});
