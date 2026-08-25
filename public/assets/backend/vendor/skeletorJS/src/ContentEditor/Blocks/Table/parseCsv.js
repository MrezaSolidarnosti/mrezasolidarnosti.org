/**
 * Parse CSV/TSV text into a grid of string rows (`string[][]`).
 *
 * Follows RFC 4180 for quoting: a field wrapped in double quotes may contain the delimiter,
 * newlines and quotes; a doubled quote `""` inside such a field is a literal `"`. The
 * delimiter is auto-detected from the first line (comma, tab or semicolon) so both typical CSV
 * and a paste straight out of a spreadsheet (which is tab-separated) work without configuration.
 *
 * Rows are returned exactly as parsed — ragged rows are not padded here; the caller decides how
 * to reconcile differing column counts.
 */
export function parseCsv(text) {
    if (typeof text !== 'string' || text.trim() === '') {
        return [];
    }
    const normalized = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    const delimiter = detectDelimiter(normalized);

    const rows = [];
    let row = [];
    let field = '';
    let inQuotes = false;

    for (let i = 0; i < normalized.length; i++) {
        const char = normalized[i];
        if (inQuotes) {
            if (char === '"') {
                if (normalized[i + 1] === '"') {
                    field += '"';   // escaped quote
                    i++;
                } else {
                    inQuotes = false;
                }
            } else {
                field += char;
            }
        } else if (char === '"') {
            inQuotes = true;
        } else if (char === delimiter) {
            row.push(field);
            field = '';
        } else if (char === '\n') {
            row.push(field);
            rows.push(row);
            field = '';
            row = [];
        } else {
            field += char;
        }
    }
    // Whatever is buffered after the last delimiter/newline is the final field of the last row.
    row.push(field);
    rows.push(row);

    // A trailing newline leaves one empty row behind — drop it.
    const last = rows[rows.length - 1];
    if (last.length === 1 && last[0] === '') {
        rows.pop();
    }
    return rows;
}

// The delimiter is whichever of comma/tab/semicolon appears most on the first line. A tie
// falls back to comma (the first candidate). Counting raw can be fooled by a delimiter inside
// a quoted field, but that's rare on a header line and only affects auto-detection, not parsing.
function detectDelimiter(text) {
    const firstLine = text.split('\n')[0] || '';
    let best = ',';
    let bestCount = -1;
    [',', '\t', ';'].forEach((candidate) => {
        const count = firstLine.split(candidate).length - 1;
        if (count > bestCount) {
            bestCount = count;
            best = candidate;
        }
    });
    return best;
}
