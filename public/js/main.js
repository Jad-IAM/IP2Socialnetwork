/**
 * IP2∞Social.network JavaScript functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Flair dropdown toggle
    const flairToggle = document.getElementById('flair-dropdown-toggle');
    const flairDropdown = document.getElementById('flair-dropdown');
    
    if (flairToggle && flairDropdown) {
        // Load available flairs via AJAX
        fetch('get_flairs.php')
            .then(response => response.json())
            .then(flairs => {
                const flairContent = flairDropdown.querySelector('.flair-dropdown-content');
                
                // Generate flair options
                flairs.forEach(flair => {
                    const flairItem = document.createElement('div');
                    flairItem.className = 'flair-item';
                    flairItem.setAttribute('data-flair-id', flair.id);
                    flairItem.style.color = 'white';
                    flairItem.style.backgroundColor = flair.color;
                    flairItem.textContent = flair.name;
                    
                    flairItem.addEventListener('click', function() {
                        // Set selected flair
                        document.getElementById('selected-flair').value = flair.id;
                        
                        // Update button text
                        flairToggle.innerHTML = `<i class="fas fa-tag"></i> ${flair.name}`;
                        flairToggle.style.color = 'white';
                        flairToggle.style.backgroundColor = flair.color;
                        
                        // Hide dropdown
                        flairDropdown.classList.remove('show');
                    });
                    
                    flairContent.appendChild(flairItem);
                });
            })
            .catch(error => console.error('Error loading flairs:', error));
        
        // Toggle dropdown on button click
        flairToggle.addEventListener('click', function(e) {
            e.preventDefault();
            flairDropdown.classList.toggle('show');
            
            // Position the dropdown
            if (flairDropdown.classList.contains('show')) {
                const buttonRect = flairToggle.getBoundingClientRect();
                flairDropdown.style.top = `${buttonRect.bottom}px`;
                flairDropdown.style.left = `${buttonRect.left}px`;
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!flairToggle.contains(e.target) && !flairDropdown.contains(e.target)) {
                flairDropdown.classList.remove('show');
            }
        });
    }
    
    // Handle post form submission with flair
    const postForm = document.getElementById('post-form');
    if (postForm) {
        postForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(postForm);
            
            fetch('create_post.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'index.php';
                } else {
                    alert(data.message || 'Error creating post');
                }
            })
            .catch(error => console.error('Error:', error));
        });
    }
});
