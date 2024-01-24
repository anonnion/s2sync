<?php

// Function to display the help message
function displayHelp() {
    echo "Usage:\n";
    echo "php remote.php --serve --path=<path>\n";
    echo "php remote.php --fetch --token=<token> --path=<path> --remote=<remote>\n";
}

// Function to generate a random token
function generateToken() {
    return bin2hex(random_bytes(16));
}

// Function to list files in alphabetical descending order and return JSON
function listFiles($path) {
    $files = scandir($path, SCANDIR_SORT_DESCENDING);
    $files = array_diff($files, ['.', '..']);
    return json_encode($files);
}

// Function to validate the token
function validateToken($token, $bearerToken) {
    return trim($token) === trim($bearerToken);
}

// Function to fetch and process backups
function fetchBackups($token, $path, $remote) {
    // Construct the URL
    $url = "http://$remote:8082";

    // Initialize cURL session
    $ch = curl_init($url);

    // Set cURL options
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
        ],
    ];

    curl_setopt_array($ch, $options);

    // Execute cURL session and get the response
    $response = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    }

    // Close cURL session
    curl_close($ch);

    // Use the $response as needed

    // Validate the JSON response
    $jsonData = json_decode($response, true);
    if ($jsonData === null) {
        die("Invalid JSON response");
    }

    // Assuming the JSON response structure has a 'backups' key
    $backups = $jsonData ?? [];

    // Create an associative array with keys as file types (zip, sql) and values as corresponding files
    $backupFiles = [];
    foreach ($backups as $backup) {
        $extension = pathinfo($backup, PATHINFO_EXTENSION);
        $backupFiles[$extension][] = $backup;
    }

    // Filter the array based on the filename format "backup_YYYY_MM_DD_HH_II_SS"
    $filteredZips = array_filter($backupFiles['zip'], function ($backup) {
        return preg_match('/backup_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.zip/', $backup);
    });

    // Filter the array based on the filename format "backup_YYYY_MM_DD_HH_II_SS"
    $filteredSqls = array_filter($backupFiles['sql'], function ($backup) {
        return preg_match('/backup_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.sql/', $backup);
    });

    // Find the latest backup files
    $latestZip = end($filteredZips);
    $latestSql = end($filteredSqls);

    // Download and process the latest backup files
    if (!empty($latestZip) && !empty($latestSql)) {
        var_dump(
            $latestSql,
            $latestZip
        );

        $zipFilePath = "$path/$latestZip";
        $sqlFilePath = "$path/$latestSql";

        if (
            fetchFileContentsWithToken("http://$remote:8082/$latestZip", $token, $zipFilePath) &&
            fetchFileContentsWithToken("http://$remote:8082/$latestSql", $token, $sqlFilePath)
        ) {
            // Unzip the zip file
            $zip = new ZipArchive;
            $zip->open($zipFilePath);
            $zip->extractTo($path);
            $zip->close();

            // Delete the zip file
            unlink($zipFilePath);

            echo "Backup files downloaded and processed successfully.";
        } else {
            echo "Failed to download one or more backup files.";
        }
    } else {
        echo "No valid backup files found.";
    }
}

// Function to fetch file contents with token using cURL
function fetchFileContentsWithToken($filePath, $token, $destinationFile) {
    $ch = curl_init($filePath);

    echo "Downloading $destinationFile...\n";
    $fileHandle = fopen($destinationFile, 'wb');

    if (!$fileHandle) {
        // Handle file opening error
        echo 'Error opening destination file: ' . $destinationFile;
        return false;
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_FILE, $fileHandle);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
    ]);

    // Execute cURL session
    curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
        fclose($fileHandle);
        return false;
    }

    fclose($fileHandle);

    // Close cURL session
    curl_close($ch);

    return true;
}



// Function to get Public IP Address
function getPublicIP() {
    // Use an external service to get the public IP
    $ipApiUrl = 'https://api64.ipify.org?format=json';

    // Make a request to the IP API
    $response = file_get_contents($ipApiUrl);

    // Parse the JSON response
    $data = json_decode($response, true);

    // Extract and return the public IP
    return $data['ip'] ?? null;
}


// Function to start the server
function startServer($token, $path) {
    // Get IP
    $ip = getPublicIP();

    // Create socket
    $server = socket_create(AF_INET, SOCK_STREAM, 0);

    if ($server === false) {
        die("Error creating socket: " . socket_last_error());
    }

    // Bind the socket to the address and port
    if (!socket_bind($server, $ip, 8082)) {
        die("Error binding socket: " . socket_last_error());
    }

    // Listen for incoming connections
    if (!socket_listen($server)) {
        die("Error listening on socket: " . socket_last_error());
    }

    echo "Server started on  http://$ip:8082\n";
    echo "Use token: $token \n";

    while ($client = socket_accept($server)) {
        // Read the request
        $request = socket_read($client, 4096);

        // Extract the token from the request
        preg_match('/Bearer (.+)/', $request, $matches);
        $requestToken = $matches[1] ?? '';
        // var_dump($request);
        // Validate the token
        if (!validateToken($token, $requestToken)) {
            header("HTTP/1.1 403 Forbidden");
            echo "Invalid token \nExpecting:  $token \nGot:  $requestToken\n";
            socket_write($client, "Invalid token \nExpecting:  $token \nGot:  $requestToken\n");
            socket_close($client);
            continue;
        }

        // Extract the request URI
        preg_match('/GET (.+) HTTP/', $request, $uriMatches);
        $requestURI = $uriMatches[1] ?? '';

        // Check if the requested file exists with .zip or .sql extension
        $requestedFilePath = $path . urldecode($requestURI);
        print(
            "Sending: " . $requestedFilePath . " to: " . $_SERVER['REMOTE_IP'] ?? $_SERVER['REMOTE_ADDR']
        );
        echo "\n";
        if (file_exists($requestedFilePath) && (pathinfo($requestedFilePath, PATHINFO_EXTENSION) === 'zip' || pathinfo($requestedFilePath, PATHINFO_EXTENSION) === 'sql')) {
            // Serve the file in chunks
            $chunkSize = 1024 * 1024; // 1 MB chunks
            $fileHandle = fopen($requestedFilePath, 'rb');

            while (!feof($fileHandle)) {
                $chunk = fread($fileHandle, $chunkSize);
                socket_write($client, $chunk);
                usleep(50000); // Add a small delay to control the transmission speed
            }

            fclose($fileHandle);
        } else {
            // Send the list of files as the response
            $response = listFiles($path);
            // var_dump($response);
            socket_write($client, $response);
        }

        socket_close($client);
    }

    socket_close($server);
}



// Parse command line arguments
// var_dump($argv);
// Parse command line options
$options = getopt(null, ["serve", "fetch", "path:", "token:", "remote:"]);

if (isset($options['serve'])) {
    $path = $options['path'] ?? '';
    
    // Validate the path
    if (!is_dir($path)) {
        die("Invalid path provided");
    }

    // Start the server
    startServer(generateToken(), $path);
} elseif (isset($options['fetch'])) {
    $token = $options['token'] ?? '';
    $path = $options['path'] ?? '';
    $remote = $options['remote'] ?? '';

    // Validate the path
    if (!is_dir($path)) {
        die("Invalid path provided");
    }

    // // Validate the token
    // if (!validateToken($token, $authHeader)) {
    //     http_response_code(403);
    //     die("Invalid token");
    // }

    fetchBackups($token, $path, $remote);
} else {
    displayHelp();
}

register_shutdown_function(function () {
    echo "\n";
})
?>
