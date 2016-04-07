<?php

/*
 * Since there's not yet any extension hooks in Uploadfield & related classes, we'll do with the Injector instead
 */
class UploadField_SelectHandler_Iconized extends UploadField_SelectHandler
{

    protected function getListField($folderID)
    {
        $list = parent::getListField($folderID);

        // update with icons & filenames including extensions
        $ext = new AssetAdmin_IconExtension();
        $ext->updateEditForm($list);

        return $list;
    }

}