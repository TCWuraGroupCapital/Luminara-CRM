<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

/**
 * Keep Pest and PHPUnit away from the live demo database without breaking CI.
 *
 * The local Docker stack exports DB_DATABASE=luminara_crm, which holds the stakeholder demo data
 * used in the S11 presentation. Some agent tests exercise installer endpoints that can run
 * migrate:fresh, so a silent fall-back to that database is not acceptable. CI has no such exported
 * variable: it writes its own DB_DATABASE=krayin into .env and must be left alone.
 *
 * Laravel's immutable env repository reads $_SERVER first, then $_ENV/getenv(), so all three are
 * set together. A TestCase guard in tests/TestCase.php is the second line of defence for a host
 * that has the demo DB only in .env and no exported variable.
 */
$demoDatabase = 'luminara_crm';

if (getenv('DB_DATABASE') === $demoDatabase) {
    $testDatabase = getenv('LUMINARA_TEST_DB') ?: 'luminara_crm_test';

    putenv('DB_DATABASE='.$testDatabase);
    $_ENV['DB_DATABASE'] = $testDatabase;
    $_SERVER['DB_DATABASE'] = $testDatabase;
}
