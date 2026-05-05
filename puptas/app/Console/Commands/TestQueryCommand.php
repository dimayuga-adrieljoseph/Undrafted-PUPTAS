<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestQueryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-query-command:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reports that the test query command is not implemented and should be removed or completed.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->error('The test query command is currently a placeholder. Implement its query behavior or remove it before shipping.');

        return self::FAILURE;
    }
}
