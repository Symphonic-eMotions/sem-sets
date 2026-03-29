(function () {
    // Toggle between display and edit mode
    window.toggleAssetRename = function (assetId) {
        const editContainer = document.getElementById('chip-edit-' + assetId);
        const displaySpan = document.querySelector('[data-asset-id="' + assetId + '"]')?.closest('.chip-name-wrapper')?.querySelector('.chip-name-display');

        if (!editContainer || !displaySpan) return;

        const isHidden = editContainer.hasAttribute('hidden');

        if (isHidden) {
            // Show edit mode
            editContainer.removeAttribute('hidden');
            displaySpan.style.display = 'none';
            const input = editContainer.querySelector('.rename-input');
            if (input) {
                input.focus();
                input.select();
            }
        } else {
            // Hide edit mode
            editContainer.setAttribute('hidden', '');
            displaySpan.style.display = 'block';
        }
    };

    // Save asset rename
    window.saveAssetRename = function (assetId, documentId) {
        const editContainer = document.getElementById('chip-edit-' + assetId);
        const input = editContainer?.querySelector('.rename-input');

        if (!input) return;

        const newName = input.value.trim();

        // Validation
        if (!newName) {
            alert('Naam mag niet leeg zijn.');
            return;
        }

        if (newName.length > 255) {
            alert('Naam is te lang (max 255 karakters).');
            return;
        }

        const csrfToken = input.getAttribute('data-csrf-token');

        // Show loading state
        const btn = editContainer.querySelector('[onclick*="saveAssetRename"]');
        if (btn) btn.disabled = true;

        // POST to rename endpoint
        fetch(`/documents/${documentId}/assets/${assetId}/rename`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                displayName: newName,
                _token: csrfToken
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    // Update display name in DOM
                    const displaySpan = editContainer.closest('.chip-name-wrapper')?.querySelector('.chip-name-display');
                    if (displaySpan) {
                        // Keep the prefix (↳ track label) if it exists
                        const prefix = displaySpan.innerHTML.match(/^(.*?↳.*?<\/span>)/);
                        displaySpan.innerHTML = (prefix ? prefix[0] : '') + newName;
                    }

                    // Close edit mode
                    cancelAssetRename(assetId);
                } else {
                    alert('Fout: ' + (data.error || 'Hernoemen mislukt'));
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Fout bij hernoemen: ' + err.message);
            })
            .finally(() => {
                if (btn) btn.disabled = false;
            });
    };

    // Cancel rename (close edit mode)
    window.cancelAssetRename = function (assetId) {
        const editContainer = document.getElementById('chip-edit-' + assetId);
        const displaySpan = editContainer?.closest('.chip-name-wrapper')?.querySelector('.chip-name-display');

        if (!editContainer || !displaySpan) return;

        // Hide edit mode
        editContainer.setAttribute('hidden', '');
        displaySpan.style.display = 'block';

        // Reset input to original value
        const input = editContainer.querySelector('.rename-input');
        if (input) {
            // Get original name from the document (from data attribute or DOM)
            const originalValue = input.getAttribute('value');
            input.value = originalValue || '';
        }
    };

    // Allow Enter to save, Escape to cancel
    document.addEventListener('keydown', function (e) {
        const input = e.target;
        if (!input.classList.contains('rename-input')) return;

        if (e.key === 'Enter') {
            e.preventDefault();
            const assetId = parseInt(input.getAttribute('data-asset-id'), 10);
            const documentId = parseInt(input.getAttribute('data-document-id'), 10);
            saveAssetRename(assetId, documentId);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            const assetId = parseInt(input.getAttribute('data-asset-id'), 10);
            cancelAssetRename(assetId);
        }
    });
})();
