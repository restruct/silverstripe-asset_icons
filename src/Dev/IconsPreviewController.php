<?php

namespace Restruct\SilverStripe\AssetIcons\Dev;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Manifest\ModuleResourceLoader;

/**
 * Dev controller to preview all asset icon categories with sample extension overlays.
 * Access at: /admin/asset-icons-preview
 */
class IconsPreviewController extends LeftAndMain
{
    private static $url_segment = 'asset-icons-preview';

    private static $menu_title = 'Asset Icons';

    private static $allowed_actions = [
        'index' => 'CMS_ACCESS_CMSMain',
    ];

    // Hide from CMS menu — dev tool only
    private static $ignore_menuitem = true;

    private const MODULE = 'restruct/silverstripe-asset_icons';

    // Colors distributed across spectrum (matching CLAUDE.md)
    private const CATEGORIES = [
        ['name' => 'blank',        'bg' => ['#d8dce0', '#b8c0c8'], 'fg' => '#505860', 'desc' => 'Default / unknown',   'exts' => '(fallback for unmapped extensions)'],
        ['name' => 'pdf',          'bg' => ['#f5b8b8', '#e89090'], 'fg' => '#a01515', 'desc' => 'PDF',                 'exts' => 'pdf'],
        ['name' => 'document',     'bg' => ['#b8d4f5', '#90bce8'], 'fg' => '#0055a0', 'desc' => 'Documents',           'exts' => 'doc, docx, docm, dotx, odt, ott, rtf, txt, md, pages, wpd, wps'],
        ['name' => 'spreadsheet',  'bg' => ['#c8f5b8', '#a0e890'], 'fg' => '#308010', 'desc' => 'Spreadsheets',        'exts' => 'xls, xlsx, xlsm, xltx, ods, ots, csv, tsv, numbers'],
        ['name' => 'presentation', 'bg' => ['#f5d4b8', '#e8bc90'], 'fg' => '#a05010', 'desc' => 'Presentations',       'exts' => 'ppt, pptx, pptm, potx, odp, otp, key'],
        ['name' => 'archive',      'bg' => ['#f5ecb8', '#e8dc90'], 'fg' => '#907000', 'desc' => 'Archives',            'exts' => 'zip, 7z, rar, tar, gz, tgz, bz2, xz, cab, jar, war, deb, rpm'],
        ['name' => 'audio',        'bg' => ['#d8b8f5', '#c090e8'], 'fg' => '#6020a0', 'desc' => 'Audio',               'exts' => 'mp3, wav, aac, flac, ogg, opus, m4a, wma, aiff, aif, mid, midi'],
        ['name' => 'video',        'bg' => ['#f5b8e8', '#e890d4'], 'fg' => '#a01080', 'desc' => 'Video',               'exts' => 'mp4, avi, mov, mkv, webm, flv, wmv, mpg, mpeg, m4v, 3gp, ogv, vob'],
        ['name' => 'code',         'bg' => ['#b8ecf5', '#90dce8'], 'fg' => '#008090', 'desc' => 'Code',                'exts' => 'js, jsx, ts, tsx, php, py, rb, java, c, cpp, cs, go, rs, swift, sh, bat, ...'],
        ['name' => 'markup',       'bg' => ['#c8d0d8', '#a8b4c0'], 'fg' => '#405060', 'desc' => 'Markup / config',     'exts' => 'html, htm, xml, json, yaml, yml, toml, ini, cfg, css, scss, sass, less'],
        ['name' => 'vector',       'bg' => ['#c8b8f5', '#a890e8'], 'fg' => '#5030a0', 'desc' => 'Vector graphics',    'exts' => 'ai, eps, svg'],
        ['name' => 'image',        'bg' => ['#b8e0f5', '#90c8e8'], 'fg' => '#0070a0', 'desc' => 'Bitmap / raster',    'exts' => 'psd, raw, cr2, cr3, nef, dng, tif, tiff, bmp, tga, xcf, indd, sketch'],
        ['name' => 'cad',          'bg' => ['#b8f5c8', '#90e8a8'], 'fg' => '#108030', 'desc' => 'CAD / 3D',            'exts' => 'dwg, dxf, vsd, vsdx, vdx, vst, skp, blend, 3ds, fbx, obj, stl, step, stp, iges'],
        ['name' => 'database',     'bg' => ['#b8f5ec', '#90e8dc'], 'fg' => '#008080', 'desc' => 'Database',            'exts' => 'sql, db, sqlite, sqlite3, mdb, accdb, dbf, odb'],
        ['name' => 'font',         'bg' => ['#e8b8f5', '#d490e8'], 'fg' => '#8020a0', 'desc' => 'Fonts',               'exts' => 'ttf, otf, woff, woff2, eot, pfb, pfm'],
        ['name' => 'system',       'bg' => ['#d0d4d8', '#b0b8c0'], 'fg' => '#404850', 'desc' => 'System / executable', 'exts' => 'exe, dll, bin, iso, dmg, app, msi, sys, apk, ipa'],
        ['name' => 'ebook',        'bg' => ['#e8d8c0', '#d4c0a0'], 'fg' => '#705020', 'desc' => 'E-books',             'exts' => 'epub, mobi, azw, azw3, cbz, cbr, djvu'],
        ['name' => 'plc',          'bg' => ['#dce0b8', '#c8cc90'], 'fg' => '#606010', 'desc' => 'PLC / industrial',    'exts' => 'e80, lsc, zap15_1, awl, gxw, acd, s7p, ap17-20, zap18-20, scl, udt, aml, tia, ...'],
    ];

    /**
     * Resolve a module icon path to a public URL.
     */
    private function iconURL(string $filename): string
    {
        return ModuleResourceLoader::resourceURL(
            self::MODULE . ':client/icons/' . $filename
        );
    }

    public function index($request): HTTPResponse
    {
        $cardsHtml = '';
        foreach (self::CATEGORIES as $cat) {
            $name = htmlspecialchars($cat['name']);
            $fg = htmlspecialchars($cat['fg']);
            $desc = htmlspecialchars($cat['desc']);
            $iconUrl = $this->iconURL($name . '.svg');

            // Extension badges (first 5)
            $sampleExts = array_slice(array_map('trim', explode(',', $cat['exts'])), 0, 5);
            $badgesHtml = '';
            foreach ($sampleExts as $ext) {
                $ext = trim(htmlspecialchars($ext));
                if (str_starts_with($ext, '(')) {
                    continue;
                }
                $badgesHtml .= "<span class=\"badge\" style=\"background:{$fg};\">{$ext}</span>";
            }

            $cardsHtml .= <<<HTML
            <div class="icon-card">
                <img src="{$iconUrl}" alt="{$name}">
                <div class="label">{$name}</div>
                <div class="desc">{$desc}</div>
                <div class="badges">{$badgesHtml}</div>
            </div>
HTML;
        }

        $baseUrl = Director::absoluteBaseURL();
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <base href="{$baseUrl}">
    <title>Asset Icons Preview</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f4f6f8; margin: 0; padding: 2rem; color: #333; }
        h1 { font-size: 1.4rem; margin-bottom: .25rem; }
        .subtitle { color: #666; font-size: .85rem; margin-bottom: 1.5rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; }
        .icon-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            background: #fff;
            border: 1px solid #e2e6ea;
            border-radius: 8px;
            padding: 1.25rem 1rem 1rem;
            transition: box-shadow .15s;
        }
        .icon-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
        .icon-card img { width: 64px; height: auto; margin-bottom: .5rem; }
        .icon-card .label { font-size: .85rem; font-weight: 600; color: #333; margin-bottom: .15rem; }
        .icon-card .desc { font-size: .7rem; color: #888; margin-bottom: .6rem; }
        .icon-card .badges { display: flex; flex-wrap: wrap; justify-content: center; gap: 3px; }
        .badge { display: inline-block; color: #fff; font-size: .55rem; font-weight: 700; text-transform: uppercase; padding: 2px 5px; border-radius: 3px; letter-spacing: .02em; }
    </style>
</head>
<body>
    <h1>Asset Icons &mdash; Category Preview</h1>
    <p class="subtitle">18 categories &middot; Light pastel backgrounds &middot; Dark saturated foregrounds</p>
    <div class="grid">
        {$cardsHtml}
    </div>
</body>
</html>
HTML;

        return HTTPResponse::create($html)->addHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
