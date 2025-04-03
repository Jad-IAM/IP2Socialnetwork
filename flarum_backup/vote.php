<?php
require_once 'functions.php';

// Initialize session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    $vote = filter_input(INPUT_POST, 'vote', FILTER_SANITIZE_STRING);
    $username = $_SESSION['username'];
    
    // Validate inputs
    if (!$postId || !in_array($vote, ['up', 'down'])) {
        header('Location: index.php?error=invalid_vote');
        exit;
    }
    
    // Process the vote
    votePost($postId, $username, $vote);
    
    // Redirect back to the main page
    header('Location: index.php');
    exit;
} else {
    // If accessed directly without POST data, redirect to home
    header('Location: index.php');
    exit;
}
