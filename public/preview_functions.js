
function openPreviewInNewTab() {
    const iframe = document.getElementById('editorPreview');
    
    if (iframe && iframe.contentDocument && iframe.contentDocument.body.innerHTML.trim() !== '') {
        const newWindow = window.open('', '_blank');
        newWindow.document.write(iframe.contentDocument.documentElement.outerHTML);
        newWindow.document.close();
    } else {
        alert('Please run the code first to see the preview.');
    }
}



function toggleFullscreenPreview() {
    const iframe = document.getElementById('editorPreview');
    let fullscreenDiv = document.getElementById('fullscreenPreviewContainer');
    
    if (fullscreenDiv) {
        fullscreenDiv.remove();
    } else {
        
        if (!iframe || !iframe.contentDocument || iframe.contentDocument.body.innerHTML.trim() === '') {
            alert('Please run the code first to see the preview.');
            return;
        }
        
        fullscreenDiv = document.createElement('div');
        fullscreenDiv.id = 'fullscreenPreviewContainer';
        fullscreenDiv.className = 'fullscreen-preview';
        fullscreenDiv.innerHTML = '<div class="fullscreen-preview-header"><h5>Preview Output</h5><button onclick="toggleFullscreenPreview()" class="btn btn-outline-secondary btn-sm">Close</button></div><div class="fullscreen-preview-content"><iframe id="fullscreenPreviewIframe" style="width:100%;height:100%;border:none;"></iframe></div>';
        
        document.body.appendChild(fullscreenDiv);
        
        const fullscreenIframe = document.getElementById('fullscreenPreviewIframe');
        
        const content = iframe.contentDocument.documentElement.outerHTML;
        fullscreenIframe.srcdoc = content;
    }
}
