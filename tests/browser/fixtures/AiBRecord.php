<?php

namespace Restruct\AiBrowser;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\ORM\DataObject;

/**
 * BROWSER-TEST FIXTURE ONLY - seeds the files the asset-admin specs look at, and a record whose
 * CMS form has an UploadField holding one of them.
 *
 * Never loaded by a real install: it lives under tests/browser/, which carries a _manifest_exclude
 * marker, and the browser-test runner copies it into a scratch host's app/ before dev/build.
 * Written to load on both Silverstripe 5 and 6 (no class imports that moved between the two).
 *
 * Every dev/build (the runner does one per run) empties the fixture folder and writes the files
 * again, so a run starts from the same folder whatever earlier runs did.
 *
 * @property string $Title
 * @method File Attachment()
 */
class AiBRecord extends DataObject
{
    private static $table_name = 'AiBRecord';

    private static $singular_name = 'Browser Record';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    private static $has_one = [
        'Attachment' => File::class,
    ];

    private static $owns = [
        'Attachment',
    ];

    private static $summary_fields = [
        'Title' => 'Title',
    ];

    /** The asset-admin folder the specs open (by name; its ID changes every dev/build). */
    public const FOLDER = 'aib-files';

    /**
     * Folder for the renderable PDF the second record holds, kept out of FOLDER so the asset-admin
     * specs' file list stays as it is. The PDF is real (AiBPreviewSeed::pdf()), so with previews
     * switched on (AiBVariantMiddleware) its UploadField item gets a rendered preview.
     */
    public const PREVIEW_FOLDER = 'aib-uploadfield';

    /**
     * The seeded files: name => content. One per icon the specs check, chosen so both kinds of
     * icon are covered: those core already marks with a broad category class (docx = document,
     * zip = archive, mp3 = audio), which the CSS styles before any JS runs, and those only the
     * module's data-ext attribute tells apart (pdf, xlsx - core files them under "document").
     * The PNG is an image: core renders its thumbnail and the module must leave it alone.
     */
    public static function seedFiles(): array
    {
        return [
            'report.pdf' => "%PDF-1.4\n% browser-test fixture, not rendered\n%%EOF\n",
            'notes.docx' => 'browser-test fixture',
            'sheet.xlsx' => 'browser-test fixture',
            'bundle.zip' => 'browser-test fixture',
            'song.mp3' => 'browser-test fixture',
            # A 1x1 transparent PNG.
            'pixel.png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='),
        ];
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['Attachment', 'AttachmentID']);
        $fields->addFieldToTab('Root.Main', UploadField::create('Attachment', 'Attachment'));
        return $fields;
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        $folder = Folder::find_or_make(self::FOLDER);
        foreach (File::get()->filter('ParentID', $folder->ID) as $old) {
            $old->doArchive();
        }

        $pdf = null;
        foreach (self::seedFiles() as $name => $content) {
            $class = File::get_class_for_file_extension(pathinfo($name, PATHINFO_EXTENSION));
            /** @var File $file */
            $file = $class::create();
            $file->setFromString($content, self::FOLDER . '/' . $name);
            $file->ParentID = $folder->ID;
            $file->Title = $name;
            $file->write();
            $file->publishSingle();
            if ($name === 'report.pdf') {
                $pdf = $file;
            }
        }

        # The renderable PDF for the "Rendered preview attachment" record. Its content carries the
        # build time (like AiBPreviewSeed), so the preview is rendered in this run.
        $previewFolder = Folder::find_or_make(self::PREVIEW_FOLDER);
        foreach (File::get()->filter('ParentID', $previewFolder->ID) as $old) {
            $old->doArchive();
        }
        $renderable = File::create();
        $renderable->setFromString(AiBPreviewSeed::pdf('UploadField preview ' . date('c')), self::PREVIEW_FOLDER . '/rendered.pdf');
        $renderable->ParentID = $previewFolder->ID;
        $renderable->Title = 'rendered.pdf';
        $renderable->write();
        $renderable->publishSingle();

        foreach (static::get() as $old) {
            $old->delete();
        }
        static::create(['Title' => 'PDF attachment', 'AttachmentID' => $pdf->ID])->write();
        static::create(['Title' => 'Rendered preview attachment', 'AttachmentID' => $renderable->ID])->write();
    }
}
