/**
 * Asset Icons - SS5/React 18 compatible
 *
 * Sets data-ext attributes on gallery items so CSS can apply category icons
 * and extension text overlays. Works with both tile and table views.
 *
 * Rendered preview thumbnails (for PDFs, SVGs, etc.) are populated server-side
 * via RenderableThumbnailGenerator into the GraphQL `thumbnail` field. However,
 * React's GalleryItem only applies backgroundImage for image-category files,
 * so this JS applies the thumbnail as inline style for non-image files that
 * have a rendered preview (itemData.thumbnail is truthy).
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
     * Walk fiber tree upward to find file data.
     * Checks for `item`, `rowData`, or `data` props containing extension info.
     * Returns the item data object or null.
     */
    function findItemData(el) {
        // First try direct props on the element
        const props = getProps(el);
        if (props) {
            if (props.item && props.item.extension) return props.item;
            if (props.rowData && props.rowData.extension) return props.rowData;
            if (props.data && props.data.extension) return props.data;
        }
        // Walk up the fiber tree
        let fiber = getFiber(el);
        let depth = 0;
        while (fiber && depth < 15) {
            const mp = fiber.memoizedProps;
            if (mp) {
                if (mp.item && mp.item.extension) return mp.item;
                if (mp.rowData && mp.rowData.extension) return mp.rowData;
                if (mp.data && mp.data.extension) return mp.data;
            }
            fiber = fiber.return;
            depth++;
        }
        return null;
    }

    /**
     * Apply data-ext to a highlighted item's edit form thumbnail.
     * Skip image items — SS generates real thumbnails for those.
     * Skip items with a GraphQL thumbnail — React already renders the preview,
     * and data-ext would trigger CSS that hides the <img> and shows an icon.
     */
    function applyToEditThumb(isHighlighted, itemData) {
        if (isHighlighted && itemData.category !== 'image') {
            var thumbContainer = document.querySelector('.editor__details .editor__thumbnail-container');
            if (thumbContainer && !itemData.thumbnail) {
                thumbContainer.setAttribute('data-ext', itemData.extension);
            }
        }
    }

    /**
     * Process all gallery items (tiles + table rows) and set data attributes.
     */
    function processGalleryItems() {
        // --- Tile view ---
        var tiles = document.querySelectorAll('.gallery__main-view--tile .gallery__files > div');
        for (var i = 0; i < tiles.length; i++) {
            var tile = tiles[i];
            if (tile.hasAttribute('data-ext')) continue; // already processed
            try {
                var itemData = findItemData(tile);
                if (!itemData || !itemData.extension) continue;
                // Skip image items — SS generates real thumbnails for those
                if (itemData.category === 'image') continue;

                tile.setAttribute('data-ext', itemData.extension);
                var thumb = tile.querySelector('.gallery-item__thumbnail');
                if (thumb) {
                    // Always set data-ext for category icon CSS fallback
                    thumb.setAttribute('data-ext', itemData.extension);
                    if (itemData.thumbnail) {
                        // React only applies backgroundImage for image category —
                        // apply the GraphQL thumbnail (rendered preview) ourselves.
                        // Inline style overrides the data-ext category icon CSS.
                        thumb.style.backgroundImage = "url('" + itemData.thumbnail + "')";
                        thumb.style.backgroundSize = "cover";
                        thumb.style.backgroundPosition = "center top";
                        // Flag for CSS badge — distinguishes previews from icons
                        thumb.setAttribute('data-preview', '');
                    }
                }

                applyToEditThumb(
                    tile.querySelector('.gallery-item--highlighted'),
                    itemData
                );
            } catch (e) {
                // Silently skip items where fiber traversal fails
            }
        }

        // --- Table view ---
        var rows = document.querySelectorAll('.gallery__main-view--table tbody tr.gallery__table-row');
        for (var j = 0; j < rows.length; j++) {
            var row = rows[j];
            if (row.hasAttribute('data-ext')) continue; // already processed
            try {
                var itemData = findItemData(row);
                if (!itemData || !itemData.extension) continue;
                // Skip image items — SS generates real thumbnails for those
                if (itemData.category === 'image') continue;

                row.setAttribute('data-ext', itemData.extension);
                var img = row.querySelector('.gallery__table-image');
                if (img) {
                    img.setAttribute('data-ext', itemData.extension);
                    if (itemData.thumbnail) {
                        img.style.backgroundImage = "url('" + itemData.thumbnail + "')";
                        img.style.backgroundSize = "contain";
                        img.style.backgroundPosition = "center";
                        img.setAttribute('data-preview', '');
                    }
                }

                var titleSpan = row.querySelector('.gallery__table-column--title span');
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

        // --- UploadField items ---
        var uploadItems = document.querySelectorAll('.uploadfield-item');
        for (var k = 0; k < uploadItems.length; k++) {
            var uploadItem = uploadItems[k];
            if (uploadItem.hasAttribute('data-ext')) continue; // already processed
            try {
                var itemData = findItemData(uploadItem);
                if (!itemData || !itemData.extension) continue;
                if (itemData.category === 'image') continue;

                uploadItem.setAttribute('data-ext', itemData.extension);
                var uploadThumb = uploadItem.querySelector('.uploadfield-item__thumbnail');
                if (uploadThumb) {
                    uploadThumb.setAttribute('data-ext', itemData.extension);
                    if (itemData.thumbnail) {
                        // React only applies backgroundImage for image category —
                        // apply the rendered preview ourselves
                        uploadThumb.style.backgroundImage = "url('" + itemData.thumbnail + "')";
                        uploadThumb.style.backgroundSize = "cover";
                        uploadThumb.style.backgroundPosition = "center top";
                    }
                }
            } catch (e) {
                // Silently skip items where fiber traversal fails
            }
        }

        // --- Edit form thumbnail ---
        // Also handle when edit panel is open but no tile/row is actively highlighted
        var editThumb = document.querySelector('.editor__details .editor__thumbnail-container');
        if (editThumb && !editThumb.hasAttribute('data-ext')) {
            // Find highlighted item in either view
            var highlighted = document.querySelector(
                '.gallery-item--highlighted, .gallery__table-row--highlighted'
            );
            if (highlighted) {
                try {
                    var itemData = findItemData(highlighted);
                    if (itemData && itemData.extension && itemData.category !== 'image' && !itemData.thumbnail) {
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
