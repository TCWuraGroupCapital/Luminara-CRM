<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /**
         * Fail closed. tests/bootstrap.php redirects the local Docker stack to luminara_crm_test,
         * but a host run that only has the demo database in .env would otherwise silently use it.
         * CI resolves to its own `krayin` database and is unaffected.
         */
        if (config('database.connections.mysql.database') === 'luminara_crm') {
            throw new RuntimeException(
                'Refusing to run tests against the live demo database [luminara_crm]. '
                .'Export DB_DATABASE=luminara_crm_test (or set LUMINARA_TEST_DB) before running the suite.'
            );
        }
    }
}
