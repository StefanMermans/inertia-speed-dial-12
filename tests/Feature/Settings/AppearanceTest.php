<?php

declare(strict_types=1);

use App\Models\User;

const DARK_CLASS_APPLIED = "document.documentElement.classList.contains('dark')";

const DARK_TAB_ACTIVE = "[...document.querySelectorAll('button')].find((button) => button.textContent.trim() === 'Dark').classList.contains('bg-white')";

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders the appearance settings page', function () {
    $this->get(route('appearance'))->assertSuccessful();
});

it('applies the dark theme when dark appearance is selected', function () {
    visit(route('appearance'))
        ->click('Dark')
        ->assertScript(DARK_CLASS_APPLIED, true)
        ->assertScript("localStorage.getItem('appearance')", 'dark')
        ->assertNoJavaScriptErrors();
});

it('returns to the light theme when light appearance is selected', function () {
    visit(route('appearance'))
        ->click('Dark')
        ->click('Light')
        ->assertScript(DARK_CLASS_APPLIED, false)
        ->assertScript("localStorage.getItem('appearance')", 'light');
});

it('remembers the selected appearance after a reload', function () {
    visit(route('appearance'))
        ->click('Dark')
        ->refresh()
        ->assertScript(DARK_CLASS_APPLIED, true)
        ->assertScript(DARK_TAB_ACTIVE, true)
        ->assertNoJavaScriptErrors();
});

it('follows a dark system color scheme when system appearance is selected', function () {
    visit(route('appearance'))
        ->inDarkMode()
        ->click('System')
        ->assertScript(DARK_CLASS_APPLIED, true);
});

it('follows a light system color scheme when system appearance is selected', function () {
    visit(route('appearance'))
        ->inLightMode()
        ->click('System')
        ->assertScript(DARK_CLASS_APPLIED, false);
});
