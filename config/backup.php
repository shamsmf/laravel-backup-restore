<?php
return [
    'backup' => [
        /*
         * The name of this application. You can use this name to monitor
         * the backups.
         */
        'name' => env('APP_NAME', 'laravel-backup'),
        'source' => [
            'files' => [
                /*
                 * The list of directories and files that will be included in the backup.
                 */
                'include' => [
                    public_path('upload')
                ],
                /*
                 * These directories and files will be excluded from the backup.
                 *
                 * Directories used by the backup process will automatically be excluded.
                 */
                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                ],
                /*
                 * Determines if symlinks should be followed.
                 */
                'follow_links' => false,
            ],
            /*
             * The names of the connections to the databases that should be backed up
             * MySQL, PostgreSQL, SQLite and Mongo databases are supported.
             *
             * The content of the database dump may be customized for each connection
             * by adding a 'dump' key to the connection settings in config/database.php.
             * E.g.
             * 'mysql' => [
             *       ...
             *      'dump' => [
             *           'excludeTables' => [
             *                'table_to_exclude_from_backup',
             *                'another_table_to_exclude'
             *            ]
             *       ]
             * ],
             *
             * For a complete list of available customization options, see https://github.com/spatie/db-dumper
             */
            'databases' => [
                'mysql',
            ],
        ],
        /*
         * The database dump can be compressed to decrease diskspace usage.
         *
         * Out of the box Laravel-backup supplies
         * Spatie\DbDumper\Compressors\GzipCompressor::class.
         *
         * You can also create custom compressor. More info on that here:
         * https://github.com/spatie/db-dumper#using-compression
         *
         * If you do not want any compressor at all, set it to null.
         */
        'database_dump_compressor' => null,
        'destination' => [
            /*
             * The filename prefix used for the backup zip file.
             */
            'filename_prefix' => '',
            /*
             * The disk names on which the backups will be stored.
             */
            'disks' => [
                'local',
            ],
        ],
        /*
         * The directory where the temporary files will be stored.
         */
        'temporary_directory' => storage_path('app/backup-temp'),
    ],

    'restore' => [

    ]
];