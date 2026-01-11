// javascript
export function RgbToLch({ r, g, b }) {
    // helpers
    const srgbToLinear = (v) => {
        v = v / 255;
        return v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    };

    const linearR = srgbToLinear(r);
    const linearG = srgbToLinear(g);
    const linearB = srgbToLinear(b);

    // sRGB (linear) to XYZ (D65)
    // multiply by 100 to use the reference white in same units
    const X = (linearR * 0.4124564 + linearG * 0.3575761 + linearB * 0.1804375) * 100;
    const Y = (linearR * 0.2126729 + linearG * 0.7151522 + linearB * 0.0721750) * 100;
    const Z = (linearR * 0.0193339 + linearG * 0.1191920 + linearB * 0.9503041) * 100;

    // reference white D65
    const Xn = 95.047;
    const Yn = 100.0;
    const Zn = 108.883;

    const f = (t) => {
        const delta = 6 / 29;
        return t > Math.pow(delta, 3) ? Math.cbrt(t) : (t / (3 * delta * delta)) + (4 / 29);
    };

    const fx = f(X / Xn);
    const fy = f(Y / Yn);
    const fz = f(Z / Zn);

    // Lab
    const L = Math.max(0, 116 * fy - 16);
    const a = 500 * (fx - fy);
    const bb = 200 * (fy - fz);

    // LCH
    const C = Math.sqrt(a * a + bb * bb);
    let H = Math.atan2(bb, a) * (180 / Math.PI); // degrees
    if (H < 0) H += 360;

    return { l: L, c: C, h: H };
}
