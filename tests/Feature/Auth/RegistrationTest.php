<?php

it('renders the registration screen', function () {
    $this->get('/register')->assertOk();
});
