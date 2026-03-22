function showFullImage(src) {
    document.getElementById('modalImage').src = src;
    document.getElementById('imageModal').style.display = 'block';
}

function hideFullImage() {
    document.getElementById('imageModal').style.display = 'none';
}

// Close modal on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && document.getElementById('imageModal').style.display === 'block') {
        hideFullImage();
    }
});
