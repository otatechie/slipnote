<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseSnapshotTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/slipnote-snapshot-'.uniqid();
        File::ensureDirectoryExists($this->dir);
        touch($this->dir.'/database.sqlite');

        config(['database.connections.sqlite.database' => $this->dir.'/database.sqlite']);
        DB::purge('sqlite');
        DB::statement('CREATE TABLE boards (name TEXT)');
        DB::table('boards')->insert(['name' => 'Econ 200']);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_it_copies_the_database_before_a_migration(): void
    {
        $this->artisan('db:snapshot')->assertSuccessful();

        $copies = glob($this->dir.'/backups/*.sqlite');
        $this->assertCount(1, $copies);

        $copy = new \PDO('sqlite:'.$copies[0]);
        $this->assertSame('Econ 200', $copy->query('SELECT name FROM boards')->fetchColumn());
    }

    public function test_it_keeps_only_the_newest_copies(): void
    {
        File::ensureDirectoryExists($this->dir.'/backups');
        foreach (['2026-01-01_000000', '2026-01-02_000000', '2026-01-03_000000'] as $old) {
            touch($this->dir."/backups/{$old}.sqlite");
        }

        $this->artisan('db:snapshot', ['--keep' => 2])->assertSuccessful();

        $left = array_map('basename', glob($this->dir.'/backups/*.sqlite'));
        $this->assertCount(2, $left);
        $this->assertSame('2026-01-03_000000.sqlite', $left[0]);
    }

    public function test_it_does_nothing_without_a_database_file(): void
    {
        config(['database.connections.sqlite.database' => $this->dir.'/missing.sqlite']);

        $this->artisan('db:snapshot')->assertSuccessful();

        $this->assertDirectoryDoesNotExist($this->dir.'/backups');
    }

    public function test_two_copies_in_the_same_second_both_survive(): void
    {
        $this->travelTo(now()->startOfSecond());
        $this->artisan('db:snapshot')->assertSuccessful();
        $this->artisan('db:snapshot')->assertSuccessful();

        $names = array_map('basename', glob($this->dir.'/backups/*.sqlite'));
        sort($names);
        $this->assertCount(2, $names);
        $this->assertStringEndsWith('-2.sqlite', $names[0]); // '-' sorts before '.'
    }
}
