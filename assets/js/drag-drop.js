/**
 * Drag & Drop System
 * Hỗ trợ kéo thả files và elements
 */

function initDragDrop() {
    // File drop zones
    const dropZones = document.querySelectorAll('[data-drop-zone]');
    
    dropZones.forEach(zone => {
        zone.addEventListener('dragover', handleDragOver);
        zone.addEventListener('dragleave', handleDragLeave);
        zone.addEventListener('drop', handleDrop);
    });
    
    // Draggable elements
    const draggables = document.querySelectorAll('[data-draggable]');
    
    draggables.forEach(item => {
        item.draggable = true;
        item.addEventListener('dragstart', handleDragStart);
        item.addEventListener('dragend', handleDragEnd);
    });
}

function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.add('drag-over');
}

function handleDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.remove('drag-over');
}

function handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.remove('drag-over');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        handleFileDrop(e.currentTarget, files);
    }
    
    const data = e.dataTransfer.getData('text/plain');
    if (data) {
        handleElementDrop(e.currentTarget, data);
    }
}

function handleFileDrop(zone, files) {
    const fileType = zone.getAttribute('data-drop-zone');
    
    Array.from(files).forEach(file => {
        if (fileType === 'image' && file.type.startsWith('image/')) {
            handleImageUpload(zone, file);
        } else if (fileType === 'file') {
            handleFileUpload(zone, file);
        }
    });
}

function handleImageUpload(zone, file) {
    const reader = new FileReader();
    reader.onload = (e) => {
        const img = document.createElement('img');
        img.src = e.target.result;
        img.style.maxWidth = '100%';
        img.style.maxHeight = '300px';
        img.style.borderRadius = '8px';
        
        zone.innerHTML = '';
        zone.appendChild(img);
        
        // Trigger custom event
        zone.dispatchEvent(new CustomEvent('imageUploaded', {
            detail: { file, dataUrl: e.target.result }
        }));
        
        if (typeof QuickActions !== 'undefined' && QuickActions.showToast) {
            QuickActions.showToast('✅ Upload ảnh thành công!', 'success');
        }
    };
    reader.readAsDataURL(file);
}

function handleFileUpload(zone, file) {
    const fileInfo = document.createElement('div');
    fileInfo.className = 'uploaded-file';
    fileInfo.innerHTML = `
        <div class="file-icon">📄</div>
        <div class="file-info">
            <div class="file-name">${file.name}</div>
            <div class="file-size">${(file.size / 1024).toFixed(2)} KB</div>
        </div>
    `;
    
    zone.innerHTML = '';
    zone.appendChild(fileInfo);
    
    zone.dispatchEvent(new CustomEvent('fileUploaded', {
        detail: { file }
    }));
}

function handleDragStart(e) {
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', e.target.id || e.target.getAttribute('data-drag-id'));
    e.target.classList.add('dragging');
}

function handleDragEnd(e) {
    e.target.classList.remove('dragging');
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initDragDrop);

// Export
window.DragDrop = {
    initDragDrop,
    handleImageUpload,
    handleFileUpload
};

