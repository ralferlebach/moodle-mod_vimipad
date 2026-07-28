// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Client-side export of the live canvas SVG to a standalone file.
 *
 * The on-screen SVG is largely self-contained (inline fill/stroke attributes and
 * CSS-variable fallbacks), so a standalone export mainly needs a fixed viewBox
 * covering the content, an explicit text colour (for currentColor) and a
 * background. The bounds computation is pure and unit-tested; the serialization
 * step operates on a cloned DOM node.
 *
 * @module     mod_vimipad/canvas/svg_export
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {LayoutMap, SizeMap, VimiNode} from '../types';

const SVG_NS = 'http://www.w3.org/2000/svg';
const DEFAULT_NODE_W = 120;
const DEFAULT_NODE_H = 44;
const TEXT_COLOR = '#495057';
const BACKGROUND = '#ffffff';

/** A rectangle in canvas coordinates. */
export interface Bounds {
    x: number;
    y: number;
    w: number;
    h: number;
}

/**
 * Compute the bounding box of all placed nodes, padded, for the export viewBox.
 *
 * Falls back to a sensible default box when there are no positioned nodes.
 *
 * @param nodes The nodes.
 * @param layout Stored node positions (centre points).
 * @param sizes Stored node sizes; defaults are used when absent.
 * @param pad Padding added on every side.
 * @param fallback Box to use when nothing is placed.
 * @returns The padded content bounds.
 */
export function computeContentBounds(
    nodes: VimiNode[],
    layout: LayoutMap,
    sizes: SizeMap,
    pad: number,
    fallback: Bounds
): Bounds {
    let minx = Infinity;
    let miny = Infinity;
    let maxx = -Infinity;
    let maxy = -Infinity;
    let found = false;

    for (const node of nodes) {
        const pos = layout[node.stableid];
        if (!pos) {
            continue;
        }
        found = true;
        const size = sizes[node.stableid];
        const halfw = (size?.w ?? DEFAULT_NODE_W) / 2;
        const halfh = (size?.h ?? DEFAULT_NODE_H) / 2;
        minx = Math.min(minx, pos.x - halfw);
        miny = Math.min(miny, pos.y - halfh);
        maxx = Math.max(maxx, pos.x + halfw);
        maxy = Math.max(maxy, pos.y + halfh);
    }

    if (!found) {
        return fallback;
    }

    return {
        x: Math.round(minx - pad),
        y: Math.round(miny - pad),
        w: Math.round(maxx - minx + pad * 2),
        h: Math.round(maxy - miny + pad * 2),
    };
}

/**
 * Serialize a canvas SVG element to a standalone SVG document string.
 *
 * @param svg The live SVG element to export.
 * @param bounds The viewBox/content bounds.
 * @returns The standalone SVG XML.
 */
export function serializeCanvasSvg(svg: SVGSVGElement, bounds: Bounds): string {
    const clone = svg.cloneNode(true) as SVGSVGElement;

    clone.setAttribute('viewBox', `${bounds.x} ${bounds.y} ${bounds.w} ${bounds.h}`);
    clone.setAttribute('width', String(bounds.w));
    clone.setAttribute('height', String(bounds.h));
    clone.setAttribute('preserveAspectRatio', 'xMidYMid meet');
    clone.setAttribute('style', `color:${TEXT_COLOR}`);
    clone.removeAttribute('tabindex');

    // Drop interaction-only overlays that should not appear in the export.
    clone.querySelectorAll(
        '.vimipad-canvas-seloutline, .vimipad-canvas-handle, .vimipad-canvas-connector'
    ).forEach((el) => el.remove());

    // Opaque background so the map is not transparent in viewers.
    const bg = document.createElementNS(SVG_NS, 'rect');
    bg.setAttribute('x', String(bounds.x));
    bg.setAttribute('y', String(bounds.y));
    bg.setAttribute('width', String(bounds.w));
    bg.setAttribute('height', String(bounds.h));
    bg.setAttribute('fill', BACKGROUND);
    clone.insertBefore(bg, clone.firstChild);

    const xml = new XMLSerializer().serializeToString(clone);
    return `<?xml version="1.0" encoding="UTF-8"?>\n${xml}`;
}

/**
 * Trigger a browser download of a blob.
 *
 * @param blob The file contents.
 * @param filename The download filename.
 * @returns void
 */
function triggerDownload(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

/**
 * Trigger a browser download of the given SVG element as a standalone file.
 *
 * @param svg The live SVG element.
 * @param bounds The content bounds.
 * @param filename The download filename.
 * @returns void
 */
export function downloadCanvasSvg(svg: SVGSVGElement, bounds: Bounds, filename: string): void {
    const doc = serializeCanvasSvg(svg, bounds);
    triggerDownload(new Blob([doc], {type: 'image/svg+xml;charset=utf-8'}), filename);
}

/**
 * Rasterize the canvas SVG to a PNG and trigger a download.
 *
 * The standalone SVG is loaded into an image and drawn onto an off-screen canvas
 * at a higher pixel density for a crisp result. Because the SVG is same-origin
 * (an object URL), the canvas is not tainted and toBlob succeeds.
 *
 * @param svg The live SVG element.
 * @param bounds The content bounds.
 * @param filename The download filename.
 * @param scale Pixel-density multiplier (default 2).
 * @returns void
 */
export function downloadCanvasPng(svg: SVGSVGElement, bounds: Bounds, filename: string, scale = 2): void {
    const doc = serializeCanvasSvg(svg, bounds);
    const svgUrl = URL.createObjectURL(new Blob([doc], {type: 'image/svg+xml;charset=utf-8'}));
    const image = new Image();

    image.onload = (): void => {
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(bounds.w * scale));
        canvas.height = Math.max(1, Math.round(bounds.h * scale));
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            URL.revokeObjectURL(svgUrl);
            return;
        }
        ctx.fillStyle = BACKGROUND;
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => {
            URL.revokeObjectURL(svgUrl);
            if (blob) {
                triggerDownload(blob, filename);
            }
        }, 'image/png');
    };
    image.onerror = (): void => {
        URL.revokeObjectURL(svgUrl);
    };
    image.src = svgUrl;
}

/**
 * Build a minimal single-page PDF that embeds a JPEG image filling the page.
 *
 * Uses the DCTDecode filter so the JPEG bytes are embedded directly. Byte
 * offsets for the cross-reference table are tracked as chunks are appended.
 *
 * @param jpeg The JPEG bytes.
 * @param iw The image pixel width.
 * @param ih The image pixel height.
 * @param pageW The page width in points.
 * @param pageH The page height in points.
 * @returns The PDF bytes.
 */
export function buildImagePdf(
    jpeg: Uint8Array,
    iw: number,
    ih: number,
    pageW: number,
    pageH: number
): Uint8Array {
    const parts: Uint8Array[] = [];
    let length = 0;
    const offsets: number[] = [];

    const push = (data: Uint8Array | string): void => {
        let bytes: Uint8Array;
        if (typeof data === 'string') {
            // PDF structural text is ASCII; encode byte-for-byte.
            bytes = new Uint8Array(data.length);
            for (let i = 0; i < data.length; i++) {
                bytes[i] = data.charCodeAt(i) & 0xff;
            }
        } else {
            bytes = data;
        }
        parts.push(bytes);
        length += bytes.length;
    };
    const mark = (): void => {
        offsets.push(length);
    };

    const width = Math.max(1, Math.round(pageW));
    const height = Math.max(1, Math.round(pageH));
    const content = `q ${width} 0 0 ${height} 0 0 cm /Im0 Do Q`;

    push('%PDF-1.3\n');
    mark();
    push('1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n');
    mark();
    push('2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n');
    mark();
    push(
        `3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${width} ${height}] ` +
        '/Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>\nendobj\n'
    );
    mark();
    push(
        `4 0 obj\n<< /Type /XObject /Subtype /Image /Width ${iw} /Height ${ih} ` +
        `/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${jpeg.length} >>\nstream\n`
    );
    push(jpeg);
    push('\nendstream\nendobj\n');
    mark();
    push(`5 0 obj\n<< /Length ${content.length} >>\nstream\n${content}\nendstream\nendobj\n`);

    const xrefOffset = length;
    let xref = 'xref\n0 6\n0000000000 65535 f \n';
    for (const off of offsets) {
        xref += `${String(off).padStart(10, '0')} 00000 n \n`;
    }
    push(xref);
    push(`trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF\n`);

    const out = new Uint8Array(length);
    let pos = 0;
    for (const part of parts) {
        out.set(part, pos);
        pos += part.length;
    }
    return out;
}

/**
 * Rasterize the canvas SVG and download it wrapped in a single-page PDF.
 *
 * @param svg The live SVG element.
 * @param bounds The content bounds.
 * @param filename The download filename.
 * @param scale Pixel-density multiplier (default 2).
 * @returns void
 */
export function downloadCanvasPdf(svg: SVGSVGElement, bounds: Bounds, filename: string, scale = 2): void {
    const doc = serializeCanvasSvg(svg, bounds);
    const svgUrl = URL.createObjectURL(new Blob([doc], {type: 'image/svg+xml;charset=utf-8'}));
    const image = new Image();

    image.onload = (): void => {
        const iw = Math.max(1, Math.round(bounds.w * scale));
        const ih = Math.max(1, Math.round(bounds.h * scale));
        const canvas = document.createElement('canvas');
        canvas.width = iw;
        canvas.height = ih;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            URL.revokeObjectURL(svgUrl);
            return;
        }
        ctx.fillStyle = BACKGROUND;
        ctx.fillRect(0, 0, iw, ih);
        ctx.drawImage(image, 0, 0, iw, ih);
        canvas.toBlob((jpegBlob) => {
            if (!jpegBlob) {
                URL.revokeObjectURL(svgUrl);
                return;
            }
            void jpegBlob.arrayBuffer().then((buffer) => {
                URL.revokeObjectURL(svgUrl);
                const pdf = buildImagePdf(new Uint8Array(buffer), iw, ih, bounds.w, bounds.h);
                triggerDownload(new Blob([pdf] as BlobPart[], {type: 'application/pdf'}), filename);
            });
        }, 'image/jpeg', 0.92);
    };
    image.onerror = (): void => {
        URL.revokeObjectURL(svgUrl);
    };
    image.src = svgUrl;
}
