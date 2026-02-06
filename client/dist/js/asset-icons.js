/**
 * Asset Icons - SS5/React 18 compatible
 *
 * Sets data-ext attributes on gallery items so CSS can apply category icons
 * and extension text overlays. Works with both tile and table views.
 *
 * React 18 uses __reactFiber$ and __reactProps$ (with random suffix)
 * instead of React 16's __reactInternalInstance / __reactEventHandlers.
 */
(function () {

    /**
     * Get React fiber node from a DOM element.
     * React 18 stores fiber as __reactFiber$<random> on the DOM node.
     */
    function getFiber(el) {
        for (const key in el) {
            if (key.startsWith('__reactFiber$')) {
                return el[key];
            }
        }
        return null;
    }

    /**
     * Get React props from a DOM element.
     * React 18 stores props as __reactProps$<random> on the DOM node.
     */
    function getProps(el) {
        for (const key in el) {
            if (key.startsWith('__reactProps$')) {
                return el[key];
            }
        }
        return null;
    }

    /**
     * Walk fiber tree upward to find a fiber whose props contain `item` or `rowData`.
     * Returns the item data object or null.
     */
    function findItemData(el) {
        // First try direct props on the element
        const props = getProps(el);
        if (props) {
            if (props.item && props.item.extension) return props.item;
            if (props.rowData && props.rowData.extension) return props.rowData;
        }
        // Walk up the fiber tree
        let fiber = getFiber(el);
        let depth = 0;
        while (fiber && depth < 15) {
            const mp = fiber.memoizedProps;
            if (mp) {
                if (mp.item && mp.item.extension) return mp.item;
                if (mp.rowData && mp.rowData.extension) return mp.rowData;
            }
            fiber = fiber.return;
            depth++;
        }
        return null;
    }

    /**
     * Apply data-ext to a highlighted item's edit form thumbnail.
     * Skip image items — SS generates real thumbnails for those.
     */
    function applyToEditThumb(isHighlighted, itemData) {
        if (isHighlighted && itemData.category !== 'image') {
            const thumbContainer = document.querySelector('.editor__details .editor__thumbnail-container');
            if (thumbContainer) {
                thumbContainer.setAttribute('data-ext', itemData.extension);
            }
        }
    }

    /**
     * Process all gallery items (tiles + table rows) and set data attributes.
     */
    function processGalleryItems() {
        // --- Tile view ---
        const tiles = document.querySelectorAll('.gallery__main-view--tile .gallery__files > div');
        for (const tile of tiles) {
            if (tile.hasAttribute('data-ext')) continue; // already processed
            try {
                const itemData = findItemData(tile);
                if (!itemData || !itemData.extension) continue;
                // Skip image items — SS generates real thumbnails for those
                if (itemData.category === 'image') continue;

                tile.setAttribute('data-ext', itemData.extension);
                const thumb = tile.querySelector('.gallery-item__thumbnail');
                if (thumb) thumb.setAttribute('data-ext', itemData.extension);

                applyToEditThumb(
                    tile.querySelector('.gallery-item--highlighted'),
                    itemData
                );
            } catch (e) {
                // Silently skip items where fiber traversal fails
            }
        }

        // --- Table view ---
        const rows = document.querySelectorAll('.gallery__main-view--table tbody tr.gallery__table-row');
        for (const row of rows) {
            if (row.hasAttribute('data-ext')) continue; // already processed
            try {
                const itemData = findItemData(row);
                if (!itemData || !itemData.extension) continue;
                // Skip image items — SS generates real thumbnails for those
                if (itemData.category === 'image') continue;

                row.setAttribute('data-ext', itemData.extension);
                const img = row.querySelector('.gallery__table-image');
                if (img) img.setAttribute('data-ext', itemData.extension);

                const titleSpan = row.querySelector('.gallery__table-column--title span');
                if (titleSpan) {
                    titleSpan.setAttribute('data-filename', itemData.filename || '');
                    titleSpan.setAttribute('data-name', itemData.name || '');
                }

                applyToEditThumb(
                    row.classList.contains('gallery__table-row--highlighted'),
                    itemData
                );
            } catch (e) {
                // Silently skip items where fiber traversal fails
            }
        }

        // --- Edit form thumbnail ---
        // Also handle when edit panel is open but no tile/row is actively highlighted
        const editThumb = document.querySelector('.editor__details .editor__thumbnail-container');
        if (editThumb && !editThumb.hasAttribute('data-ext')) {
            // Find highlighted item in either view
            const highlighted = document.querySelector(
                '.gallery-item--highlighted, .gallery__table-row--highlighted'
            );
            if (highlighted) {
                try {
                    const itemData = findItemData(highlighted);
                    if (itemData && itemData.extension && itemData.category !== 'image') {
                        editThumb.setAttribute('data-ext', itemData.extension);
                    }
                } catch (e) {}
            }
        }
    }

    // Listen for DOMNodesInserted events from restruct/silverstripe-simpler
    document.addEventListener('DOMNodesInserted', function (event) {
        if (event.detail.type !== 'MOUNT' && event.detail.type !== 'MUTATION') {
            return;
        }
        // Use rAF to let React finish rendering before we read fiber data
        requestAnimationFrame(processGalleryItems);
    });

})();
