// Rebuilds scrambled pages (DerivativeGenerator). Format: order[sourceTile] = slot, row-major; each
// slot is a cell of tile + 2 * gutter pixels, the tile sits at the gutter offset inside it.
// Shared by the reader and the admin page grid.

export function tileLayout(bitmap, { width, height, order }, tile, gutter) {
    const [w, h] = [Number(width), Number(height)];
    const cell = tile + 2 * gutter;
    const columns = Math.ceil(w / tile);
    const rows = Math.ceil(h / tile);
    const slots = order.split(',').map(Number);
    if (slots.length !== columns * rows || bitmap.width !== columns * cell || bitmap.height !== rows * cell) {
        throw new Error('Image does not match its tile order.');
    }

    return { w, h, tile, gutter, cell, columns, rows, slots };
}

// Draws tile rows [fromRow, toRow) shifted up by top and scaled. Edges are rounded on both sides,
// so scaled tiles meet without hairline gaps; at scale 1 they are exact.
export function drawTiles(context, bitmap, layout, fromRow, toRow, top = 0, scale = 1) {
    const { w, h, tile, gutter, cell, columns, slots } = layout;
    for (let row = fromRow; row < toRow; row++) {
        for (let column = 0; column < columns; column++) {
            const slot = slots[row * columns + column];
            const x = column * tile;
            const y = row * tile - top;
            const tw = Math.min(tile, w - x);
            const th = Math.min(tile, h - row * tile);
            const dx = Math.round(x * scale);
            const dy = Math.round(y * scale);
            context.drawImage(bitmap, (slot % columns) * cell + gutter, Math.floor(slot / columns) * cell + gutter, tw, th,
                dx, dy, Math.round((x + tw) * scale) - dx, Math.round((y + th) * scale) - dy);
        }
    }
}
