<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the full framework via the base TestCase. Unit tests
| stay framework-light. No database is configured for this project.
|
*/

pest()->extend(TestCase::class)->in('Feature');
