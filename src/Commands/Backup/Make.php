<?php

namespace Shams\Backup\Commands\Backup;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

class Make extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:make';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates backup of the database and .env into "backups" folder. 
                                It also creates a info file with the time and commit\'s hash';

    /**
     * Current backup path.
     *
     * @var string
     */
    protected $path;

    /**
     * Subdirectories to be added into zip.
     *
     * @return void
     */
    protected $subDir = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('------------------------- START CREATING BACKUP -------------------------');

        $this->setUp();
        $this->backup();

        $this->info('------------------------- END CREATING BACKUP -------------------------');

        $this->info('------------------------- UPLOADING BACKUP INTO AWS S3 -------------------------');

        $this->s3Upload();

        $this->info('------------------------- END UPLOADING BACKUP INTO AWS S3 -------------------------');

        $this->unlink();

        $this->info('------------------------- REMOVED BACKUP FILES FROM LOCAL -------------------------');
    }

    /**
     * Creates database backup.
     */
    protected function database()
    {
        $database = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        $output = shell_exec("mysqldump -u {$user} -p{$password} {$database} > {$this->path}/database.sql");

        $this->info($output);
    }

    /**
     * Creates backup of the .env file.
     */
    protected function env()
    {
        copy(base_path('.env'), "{$this->path}/.env");
    }

    /**
     * Creates info file that
     */
    protected function infoFile()
    {
        file_put_contents(
            "{$this->path}/info",
            json_encode($this->payload(), JSON_PRETTY_PRINT)
        );
    }

    /**
     * Creates backup of all uploaded files.
     */
    protected function uploadedFiles()
    {
        foreach (config('backup.backup.source.files.include') as $dir) {
            File::copyDirectory($dir, $this->path . str_replace(base_path(), '', $dir));
        }
    }

    protected function setPath()
    {
        $this->path = 'backups/' . now()->format('Y-m-d_H-i');
    }

    /**
     * Creates backup directory.
     */
    protected function createDirectory()
    {
        mkdir($this->path);
//        @mkdir($this->path, '0755', true);
    }

    /**
     * @return array
     */
    protected function payload()
    {
        return [
            'created_at' => now()->format('Y-m-d H:i:s'),
            'commit' => $this->commit()
        ];
    }

    /**
     * Return current commit hash.
     *
     * @return string
     */
    protected function commit()
    {
        return trim(shell_exec('git rev-parse HEAD'));
    }

    /**
     * Do initial set up before proceed with the backups.
     */
    protected function setUp()
    {
        $this->setPath();
        $this->createDirectory();
    }

    /**
     * Do the backup
     */
    protected function backup()
    {
        $this->database();
        $this->env();
        $this->infoFile();
        $this->uploadedFiles();
    }


    /**
     * Function to add files in zip
     *
     * @param $files
     * @param $archive
     * @throws \Exception
     */
    protected function addFileToZip($files, $archive)
    {
        foreach ($files as $file) {
            if (is_file($file) && $archive->addFile($file, $this->subDir . basename($file))) {
                // do something here if addFile succeeded, otherwise this statement is unnecessary and can be ignored.
                continue;
            } elseif (is_dir($file)) {
                $files = [];
                $this->subDir .= basename($file) . '/';
                foreach (array_diff(scandir($file), array('.', '..')) as $item) {
                    $files[] = $file . '/' . $item;
                }
                $this->addFileToZip($files, $archive);
            } else {
                throw new \Exception("file `{$file}` could not be added to the zip file: " .
                    $archive->getStatusString());
            }
        }
    }

    /**
     * Function to upload backup zip into AWS S3
     */
    protected function s3Upload()
    {
        try {
            $files = glob(base_path($this->path . '/*'));
            $archiveFile = base_path("$this->path.zip");

            $archive = new ZipArchive($archiveFile);

            if ($archive->open($archiveFile, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
                $this->addFileToZip($files, $archive);
                $archive->close();
            }

            // Instantiate an Amazon S3 client.
            $s3 = new S3Client([
                'version' => 'latest',
                'region' => env('AWS_DEFAULT_REGION'),
                'credentials' => [
                    'key' => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                ]
            ]);

            $s3->putObject([
                'Bucket' => env('AWS_BUCKET'),
                'Key' => "$this->path.zip",
                'Body' => fopen("$this->path.zip", 'r'),
                'ACL' => 'private',
            ]);
        } catch (S3Exception $e) {
            $this->error("There was an error uploading the file.");
            exit(0);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            exit(0);
        }
    }

    /**
     * Remove the backup files from local
     */
    protected function unlink()
    {
        system("rm -rf " . escapeshellarg($this->path));
        unlink("$this->path.zip");
    }
}
