# Tests Design Pattern

This document defines the standard for creating and naming tests within the project.

## Test-Driven Development (TDD)

This project follows the Test-Driven Development (TDD) methodology. 

## File Naming Convention

The organization and naming of test files must be based on the directory structure where they are located. This ensures that every test file has a unique name, making it easy to identify the file's purpose just by looking at its name. This approach also fully complies with Laravel's development standards.

### Example 1

If you create a file to test the `users` database table inside the following directory:

`tests/Feature/Database`

The file name must be:

`UserDatabaseFeatureTest.php`

### Example 2

If you create a file to test the `ProcessPayment` job inside the following directory:

`tests/Feature/Jobs`

The file name must be:

`ProcessPaymentJobsFeatureTest.php`

## Database Tables Testing

When creating tests for database tables, it is strictly required to follow a comprehensive testing structure. You must write specific tests for the following aspects of the table, ensuring that **absolutely no column, type, index, or relationship is left out**:

1. **Table Existence:** A test to verify that the table exists in the database.
2. **Columns Existence:** A test to verify that all expected columns exist in the table. Every single column must be asserted.
3. **Column Types:** A test to verify that each column has the correct data type. All columns must be checked.
4. **Column Indexes:** A test to verify all indexes (e.g., primary keys, foreign keys, unique constraints). All indexes must be validated.
5. **Relationships:** Tests to verify all relationships defined for the table's corresponding model (e.g., belongsTo, hasMany). All relationships must be covered.

**CRITICAL RULE:** It is unacceptable to skip any column, type, index, or relationship. The test suite for a database table must be an exact and complete representation of its schema and model relationships.

### Database Setup & Migrations

When testing the database, it is impossible to accurately assert columns and types without the tables actually existing. Therefore, the database migrations must be executed before the tests run.

**Important Rule:** Migrations must be executed **only once** before the test suite or test file runs, and **not** before every single test method. Running migrations before every single test drastically slows down the test suite. 

To achieve this in Laravel with Pest, you must use the `RefreshDatabase` trait. This trait ensures that your database is migrated once at the beginning, and then uses database transactions to cleanly reset the state after each test without re-running the migrations.

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('verifies if the users table exists', function () {
    // ...
});
```

## Test Method Naming Convention

To ensure that the test suite output is highly readable and acts as live documentation for the project, the names of the individual tests (the methods or the Pest descriptions) must follow a clear and consistent pattern. 

### The "It" Pattern

All test names should describe the expected behavior starting with the word "it" (implicitly or explicitly), followed by an action verb, the subject, and the expected outcome or condition. The name should read like a natural English sentence.

**Format:** `it [action verb] [subject] [condition/context]`

### Examples

#### For Database Table Tests:
- `it verifies if the users table exists`
- `it verifies if the users table columns exist`
- `it verifies the users table column types`
- `it verifies the users table column indexes`
- `it verifies the users table relationships`

#### For Feature/Behavioral Tests:
- `it authenticates a user with valid credentials`
- `it prevents registration with an existing email address`
- `it dispatches the process payment job successfully`

### Framework Implementation

This project exclusively uses **Pest PHP** for testing. You must use the `it()` function directly with the natural language sentence:

```php
it('verifies if the users table exists', function () {
    // Assertions...
});
```

This pattern ensures that when a test fails, the terminal output clearly indicates exactly what behavior is broken, making debugging much easier and maintaining the readability of the test suite.

## Test Structure & Readability

To guarantee that the code inside the tests is standardized, readable, and easy to maintain, you should implement the following practices:

### 1. Arrange, Act, Assert (AAA) Pattern

Every test should visually separate its logic into three distinct blocks, separated by a blank line. This makes the test's intention immediately understandable.

- **Arrange:** Set up the necessary state, create models, or prepare data.
- **Act:** Execute the specific action or behavior being tested.
- **Assert:** Verify that the action produced the expected result.

### 2. Pest Expectation API

To make assertions read like natural English, always use Pest's `expect()` API instead of standard PHPUnit assertions (`$this->assertTrue()`, `$this->assertEquals()`, etc.).

```php
it('calculates the total price of an order', function () {
    // Arrange
    $order = Order::factory()->create();
    $product = Product::factory()->create(['price' => 100]);
    
    // Act
    $order->addProduct($product);
    
    // Assert
    expect($order->total_price)->toBe(100);
});
```
