<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Imports\LocalCandidatesImport;
use App\Imports\LocalPartiesImport;
use Illuminate\Console\Command;

class ImportLocalCandidatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import:local-candidates {--parties} {--candidates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('parties')) {
            $this->output->title('Importing local parties...');

            (new LocalPartiesImport)->withOutput($this->output)->import(database_path('partide_locale_07.06.2024.xlsx'));

            $this->output->success('Local parties imported successfully!');
        }

        if ($this->option('candidates')) {
            $this->output->title('Importing local candidates...');

            (new LocalCandidatesImport)->withOutput($this->output)->import(database_path('candidati_locale_07.06.2024.csv'));

            $this->output->success('Local candidates imported successfully!');
        }

        return self::SUCCESS;
    }
}
