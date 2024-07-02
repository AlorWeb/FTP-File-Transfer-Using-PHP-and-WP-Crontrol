<?php

 
// Action that will be triggered by the cron job
add_action('test_ftp_transfer', 'fetch_generated_csv_and_transfer_via_ftp');
 
function fetch_generated_csv_and_transfer_via_ftp() {
     // Source FTP server details
     $source_ftp_server = "";
     $source_ftp_username = "";
     $source_ftp_password = "";
     $source_directory = "";

     // Destination FTP server details
     $dest_ftp_server = "";
     $dest_ftp_username = "";
     $dest_ftp_password = "";
     $dest_path = "";

     // Set up basic connection
     $source_conn = ftp_connect($source_ftp_server);

     // Login with username and password
     $login_result = ftp_login($source_conn, $source_ftp_username, $source_ftp_password);

     // Check connection
     if ((!$source_conn) || (!$login_result)) {
          echo "FTP connection has failed!<br>";
          return;
     } else {
          echo "Connected<br>";
     }

     // Enable passive mode for source connection
     ftp_pasv($source_conn, true);

     // Change the directory
     if (ftp_chdir($source_conn, $source_directory)) {
          echo "Current directory is now: " . ftp_pwd($source_conn) . "<br>";
     } else {
          echo "Couldn't change directory.<br>";
          ftp_close($source_conn);
          return;
     }

     // Scan the source directory for files matching the naming pattern
     $files = ftp_nlist($source_conn, $source_directory);


     // Filter files based on the naming pattern
     $filtered_files = array_filter($files, function($file) use ($source_directory) {
          return preg_match('/9999_\d{8}_\d{6}_9\d{5}\.csv$/', $file);
     });
      // If no files found, exit
     if (empty($filtered_files)) {
          echo "No CSV files found.";
          ftp_close($source_conn);
          return;
     }

     // Function to get last modified time of a file via FTP
     function get_file_mtime($conn, $file) {
          $timestamp = ftp_mdtm($conn, $file);
          return ($timestamp != -1) ? $timestamp : 0; // Return 0 if timestamp retrieval fails
     }

     // Sort files by modified time
     usort($filtered_files, function($a, $b) use ($source_conn) {
          $mtime_a = get_file_mtime($source_conn, $a);
          $mtime_b = get_file_mtime($source_conn, $b);
          return $mtime_b - $mtime_a; // Sort in descending order (latest first)
     });

     // Extract filenames from paths
     $filenames = array_map('basename', $filtered_files);

     // Output the filenames
     foreach ($filenames as $filename) {
          echo "Found file: $filename<br>";
     }

       // Get the latest file
     $latest_file = $filenames[0];
     $local_file = tempnam(sys_get_temp_dir(), 'ftp'); // this will be saved temporarily in server tmp folder

     var_dump($latest_file);

     // Log the paths for debugging
     error_log("Latest file: $latest_file");
     error_log("Local file: $local_file");
     
     // Close connection to source FTP server
     ftp_close($source_conn);

     // Set up basic connection to destination FTP server
     $dest_conn = ftp_connect($dest_ftp_server);

     // Login to destination FTP server
     $dest_login_result = ftp_login($dest_conn, $dest_ftp_username, $dest_ftp_password);

     // Check connection to destination FTP server
     if ((!$dest_conn) || (!$dest_login_result)) {
          echo "FTP connection to destination server has failed!<br>";
          return;
     } else {
          echo "<br>Connected to destination server<br>";
     }

     // Enable passive mode for destination connection
     ftp_pasv($dest_conn, true);

     // Change the directory on destination FTP server
    if (ftp_chdir($dest_conn, $dest_path)) {
          echo "<br>Current directory on destination server is now: " . ftp_pwd($dest_conn) . "<br>";
     } else {
          echo "<br>Couldn't change directory on destination server.<br>";
          ftp_close($dest_conn);
          return;
     }

     // Upload the latest file to destination FTP server
     $remote_filename = basename($latest_file);
     var_dump($remote_filename);
     if (!ftp_put($dest_conn, $remote_filename, $local_file, FTP_BINARY)) {
          echo "<br>Failed to upload the latest file to destination server.<br>";
     } else {
          echo "<br>Successfully uploaded $remote_filename to $dest_ftp_server in $dest_path <br>";
     }

     // Close connection to destination FTP server
     ftp_close($dest_conn);

     // Delete the local temporary file
     unlink($local_file);

}

?>
