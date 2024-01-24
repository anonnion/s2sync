<?php

// Parse command-line options
$options = getopt(null, ['dbname:', 'dbhost:', 'dbuser:', 'dbpass:', 'path:']);

// Check if the required options are provided
if (!isset($options['dbname']) || !isset($options['path'])) {
    echo "Usage: php backup.php --dbname=<dbname> --dbhost=<dbhost> --dbuser=<dbuser> --dbpass=<dbpass> --path=<path>\n";
    exit(1);
}

// Capture options
$dbname = $options['dbname'];
$dbhost = $options['dbhost'];
$path = $options['path'];
if(@$options['dbhost'])
{
    $dbhost = $options['dbhost'];
}
else 
{
    $dbhost = "localhost";
    echo "Database host not provided, defaulting to \"localhost\" as database host\n";
}
if(@$options['dbuser'])
{
    $user = $options['dbuser'];
}
else 
{
    $user = "root";
    echo "Database username not provided, defaulting to \"root\" as database username\n";
}
if(@$options['dbpass'])
{
    $dbpass = $options['dbpass'];
}
else 
{
    $dbpass = "";
    echo "Database password not provided, defaulting to \"\"  (no password)\n";
}
if(!$dbname || !$path){
    echo "Usage: php backup.php --dbname=<dbname> --dbhost=<dbhost> --dbuser=<dbuser> --dbpass=<dbpass> --path=<path>\n";
    exit(1);
}

function backupDatabase($dbname, $dbhost, $user, $dbpass)
{
    // Generate a backup file name with underscores instead of slashes
    $backupFileName = 'backup_' . date('Y_m_d_H_i_s') . '.sql';

    // Use mysqldump command to create a database dump
    $command = "mysqldump -h $dbhost -u $user -p$dbpass $dbname > $backupFileName";
    echo $command . "\n";
    exec($command, $output, $returnCode);

    if ($returnCode === 0) {
        echo "Database backup successful. File: $backupFileName\n";
    } else {
        echo "Error during database backup:\n";
        print_r($output);
        exit(1);
    }

    return $backupFileName;
}

function zipFolder($path)
{
    // Generate a zip file name with comments and errors handled
    $zipFileName = 'backup_' . date('Y_m_d_H_i_s') . '.zip';

    // Create a ZipArchive
    $zip = new ZipArchive();

    if ($zip->open($zipFileName, ZipArchive::CREATE) === true) {
        // Add all files in the folder to the zip archive
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($path) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        // Close the ZipArchive
        $zip->close();

        echo "Folder zipped successfully. File: $zipFileName\n";
    } else {
        echo "Error creating zip file.\n";
        exit(1);
    }

    return $zipFileName;
}

function createBackupFolder($zipFileName, $sqlFileName)
{
    // Generate a folder name with the current date
    $folderName = 'backup_' . date('Y_m_d');

    // Create the backup folder
    mkdir($folderName);

    // Move the zip and sql files into the backup folder
    rename($zipFileName, $folderName . '/' . $zipFileName);
    rename($sqlFileName, $folderName . '/' . $sqlFileName);

    echo "Files moved to backup folder: $folderName\n";
}

// Example Usage:
// 1. Database Backup
$sqlFileName = backupDatabase($dbname, $dbhost, $user, $dbpass);

// 2. Folder Zipping
$zipFileName = zipFolder($path);

// 3. Create Backup Folder and Move Files
createBackupFolder($zipFileName, $sqlFileName);

?>
