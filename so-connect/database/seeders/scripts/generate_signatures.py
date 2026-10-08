#!/usr/bin/env python3
"""Render synthetic, name-derived cursive signatures from a JSON file or stdin."""

import hashlib
import json
import math
from pathlib import Path
import random
import sys
import unicodedata

from PIL import Image, ImageDraw


# Original single-stroke letter paths, relative to a baseline at y=1.
GLYPHS = {
    "a": [(0, 1), (.3, .35), (.65, .4), (.2, 1), (.05, .7), (.65, .4), (.55, 1), (.9, .85)],
    "b": [(0, 1), (.45, -.6), (.1, -.8), (.2, 1), (.6, .4), (.8, .7), (.3, 1), (.9, .85)],
    "c": [(0, 1), (.7, .4), (.35, .3), (.1, .7), (.3, 1), (.9, .85)],
    "d": [(0, 1), (.3, .4), (.65, .4), (.1, .9), (.4, 1), (.8, -.8), (.6, -.9), (.6, 1), (1, .85)],
    "e": [(0, 1), (.65, .5), (.4, .35), (.15, .7), (.35, 1), (.9, .85)],
    "f": [(0, 1), (.6, -.9), (.35, -1), (.2, 1.8), (.45, 1.9), (.45, .55), (.9, .85)],
    "g": [(0, 1), (.4, .4), (.7, .5), (.2, 1), (.1, .7), (.7, .4), (.5, 1.9), (.15, 2), (.05, 1.7), (1, .85)],
    "h": [(0, 1), (.45, -.8), (.2, -.9), (.15, 1), (.65, .4), (.75, .65), (.65, 1), (1, .85)],
    "i": [(0, 1), (.4, .4), (.3, 1), (.75, .85)],
    "j": [(0, 1), (.5, .4), (.3, 1.9), (0, 1.9), (0, 1.6), (.9, .85)],
    "k": [(0, 1), (.4, -.8), (.15, -.9), (.2, 1), (.7, .4), (.4, .75), (.8, 1), (1, .85)],
    "l": [(0, 1), (.5, -.8), (.25, -.9), (.15, .65), (.4, 1), (.9, .85)],
    "m": [(0, 1), (.3, .4), (.25, 1), (.7, .4), (.65, 1), (1.1, .4), (1.05, 1), (1.4, .85)],
    "n": [(0, 1), (.3, .4), (.25, 1), (.7, .4), (.65, 1), (1, .85)],
    "o": [(0, 1), (.4, .35), (.75, .55), (.5, 1), (.15, .85), (.3, .4), (.9, .85)],
    "p": [(0, 1), (.4, .4), (.2, 1.9), (.35, .6), (.7, .4), (.85, .75), (.4, 1), (1, .85)],
    "q": [(0, 1), (.4, .4), (.75, .45), (.2, 1), (.1, .7), (.75, .4), (.6, 1.8), (1, .85)],
    "r": [(0, 1), (.4, .4), (.6, .3), (.55, .6), (.4, 1), (.9, .85)],
    "s": [(0, 1), (.6, .3), (.35, .7), (.6, .9), (.3, 1), (.1, .8), (.9, .85)],
    "t": [(0, 1), (.5, -.2), (.3, .85), (.5, 1), (.9, .85)],
    "u": [(0, 1), (.35, .4), (.2, .9), (.4, 1), (.75, .4), (.65, 1), (1, .85)],
    "v": [(0, 1), (.35, .4), (.4, 1), (.8, .45), (1, .85)],
    "w": [(0, 1), (.3, .4), (.35, 1), (.7, .4), (.7, 1), (1.1, .4), (1.4, .85)],
    "x": [(0, 1), (.6, .4), (.25, 1), (.8, .85)],
    "y": [(0, 1), (.3, .4), (.2, .9), (.4, 1), (.75, .4), (.5, 1.9), (.1, 2), (.1, 1.6), (1, .85)],
    "z": [(0, 1), (.3, .4), (.7, .4), (.3, 1), (.7, 1), (.3, 1.9), (0, 1.7), (1, .85)],
}


def curve(points):
    padded = [points[0], *points, points[-1]]
    result = []
    for i in range(1, len(padded) - 2):
        a, b, c, d = padded[i - 1:i + 3]
        for step in range(12):
            t = step / 12
            result.append(tuple(.5 * (
                2 * b[k] + (-a[k] + c[k]) * t
                + (2 * a[k] - 5 * b[k] + 4 * c[k] - d[k]) * t * t
                + (-a[k] + 3 * b[k] - 3 * c[k] + d[k]) * t ** 3
            ) for k in (0, 1)))
    return [*result, points[-1]]


def render(profile):
    identity = f"{profile['id']}:{profile['first_name']}:{profile['last_name']}"
    rng = random.Random(int(hashlib.sha256(identity.encode()).hexdigest(), 16))
    canvas = Image.new("RGB", (600, 600), "white")
    draw = ImageDraw.Draw(canvas)
    ink = (rng.randrange(8, 35), rng.randrange(10, 35), rng.randrange(25, 65))
    slope = rng.uniform(-.11, .02)
    slant = rng.uniform(.16, .40)
    thickness = rng.choice([5, 6, 7])

    for row, name in enumerate((profile["first_name"], profile["last_name"])):
        name = unicodedata.normalize("NFKD", name).encode("ascii", "ignore").decode().lower()
        name = "".join(letter for letter in name if letter in GLYPHS)
        if not name:
            raise ValueError(f"Profile {profile['id']} has no renderable name.")
        advances = [GLYPHS[letter][-1][0] * rng.uniform(.86, 1.08) for letter in name]
        scale_x = min(78, 475 / sum(advances))
        scale_y = rng.uniform(55, 75)
        origin = rng.uniform(40, 65)
        baseline = 220 + row * 145 + rng.uniform(-12, 12)
        x = 0
        path = []
        for index, letter in enumerate(name):
            amplitude = 1.35 if index == 0 else rng.uniform(.92, 1.08)
            points = []
            for gx, gy in GLYPHS[letter]:
                y = (gy - 1) * scale_y * amplitude
                px = origin + (x + gx) * scale_x - slant * y
                py = baseline + y + slope * px + rng.uniform(-1.5, 1.5)
                points.append((px, py))
            path.extend(curve(points))
            if letter in "ij":
                px = origin + (x + .45) * scale_x
                py = baseline - scale_y + slope * px
                draw.ellipse((px, py, px + 8, py + 8), fill=ink)
            if letter in "tx":
                px = origin + (x + .4) * scale_x
                py = baseline - scale_y * .35 + slope * px
                draw.line((px - 15, py + 5, px + 25, py - 6), fill=ink, width=thickness)
            x += advances[index]
        draw.line(path, fill=ink, width=thickness, joint="curve")

    # Each identity has its own terminal flourish and pressure variation.
    flourish = [(60, 435), (230, 410 + rng.randrange(-12, 12)),
                (520, 397), (545, 420), (360, 444), (140, 451)]
    draw.line(curve(flourish), fill=ink, width=max(3, thickness - 2), joint="curve")
    canvas = canvas.rotate(rng.uniform(-3, 3), Image.Resampling.BICUBIC, fillcolor="white")
    path = Path(profile["path"])
    path.parent.mkdir(parents=True, exist_ok=True)
    canvas.resize((100, 100), Image.Resampling.LANCZOS).save(path, "JPEG", quality=82, optimize=True)


if __name__ == "__main__":
    if len(sys.argv) > 1:
        with Path(sys.argv[1]).open(encoding="utf-8") as source:
            batch = json.load(source)
    else:
        batch = json.load(sys.stdin)
    for item in batch:
        render(item)
    print(f"Generated {len(batch)} unique name-derived signatures.")
