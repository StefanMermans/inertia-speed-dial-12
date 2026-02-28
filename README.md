# Inertia speed dial 12

## Running tests

Run all tests:

```bash
php artisan test
```

Run a specific file or filter by name:

```bash
php artisan test tests/Feature/DashboardTest.php
php artisan test --filter="renders the dashboard"
```

Run in parallel or with coverage:

```bash
php artisan test --parallel
php artisan test --coverage
```

## Writing tests

Tests use [Pest](https://pestphp.com/) and live in `tests/Feature/`.

### HTTP tests

Standard request/response tests using Laravel's test helpers:

```php
it('redirects guests to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});
```

### Browser tests

Browser tests use [pest-plugin-browser](https://github.com/pestphp/pest-plugin-browser) (Playwright under the hood). A test is automatically detected as a browser test when it calls `visit()` as a standalone function:

```php
it('renders the dashboard for authenticated users in the browser', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    visit('/dashboard')
        ->assertRoute('dashboard')
        ->assertTitleContains('Dashboard');
});
```
