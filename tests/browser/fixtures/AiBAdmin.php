<?php

namespace Restruct\AiBrowser;

use SilverStripe\Admin\ModelAdmin;

/**
 * BROWSER-TEST FIXTURE ONLY - the CMS screen for the UploadField spec: /admin/aib-browser/records
 * (see AiBRecord for why this never loads in a real install).
 */
class AiBAdmin extends ModelAdmin
{
    private static $url_segment = 'aib-browser';

    private static $menu_title = 'Asset icons browser test';

    # Keyed managed_models (SS5 and SS6): 'records' becomes the URL segment.
    private static $managed_models = [
        'records' => [
            'dataClass' => AiBRecord::class,
            'title' => 'Records',
        ],
    ];
}
