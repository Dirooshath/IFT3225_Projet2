<?php
// This is the main entry point of our application
// It simply includes the api.php file which handles both API requests
// and serves the SPA HTML

// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if this is an API request
if (isset($_GET['path'])) {
    // It's an API request, include the API handler
    include 'api.php';
} else {
    // It's a regular page request, serve the SPA
    include 'index.html';
}
