<?php

it('returns a successful response for the start page', function () {
    $this->get('/')->assertStatus(200);
});
