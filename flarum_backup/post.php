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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parentId = filter_input(INPUT_POST, 'parent_id', FILTER_VALIDATE_INT);
    $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING);
    $username = $_SESSION['username'];
    
    // For top-level posts, we need a title
    if ($parentId == 0) {
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
        if (!$title || !$content) {
            header('Location: index.php?error=missing_fields');
            exit;
        }
        addPost($title, $content, $username, $parentId);
    } else {
        // For replies, title is optional
        if (!$content) {
            header('Location: index.php?error=missing_content');
            exit;
        }
        addPost('', $content, $username, $parentId);
    }
    
    // Redirect back to the main page
    header('Location: index.php');
    exit;
} else {
    // If accessed directly without POST data, redirect to home
    header('Location: index.php');
    exit;
}
