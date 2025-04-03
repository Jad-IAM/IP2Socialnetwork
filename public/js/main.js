/**
 * Main JavaScript file for IP2∞ forum
 */

document.addEventListener('DOMContentLoaded', function() {
    // Character counter for status updates
    const statusTextarea = document.querySelector('.status-textarea');
    const statusButton = document.querySelector('.status-button');
    
    if (statusTextarea && statusButton) {
        statusTextarea.addEventListener('input', function() {
            if (this.value.trim().length > 0) {
                statusButton.removeAttribute('disabled');
            } else {
                statusButton.setAttribute('disabled', 'disabled');
            }
        });
    }
    
    // Add Flair button
    const addFlairButton = document.getElementById('add-flair');
    if (addFlairButton) {
        addFlairButton.addEventListener('click', function() {
            fetch('get_flairs.php')
                .then(response => response.json())
                .then(flairs => {
                    if (flairs.length === 0) {
                        console.error('Error loading flairs:', flairs);
                        return;
                    }
                    
                    // Create a dropdown for flair selection
                    const dropdown = document.createElement('div');
                    dropdown.className = 'flair-dropdown';
                    dropdown.style.position = 'absolute';
                    dropdown.style.backgroundColor = 'var(--content-bg)';
                    dropdown.style.border = '1px solid var(--border-color)';
                    dropdown.style.borderRadius = '4px';
                    dropdown.style.padding = '10px';
                    dropdown.style.zIndex = '100';
                    dropdown.style.maxHeight = '200px';
                    dropdown.style.overflowY = 'auto';
                    
                    flairs.forEach(flair => {
                        const flairOption = document.createElement('div');
                        flairOption.className = 'flair-option';
                        flairOption.textContent = flair;
                        flairOption.style.padding = '5px 10px';
                        flairOption.style.cursor = 'pointer';
                        flairOption.style.borderRadius = '3px';
                        
                        flairOption.addEventListener('mouseover', function() {
                            this.style.backgroundColor = 'var(--hover-bg)';
                        });
                        
                        flairOption.addEventListener('mouseout', function() {
                            this.style.backgroundColor = 'transparent';
                        });
                        
                        flairOption.addEventListener('click', function() {
                            // Add flair to the beginning of the text
                            if (statusTextarea.value.length > 0 && !statusTextarea.value.startsWith('[' + flair + '] ')) {
                                statusTextarea.value = '[' + flair + '] ' + statusTextarea.value;
                            } else {
                                statusTextarea.value = '[' + flair + '] ';
                            }
                            
                            // Remove dropdown
                            dropdown.remove();
                            
                            // Focus textarea and enable button
                            statusTextarea.focus();
                            statusButton.removeAttribute('disabled');
                        });
                        
                        dropdown.appendChild(flairOption);
                    });
                    
                    // Position dropdown below the button
                    const buttonRect = addFlairButton.getBoundingClientRect();
                    dropdown.style.top = (buttonRect.bottom + window.scrollY) + 'px';
                    dropdown.style.left = (buttonRect.left + window.scrollX) + 'px';
                    
                    // Add dropdown to page
                    document.body.appendChild(dropdown);
                    
                    // Close dropdown when clicking outside
                    document.addEventListener('click', function closeDropdown(e) {
                        if (!dropdown.contains(e.target) && e.target !== addFlairButton) {
                            dropdown.remove();
                            document.removeEventListener('click', closeDropdown);
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading flairs:', error);
                });
        });
    }
    
    // Add image button
    const addImageButton = document.getElementById('add-image');
    if (addImageButton) {
        addImageButton.addEventListener('click', function() {
            const imageUrl = prompt('Enter image URL:');
            if (imageUrl && imageUrl.trim().length > 0) {
                statusTextarea.value += '\n[image:' + imageUrl.trim() + ']';
                statusButton.removeAttribute('disabled');
            }
        });
    }
    
    // Add video button
    const addVideoButton = document.getElementById('add-video');
    if (addVideoButton) {
        addVideoButton.addEventListener('click', function() {
            window.location.href = 'upload.php';
        });
    }
});
