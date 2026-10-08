<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests wipe and rebuild the database. Check BEFORE any test trait runs, and refuse to
     * continue unless the database is throwaway, so a real DB_DATABASE in the environment
     * can never be erased by a test run.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $database = $app['config']->get('database.connections.'.$app['config']->get('database.default').'.database');

        if ($database !== ':memory:') {
            throw new RuntimeException("Refusing to run tests against '{$database}'. Tests must use an in-memory database.");
        }

        return $app;
    }
}
