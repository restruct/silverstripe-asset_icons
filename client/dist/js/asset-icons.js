/**
 * Asset Icons - SS5/React 18 compatible
 *
 * Sets data-ext attributes on gallery items so CSS can apply category icons
 * and extension text overlays. Works with both tile and table views.
 *
 * Also applies rendered preview thumbnails (from RenderablePreviewExtension)
 * for PDFs, SVGs, and other renderable file types. Preview URLs are derived
 * from the file URL (inserting __renderedpreview variant) and verified via
 * Image preload before applying.
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

    // -----------------------------------------------------------------------
    // Rendered preview support
    //
    // The GraphQL data doesn't include the rendered preview URL (thumbnail
    // is null for non-images). Instead, we derive the variant URL from the
    // file's public URL and verify it exists via Image preload.
    // -----------------------------------------------------------------------

    // Extensions that may have rendered previews (matches PHP config)
    var RENDERABLE_EXTS = ['pdf', 'eps', 'ps', 'ai', 'svg', 'svgz'];

    // Cache: variant URL -> true (exists) / false (doesn't exist)
    var previewChecked = {};

    // Collected preview URLs to inject as CSS rules (elId -> url)
    var previewUrls = {};

    /**
     * Derive the __renderedpreview variant URL from a file's public URL.
     * Keeps original extension — the variant is PNG data but stored with the
     * source extension (AssetStore requires matching base filename).
     * CSS background-image does MIME sniffing so it renders PNG bytes fine.
     * E.g. /assets/document.pdf -> /assets/document__renderedpreview.pdf
     */
    function derivePreviewUrl(fileUrl) {
        if (!fileUrl) return null;
        return fileUrl.replace(/(\.[^.]+)$/, '__renderedpreview$1');
    }

    /**
     * Check if a rendered preview exists for a file by preloading the variant
     * URL as an Image. On success, add it to previewUrls and update styles.
     * Results are cached so each URL is only checked once.
     */
    function checkRenderedPreview(elId, fileUrl, ext) {
        if (RENDERABLE_EXTS.indexOf(ext) === -1) return;
        if (!fileUrl) return;

        var previewUrl = derivePreviewUrl(fileUrl);
        if (!previewUrl) return;

        // Already confirmed — add to styles immediately
        if (previewChecked[previewUrl] === true) {
            previewUrls[elId] = previewUrl;
            return;
        }

        // Already checked and failed — skip
        if (previewChecked[previewUrl] === false) return;

        // First time seeing this URL — HEAD request to verify it exists.
        // Using fetch() instead of Image() because the variant file has the
        // original extension (.pdf) but contains PNG data. Image() might reject
        // the wrong Content-Type, but fetch() just checks HTTP status.
        // CSS background-image does its own MIME sniffing for the actual rendering.
        previewChecked[previewUrl] = false; // mark as in-progress
        fetch(previewUrl, { method: 'HEAD' })
            .then(function (response) {
                if (response.ok) {
                    previewChecked[previewUrl] = true;
                    previewUrls[elId] = previewUrl;
                    updatePreviewStyles();
                }
            })
            .catch(function () {
                // Failed — previewChecked stays false, no style update
            });
    }

    /**
     * Inject/update a <style> block with background-image rules for rendered previews.
     * Targets the React-stable #selectableItem-{id} wrapper IDs so rules survive
     * React re-renders (which wipe inline styles but preserve element IDs).
     */
    function updatePreviewStyles() {
        var styleEl = document.getElementById('asset-icons-rendered-previews');
        if (!styleEl) {
            styleEl = document.createElement('style');
            styleEl.id = 'asset-icons-rendered-previews';
            document.head.appendChild(styleEl);
        }

        var rules = [];
        for (var elId in previewUrls) {
            var url = previewUrls[elId];
            if (elId.indexOf('table-preview-') === 0) {
                // Table view: target image div via data-preview-id attribute
                rules.push(
                    '[data-preview-id="' + elId + '"] {'
                    + ' background-image: url(\'' + url + '\') !important;'
                    + ' background-size: contain !important;'
                    + ' background-position: center !important;'
                    + '}'
                );
            } else {
                // Tile view: target thumbnail inside #selectableItem-{id} wrapper
                rules.push(
                    '#' + elId + ' .gallery-item__thumbnail {'
                    + ' background-image: url(\'' + url + '\') !important;'
                    + ' background-size: cover !important;'
                    + ' background-position: center top !important;'
                    + '}'
                );
            }
        }

        styleEl.textContent = rules.join('\n');
    }

    /**
     * Apply rendered preview to the edit panel thumbnail (right sidebar).
     */
    function applyPreviewToEditThumb(thumbContainer, fileUrl, ext) {
        if (RENDERABLE_EXTS.indexOf(ext) === -1) return;
        var previewUrl = derivePreviewUrl(fileUrl);
        if (!previewUrl) return;

        // If already confirmed, apply immediately
        if (previewChecked[previewUrl] === true) {
            thumbContainer.style.backgroundImage = "url('" + previewUrl + "')";
            thumbContainer.style.backgroundSize = 'contain';
            thumbContainer.style.backgroundPosition = 'center';
            thumbContainer.setAttribute('data-rendered-preview', '');
            return;
        }

        // Check and apply async via fetch() HEAD (same reasoning as checkRenderedPreview)
        fetch(previewUrl, { method: 'HEAD' })
            .then(function (response) {
                if (response.ok) {
                    previewChecked[previewUrl] = true;
                    thumbContainer.style.backgroundImage = "url('" + previewUrl + "')";
                    thumbContainer.style.backgroundSize = 'contain';
                    thumbContainer.style.backgroundPosition = 'center';
                    thumbContainer.setAttribute('data-rendered-preview', '');
                }
            })
            .catch(function () {
                // Failed — no preview for this file
            });
    }

    /**
     * Apply data-ext to a highlighted item's edit form thumbnail.
     * Skip image items — SS generates real thumbnails for those.
     */
    function applyToEditThumb(isHighlighted, itemData) {
        if (isHighlighted && itemData.category !== 'image') {
            var thumbContainer = document.querySelector('.editor__details .editor__thumbnail-container');
            if (thumbContainer) {
                thumbContainer.setAttribute('data-ext', itemData.extension);
                applyPreviewToEditThumb(thumbContainer, itemData.url, itemData.extension);
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
                if (thumb) thumb.setAttribute('data-ext', itemData.extension);

                // Check for rendered preview (async — applies via <style> on success)
                if (tile.id) {
                    checkRenderedPreview(tile.id, itemData.url, itemData.extension);
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
                if (img) img.setAttribute('data-ext', itemData.extension);

                var titleSpan = row.querySelector('.gallery__table-column--title span');
                if (titleSpan) {
                    titleSpan.setAttribute('data-filename', itemData.filename || '');
                    titleSpan.setAttribute('data-name', itemData.name || '');
                }

                // Table rows don't have #selectableItem-{id} wrappers.
                // Set a data-preview-id on the image div for CSS targeting.
                if (itemData.id && img) {
                    var previewId = 'table-preview-' + itemData.id;
                    img.setAttribute('data-preview-id', previewId);
                    checkRenderedPreview(previewId, itemData.url, itemData.extension);
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
        var editThumb = document.querySelector('.editor__details .editor__thumbnail-container');
        if (editThumb && !editThumb.hasAttribute('data-ext')) {
            // Find highlighted item in either view
            var highlighted = document.querySelector(
                '.gallery-item--highlighted, .gallery__table-row--highlighted'
            );
            if (highlighted) {
                try {
                    var itemData = findItemData(highlighted);
                    if (itemData && itemData.extension && itemData.category !== 'image') {
                        editThumb.setAttribute('data-ext', itemData.extension);
                        applyPreviewToEditThumb(editThumb, itemData.url, itemData.extension);
                    }
                } catch (e) {}
            }
        }

        // Update the injected <style> with any synchronously-resolved previews
        updatePreviewStyles();
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
