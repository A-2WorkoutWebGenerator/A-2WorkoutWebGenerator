<?php
// index.php
// This file loads and displays WoW.html

// Path to the HTML interface file
$htmlFile = 'WoW.html';

// Check if the file exists
if (file_exists($htmlFile)) {
    // Output the contents of WoW.html
    readfile($htmlFile);
} else {
    // Display an error message if the file is not found
    echo "<h1>Error</h1>";
    echo "<p>The interface file <strong>WoW.html</strong> could not be found.</p>";
}
?>