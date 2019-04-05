<?php

namespace Shams\Backup\Commands\Backup;

use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/*
 * Class to perform the restoring tasks.
 * It fetches the specified backup file from AWS S3. Following are the restoring steps.
 * 1. Restore upload folder, 2. Restore Database, 3. Restore code by switching to the commit.
 */
class Restore extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:restore {by : k} {value : key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore the specified back up file, Database and back to the commit for the specified key from AWS S3';

    /**
     * Amazon S3 client.
     *
     * @S3Client
     */
    protected $s3;

    /**
     * Restore directory name in local.
     *
     * @var string
     */
    protected $restoreDir;

    /**
     * Restore backup by exact key.
     *
     * @var string
     */
    protected $restoreMode;

    /**
     * Backup file Key in AWS S3.
     *
     * @var string
     */
    protected $backUpKey;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        // Instantiate an Amazon S3 client.
        $this->s3 = new S3Client([
            'version' => 'latest',
            'region' => env('AWS_DEFAULT_REGION'),
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ]
        ]);
    }

    /**
     * Function to setup the class variables.
     */
    protected function setUp()
    {
        $this->restoreMode = trim($this->argument('by'));
        $this->backUpKey = trim($this->argument('value'));

        $this->validateArgs();

        $this->setUpRestores();
    }

    /**
     * Function to set the back up file key.
     */
    protected function validateArgs()
    {
        if ($this->restoreMode !== 'k' || !$this->backUpKey) {
            Log::error($this->arguments());

            $this->error('Invalid argument.');
            exit(0);
        }
    }

    /**
     * Function to set up restore directory.
     */
    protected function setUpRestores()
    {
        @mkdir('restores');

        $this->restoreDir = 'restores/' . pathinfo($this->backUpKey)['filename'];
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('------------------------- START RESTRING -------------------------');

        $this->setUp();

        $this->getBackUp();

        $this->restoreUpload();

        $this->restoreDatabase();

        $this->restoreCode();

        $this->info('------------------------- END RESTORING -------------------------');

        $this->warn('Please change the Configuration file(.env) according to the .env.example file.');
    }

    /**
     * Function to get the backup file from AWS S3.
     */
    protected function getBackUp()
    {
        try {
            $s3file = $this->s3->getObject([
                'Bucket' => env('AWS_BUCKET'),
                'Key' => $this->backUpKey
            ]);

            if ($s3file['ContentLength'] > 0 && !empty($s3file['ContentType'])) {
                $body = $s3file->get('Body');

                file_put_contents("$this->restoreDir.zip", $body);
            }

            $this->unzip();

            $this->info('------------------------- RECEIVED BACKUP FROM AWS S3 -------------------------');
        } catch (\Exception $exc) {
            Log::error($exc->getMessage());

            $this->error('Unable to fetch backup file you specified.' . PHP_EOL
                . '-> Make sure to provide the full key name of the backup file.' . PHP_EOL
                . '-> Make sure the key exists in AWS S3 bucket.');

            exit(0);
        }
    }

    /**
     * Function to unzip the zip file.
     */
    protected function unzip()
    {
        /* Open the Zip file */
        $zip = new ZipArchive();

        if ($zip->open("$this->restoreDir.zip") != "true") {
            echo "Error :- Unable to open the Zip File";
        }

        /* Extract Zip File */
        $zip->extractTo($this->restoreDir);
        $zip->close();
    }

    /**
     * Function to restore uploads directory.
     */
    protected function restoreUpload()
    {
        // Remove upload folder
        shell_exec("rm -r public/upload/");

        // Copy upload folder to restore
        shell_exec("cp -r {$this->restoreDir}/upload/ public/");

        $this->info('------------------------- RESTORED UPLOAD FOLDER -------------------------');
    }

    /**
     * Function to restore database.
     */
    protected function restoreDatabase()
    {
        $database = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        // Drop old database
        shell_exec("mysql -u {$user} -p{$password} -e 'drop database {$database};'");

        // Create new database
        shell_exec("mysql -u {$user} -p{$password} -e 'create database {$database};'");

        try {
            // Restore database
            shell_exec("mysql -u {$user} -p{$password} {$database} < {$this->restoreDir}/database.sql");

            $this->info('------------------------- RESTORED DATABASE -------------------------');
        } catch (\Exception $exc) {
            Log::error($exc->getMessage());

            $this->error($exc->getMessage());
            exit(0);
        }
    }

    /**
     * Function to checkout to the commit id.
     */
    protected function restoreCode()
    {
        $info = file_get_contents("$this->restoreDir/info");
        $commitId = json_decode($info, true)['commit'];

        $this->unlink();

        shell_exec("git checkout {$commitId}");

        Log::info('Project is restored to the commit: ' . $commitId . ', backup key: ' . $this->backUpKey);

        $this->info('-------------------- CHECKED OUT TO THE COMMIT ' . $commitId . ' --------------------');
    }

    /**
     * Clean restore files.
     */
    protected function unlink()
    {
        system("rm -rf " . escapeshellarg('restores'));

        $this->info('------------------------- CLEAN RESTORES DIRECTORY -------------------------');
    }
}
