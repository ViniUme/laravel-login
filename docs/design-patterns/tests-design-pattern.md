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
