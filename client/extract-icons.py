#!/usr/bin/env python3
"""Regenerate client/icons/{category}.svg from client/icons-source.svg.

Run from the client/ directory:  cd client && python3 extract-icons.py

It regenerates EVERY icon that has a bg-{category} path, a file-{category} path and a
grad-{category} gradient in icons-source.svg - not just one. Moved here verbatim from
CLAUDE.md, where it lived only as an inline heredoc (issue #2).
"""
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
        # ViewBox: 7 units left of start, 3.4 units up, 8.5 wide, 10.5 tall
        return (start_x - 7, start_y - 3.4, 8.5, 10.5)
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
