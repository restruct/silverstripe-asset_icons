document.addEventListener("DOMNodesInserted", function (event) {

    if(event.detail.type !== 'MOUNT' && event.detail.type !== 'MUTATION') {
        return;
    }

    // Small helper to prevent code duplication for both tiles & table view
    let applyToEditThumb = function(isHighlighted, itemData){
        // if highlighted/currently being edited, also set attr on editform-thumb
        if(isHighlighted && itemData.category==='document') {
            let thumbContainer = document.querySelector('.editor__details .editor__thumbnail-container');
            if(thumbContainer) thumbContainer.setAttribute('data-ext', itemData.extension);
        }
    };

    // gallery/tiles view
    for (const tile of document.querySelectorAll('.gallery__main-view--tile .gallery__files > div')){
        for (const property in tile) {
            if (property.startsWith('__reactInternalInstance')) {
                if (tile[property].pendingProps.children.props) {
                    let itemData = tile[property].pendingProps.children.props.item;
                    tile.setAttribute('data-ext', itemData.extension);
                    // if highlighted/currently being edited, also set attr on editform-thumb
                    applyToEditThumb(tile.querySelector('.gallery-item--highlighted'), itemData);
                }
            }
        }
    };
    // table/list view
    for (const row of document.querySelectorAll('.gallery__main-view--table tbody tr.gallery__table-row')){
        for (const property in row) {
            if(property.startsWith('__reactEventHandlers')){
                // if(this[property].children.props){
                if(row[property].children[1].props.children.props){
                    let itemData = row[property].children[1].props.children.props.rowData;
                    row.setAttribute('data-ext', itemData.extension);
                    row.querySelector('.gallery__table-column--title span').setAttribute('data-filename', itemData.filename);
                    row.querySelector('.gallery__table-column--title span').setAttribute('data-name', itemData.name);
                    // if highlighted/currently being edited, also set attr on editform-thumb
                    applyToEditThumb(row.classList.contains('gallery__table-row--highlighted'), itemData);
                }
            }
        }
    };

});

// __reactEventHandlers~/__reactInternalInstance~ .children.props.rowData
//     category: "document"
//     draft: true
//     exists: true
//     extension: "xlsx"
//     filename: "vergoedingen/Kopie-van-Zorgverzekeraars-vergoeding-2022-v2.xlsx"
//     hasRestrictedAccess: false
//     highlighted: false
//     id: 73
//     isTrackedFormUpload: false
//     key: "id73"
//     lastEdited: "2022-09-23 04:53:26"
//     modified: true
//     name: "Kopie-van-Zorgverzekeraars-vergoeding-2022-v2.xlsx"
//     parentId: 74