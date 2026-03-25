Dropzone.autoDiscover = false;

const dropzone = new Dropzone("#picture-dropzone", {
    url: "#",
    maxFiles: 1,
    acceptedFiles: "image/jpeg,image/png,image/webp",
    maxFilesize: 40, // MB
    autoProcessQueue: false,
    addRemoveLinks: true,
    dictRemoveFile: "Remove",
    previewsContainer: "#picture-dropzone",
    thumbnailWidth: 400,
    thumbnailHeight: 400,
    maxThumbnailFilesize: 40,
    
    init: function() {
        const formElement = document.getElementById('identify-form');
        const fileInput = document.querySelector('input[id*="picture"]');
        
        // Handle form submission
        formElement.addEventListener('submit', (e) => {
            if (this.getQueuedFiles().length === 0) {
                if (fileInput.files.length === 0) {
                    e.preventDefault();
                    alert('Please select a file first');
                }
            }
        });
        
        // Update hidden input when file is added
        this.on('addedfile', (file) => {
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            fileInput.files = dataTransfer.files;
        });
        
        // Update hidden input when file is removed
        this.on('removedfile', (file) => {
            const dataTransfer = new DataTransfer();
            fileInput.files = dataTransfer.files;
        });

        this.on("maxfilesexceeded", function(file) {
            this.removeAllFiles();
            this.addFile(file);
        });
    }
});