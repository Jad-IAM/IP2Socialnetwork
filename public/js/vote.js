/**
 * JavaScript for handling AJAX voting
 */
document.addEventListener('DOMContentLoaded', function() {
    // Setup post voting
    setupPostVoting();
    
    // Setup comment voting
    setupCommentVoting();

    /**
     * Setup post voting
     */
    function setupPostVoting() {
        const upvoteButtons = document.querySelectorAll('.post .upvote');
        const downvoteButtons = document.querySelectorAll('.post .downvote');
        
        upvoteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const postId = this.getAttribute('data-post-id');
                if (postId) {
                    voteOnPost(postId, 1, this);
                }
            });
        });
        
        downvoteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const postId = this.getAttribute('data-post-id');
                if (postId) {
                    voteOnPost(postId, -1, this);
                }
            });
        });
    }
    
    /**
     * Setup comment voting
     */
    function setupCommentVoting() {
        const upvoteButtons = document.querySelectorAll('.comment .upvote');
        const downvoteButtons = document.querySelectorAll('.comment .downvote');
        
        upvoteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const commentId = this.getAttribute('data-comment-id');
                if (commentId) {
                    voteOnComment(commentId, 1, this);
                }
            });
        });
        
        downvoteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const commentId = this.getAttribute('data-comment-id');
                if (commentId) {
                    voteOnComment(commentId, -1, this);
                }
            });
        });
    }
    
    /**
     * Vote on a post
     * 
     * @param {number} postId The post ID
     * @param {number} voteType 1 for upvote, -1 for downvote
     * @param {HTMLElement} button The button that was clicked
     */
    function voteOnPost(postId, voteType, button) {
        const formData = new FormData();
        formData.append('post_id', postId);
        formData.append('vote_type', voteType);
        
        fetch('/api/vote.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update vote count
                const voteCountElement = button.parentElement.querySelector('.vote-count');
                if (voteCountElement) {
                    voteCountElement.textContent = data.vote_count;
                }
                
                // Update button styles
                const upvoteButton = button.parentElement.querySelector('.upvote');
                const downvoteButton = button.parentElement.querySelector('.downvote');
                
                if (data.message === 'Vote removed') {
                    // Remove active class from the clicked button
                    button.classList.remove('active');
                } else {
                    // Add active class to the clicked button and remove from the other
                    if (voteType === 1) {
                        upvoteButton.classList.add('active');
                        downvoteButton.classList.remove('active');
                    } else {
                        downvoteButton.classList.add('active');
                        upvoteButton.classList.remove('active');
                    }
                }
            } else {
                // If not logged in, redirect to login page
                if (data.message === 'You must be logged in to vote') {
                    window.location.href = '/login.php?redirect=' + encodeURIComponent(window.location.pathname);
                } else {
                    alert(data.message);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
    
    /**
     * Vote on a comment
     * 
     * @param {number} commentId The comment ID
     * @param {number} voteType 1 for upvote, -1 for downvote
     * @param {HTMLElement} button The button that was clicked
     */
    function voteOnComment(commentId, voteType, button) {
        const formData = new FormData();
        formData.append('comment_id', commentId);
        formData.append('vote_type', voteType);
        
        fetch('/api/comment_vote.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update vote count
                const voteCountElement = button.parentElement.querySelector('.vote-count');
                if (voteCountElement) {
                    voteCountElement.textContent = data.vote_count;
                }
                
                // Update button styles
                const upvoteButton = button.parentElement.querySelector('.upvote');
                const downvoteButton = button.parentElement.querySelector('.downvote');
                
                if (data.message === 'Vote removed') {
                    // Remove active class from the clicked button
                    button.classList.remove('active');
                } else {
                    // Add active class to the clicked button and remove from the other
                    if (voteType === 1) {
                        upvoteButton.classList.add('active');
                        downvoteButton.classList.remove('active');
                    } else {
                        downvoteButton.classList.add('active');
                        upvoteButton.classList.remove('active');
                    }
                }
            } else {
                // If not logged in, redirect to login page
                if (data.message === 'You must be logged in to vote') {
                    window.location.href = '/login.php?redirect=' + encodeURIComponent(window.location.pathname);
                } else {
                    alert(data.message);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
});
