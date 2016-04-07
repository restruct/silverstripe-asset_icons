<?php

class AssetAdmin_IconExtension extends Extension
{

    public function updateEditForm(& $form_or_field)
    {
        // make this work both on Compositefields & forms containing the actual grid;
        if ($form_or_field instanceof Form) { // AssetAdmin
            $grid = $form_or_field->Fields()->dataFieldByName('File');
        } else if ($form_or_field instanceof CompositeField) { // Uploadfield filepicker
            $grid = $form_or_field->fieldByName('Files');
        }
        $datacols = $grid->getConfig()->getComponentByType('GridFieldDataColumns');
        $dfields = $datacols->getDisplayFields($grid);

        // replace Title with Name as Title doesn't make sense, doesn't even include the extension
        $new_dfields = array();
        foreach ($dfields as $key => $val) {
            $key === 'Title' ? $new_dfields['Name'] = _t('File.Name', 'Name') : $new_dfields[$key] = $val;
        }
        $datacols->setDisplayFields($new_dfields);

        // Add filetype-thumbnails to files
        $grid->addDataFields(array(
            'StripThumbnail' => function($record){
                $iconpath = ASSET_ICONS_DIR.'/third-party/icons/32px/'.$record->getExtension().'.png';

                if($record->ClassName != 'File') {
                    // fallback to regular GridField::getDataFieldValue() handling
                    if($record->hasMethod('relField')) { return $record->relField('StripThumbnail'); }
                    if($record->hasMethod($fieldName)) { return $record->StripThumbnail(); }
                    return $record->StripThumbnail;

                } else if(file_exists(BASE_PATH.'/'.$iconpath)) {
                    // return "<img src=\"$iconpath\" />"; // gets escaped
                    return DBField::create_field('HTMLVarchar', '<img src="'.$iconpath.'"/>');
                }
            }
        ));

    }

}