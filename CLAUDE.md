# CLAUDE.md - Asset Icons Module

This module provides file type icons for SilverStripe's Asset Admin, replacing the default generic icons with category-colored SVG icons.

## File Structure

```
client/
├── icons/              # Individual SVG icons used by the module
│   ├── blank.svg
│   ├── pdf.svg
│   ├── document.svg
│   └── ... (17 total)
├── icons-source.svg    # Master source file with all icons (for editing in Inkscape)
├── src/styles/
│   └── asset-icons.scss
└── dist/
    └── css/
        └── asset-icons.css
```

## Icon Source File

The `client/icons-source.svg` is a combined Inkscape file containing all 17 category icons. It's a single-line XML format for easy sed/regex processing.

### Element Structure

Each icon consists of two elements:
1. **Background path** — Uses `fill:url(#grad-{category})` gradient
2. **Foreground path** — Has `id="file-{category}"` with solid `fill:#{color}`

### Element IDs

| Category | Source ID | Output Filename | Notes |
|----------|-----------|-----------------|-------|
| blank | `file-blank` | blank.svg | |
| pdf | `file-pdf` | pdf.svg | |
| document | `file-document` | document.svg | |
| spreadsheet | `file-spreadsheet` | spreadsheet.svg | |
| presentation | `file-presentation` | presentation.svg | |
| bitmap | `file-bitmap` | **image.svg** | Renamed for SCSS |
| vector | `file-vector` | vector.svg | Not used in SCSS |
| cad | `file-cad` | cad.svg | |
| database | `file-database` | database.svg | |
| font | `file-font` | font.svg | |
| binary | `file-binary` | **system.svg** | Renamed for SCSS |
| archive | `file-archive` | archive.svg | |
| audio | `file-audio` | audio.svg | |
| video | `file-video` | video.svg | |
| code | `file-code` | code.svg | |
| markup | `file-markup` | markup.svg | |
| ebook | `file-ebook` | ebook.svg | |
| plc | `file-plc` | plc.svg | |

## Color Scheme

Colors are distributed across the spectrum for maximum differentiation:

| Category | Hue | Background Light | Background Dark | Foreground |
|----------|-----|------------------|-----------------|------------|
| blank | Gray | #d8dce0 | #b8c0c8 | #505860 |
| pdf | Red 0° | #f5b8b8 | #e89090 | #a01515 |
| presentation | Orange 30° | #f5d4b8 | #e8bc90 | #a05010 |
| archive | Yellow 45° | #f5ecb8 | #e8dc90 | #907000 |
| spreadsheet | Lime 90° | #c8f5b8 | #a0e890 | #308010 |
| cad | Green 120° | #b8f5c8 | #90e8a8 | #108030 |
| database | Teal 180° | #b8f5ec | #90e8dc | #008080 |
| code | Cyan 195° | #b8ecf5 | #90dce8 | #008090 |
| image/bitmap | Sky 200° | #b8e0f5 | #90c8e8 | #0070a0 |
| document | Blue 210° | #b8d4f5 | #90bce8 | #0055a0 |
| vector | Indigo 240° | #c8b8f5 | #a890e8 | #5030a0 |
| audio | Purple 270° | #d8b8f5 | #c090e8 | #6020a0 |
| font | Violet 285° | #e8b8f5 | #d490e8 | #8020a0 |
| video | Magenta 300° | #f5b8e8 | #e890d4 | #a01080 |
| markup | Slate | #c8d0d8 | #a8b4c0 | #405060 |
| system/binary | Gray | #d0d4d8 | #b0b8c0 | #404850 |
| ebook | Brown | #e8d8c0 | #d4c0a0 | #705020 |
| plc | Olive | #dce0b8 | #c8cc90 | #606010 |

## Extracting Icons from Source File

The `icons-source.svg` is an Inkscape file with all icons. Each icon has two elements:
- `bg-{category}` — Background path with gradient fill `url(#grad-{category})`
- `file-{category}` — Foreground pictogram with solid fill

To extract individual icons after modifying the source:

```bash
cd client

python3 << 'PYEOF'
import xml.etree.ElementTree as ET
import re

tree = ET.parse('icons-source.svg')
root = tree.getroot()
ET.register_namespace('', 'http://www.w3.org/2000/svg')

# Extract gradient colors
defs = root.find('.//{http://www.w3.org/2000/svg}defs')
gradients = {}
for grad in defs.findall('.//{http://www.w3.org/2000/svg}linearGradient'):
    grad_id = grad.get('id', '')
    if grad_id.startswith('grad-'):
        cat = grad_id.replace('grad-', '')
        stops = grad.findall('.//{http://www.w3.org/2000/svg}stop')
        colors = [stop.get('stop-color', '') for stop in stops]
        if len(colors) >= 2:
            gradients[cat] = {'light': colors[0], 'dark': colors[1]}

# Find bg-* and file-* elements
elements = {}
for elem in root.iter('{http://www.w3.org/2000/svg}path'):
    elem_id = elem.get('id', '')
    if elem_id.startswith('bg-'):
        cat = elem_id.replace('bg-', '')
        if cat not in elements: elements[cat] = {}
        elements[cat]['bg'] = {'d': elem.get('d', ''), 'style': elem.get('style', '')}
    elif elem_id.startswith('file-'):
        cat = elem_id.replace('file-', '')
        if cat not in elements: elements[cat] = {}
        elements[cat]['fg'] = {'d': elem.get('d', ''), 'style': elem.get('style', '')}

def parse_path_bounds(d):
    """Get viewBox from path starting point."""
    m_match = re.match(r'm\s*([\d.-]+)[,\s]+([\d.-]+)', d, re.I)
    if m_match:
        start_x = float(m_match.group(1))
        start_y = float(m_match.group(2))
        # ViewBox: 7 units left of start, 3 units up, 8.5 wide, 11 tall
        return (start_x - 7, start_y - 3, 8.5, 11)
    return None

# Output filename mapping (source name → SCSS name)
name_map = {'bitmap': 'image', 'binary': 'system'}

for cat, data in elements.items():
    if 'bg' not in data or 'fg' not in data or cat not in gradients:
        continue

    bounds = parse_path_bounds(data['bg']['d'])
    if not bounds:
        continue

    x, y, w, h = bounds
    output_name = name_map.get(cat, cat)
    colors = gradients[cat]

    # Extract foreground fill color from style
    fg_style = data['fg']['style']
    fg_fill_match = re.search(r'fill:(#[0-9a-fA-F]+)', fg_style)
    fg_fill = fg_fill_match.group(1) if fg_fill_match else '#000'

    svg_content = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="{x:.2f} {y:.2f} {w:.2f} {h:.2f}">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="{colors['light']}"/>
      <stop offset="100%" stop-color="{colors['dark']}"/>
    </linearGradient>
  </defs>
  <path fill="url(#g)" d="{data['bg']['d']}"/>
  <path fill="{fg_fill}" d="{data['fg']['d']}"/>
</svg>'''

    with open(f'icons/{output_name}.svg', 'w') as f:
        f.write(svg_content)
    print(f"Created icons/{output_name}.svg")

print("Done!")
PYEOF
```

## Updating Colors in Source File

The source file uses gradient IDs `grad-{category}` and foreground IDs `file-{category}`.

To update gradient colors:
```bash
# Example: Update PDF gradient
sed -i '' 's|id="grad-pdf"[^>]*>[^<]*<stop[^>]*stop-color="[^"]*"|id="grad-pdf" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#NEW_LIGHT"|' icons-source.svg
```

To update foreground colors:
```bash
# Example: Update PDF foreground
sed -i '' 's/\(id="file-pdf"[^>]*fill:\)#[0-9a-fA-F]*/\1#NEW_COLOR/' icons-source.svg
```

## Build Process

After modifying icons:

```bash
# 1. Rebuild CSS (compiles SCSS, inlines SVGs as data URIs)
npm run build

# 2. Re-expose assets to public
composer vendor-expose
```

## Adding a New Extension

1. Edit `client/src/styles/asset-icons.scss`
2. Add the extension to `$ext-categories` map:
   ```scss
   newext: category,  // e.g., abc: document
   ```
3. Run `npm run build`

## Adding a New Category

1. Create `client/icons/{category}.svg` using the bash function above
2. Edit `client/src/styles/asset-icons.scss`:
   - Add entry to `$category-icons` map
   - Add extensions to `$ext-categories` map
3. Optionally add the category to `icons-source.svg` for reference
4. Run `npm run build`
