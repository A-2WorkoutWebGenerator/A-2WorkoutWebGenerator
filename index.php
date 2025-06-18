<?php
$htmlFile = 'WoW.html';

if (file_exists($htmlFile)) {
    readfile($htmlFile);
} else {
    echo "<h1>Error</h1>";
    echo "<p>The interface file <strong>WoW.html</strong> could not be found.</p>";
}
?>