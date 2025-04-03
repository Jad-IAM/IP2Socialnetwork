<?php
// Return list of available flairs
header('Content-Type: application/json');

$flairs = [
    'Announcement',
    'Discussion',
    'Question',
    'Meme',
    'Drama',
    'News',
    'Video',
    'Image',
    'Stream Highlight',
    'IP2 Army'
];

echo json_encode($flairs);
?>
