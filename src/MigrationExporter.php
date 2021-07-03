<?php

namespace JugalKariya\LaravelMigrationExporter;

use Storage;
use SqlFormatter;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Console\Migrations\BaseCommand as MigrationBaseCommand;

class MigrationExporter extends MigrationBaseCommand
{

    protected $signature = 'migrate:export';

    protected $description = 'Export migrations to raw SQL files';

    protected $migrator;

    public function __construct(Migrator $migrator)
    {
        parent::__construct();

        $this->migrator = $migrator;
    }

    protected function prepareDatabase()
    {
        $this->migrator->setConnection();
    }

    protected function getQueries($migration, $method)
    {
        $db = $this->migrator->resolveConnection(
            $migration->getConnection()
        );

        return $db->pretend(function () use ($migration, $method) {
            if (method_exists($migration, $method)) {
                $migration->{$method}();
            }
        });
    }

    protected function transformSourceFileName($originalName)
    {
        $fileNameMap = explode('_', $originalName, 5);
        $suffix = str_replace ('_', '-', $fileNameMap['4']);
        unset($fileNameMap['4']);
        $date = date_create_from_format('Y_m_d_His', implode('_', $fileNameMap));

        return $date->format('Ymdhis') . '.000000000' . '-' . $suffix;
    }

    protected function displayResult($data)
    {
        $displayData = [];

        foreach ($data as $row) {
            if (!$row['exists']) {
                $displayData[] = array_only($row, ['source_file_name', 'export_file_name']);
            }
        }

        if (count($displayData)) {
            $this->info(count($displayData) . " migrations exported: ");
            $this->table(['PHP Source File', 'Exported SQL File'], $displayData);
        } else {
            $this->info("Nothing to export");
        }

    }

    public function getExportPath($fileName)
    {
        return 'raw/migrations/'.$fileName.'.sql';
    }


    public function handle()
    {
        $this->line('Exporting migrations to: ' . storage_path('raw/migrations/'));

        $data = [];

        $files = $this->migrator->getMigrationFiles($this->getMigrationPaths());

        $this->migrator->requireFiles($files);

        foreach ($files as $file) {
            $migration = $this->migrator->resolve(
                $fileName = $this->migrator->getMigrationName($file)
            );

            $queries = [];

            $exportFileName = $this->transformSourceFileName($fileName);

            foreach ($this->getQueries($migration, 'up') as $query) {
                $name = get_class($migration);
                $queries[] = "{$query['query']}";
            }

            $exportPath = $this->getExportPath($exportFileName);
            if (Storage::exists($exportPath)) {
                $exists = true;
            } else {
                $exists = false;
                $content = implode('; ', $queries).';';
                $content = SqlFormatter::format($content, false);
                Storage::put($exportPath, $content);
            }


            $data[] = [
                'source_file_name' => $fileName,
                'export_file_name' => $exportFileName,
                'exists'           => $exists,
                'queries'          => $queries
            ];


        }

        $this->displayResult($data);
    }
}
