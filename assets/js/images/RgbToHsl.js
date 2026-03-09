// javascript
export function RgbToHsl(rgb) {
    const hsl = {h: 0, s: 0, l: 0};
    const r = rgb.r / 255;
    const g = rgb.g / 255;
    const b = rgb.b / 255;
    const l = Math.max(r, g, b);
    const s = l - Math.min(r, g, b);
    const h = s
        ? l === r
            ? (g - b) / s
            : l === g
                ? 2 + (b - r) / s
                : 4 + (r - g) / s
        : 0;

    hsl.h = Math.floor(60 * h < 0 ? 60 * h + 360 : 60 * h);
    hsl.s = Math.floor(100 * (s ? (l <= 0.5 ? s / (2 * l - s) : s / (2 - (2 * l - s))) : 0));
    hsl.l = Math.floor((100 * (2 * l - s)) / 2);

    return hsl;
}
