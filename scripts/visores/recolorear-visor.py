"""Genera las fotos de los visores Iridium a partir de una foto real de visor AGV.

Toma la foto del AGV Iridium Red (visors/01KZPPBDC1GM7VFMEGNER9V1GK.png en el
servidor) y cambia solo el tono de la parte de color del visor, conservando brillos,
reflejos, tornillos y fondo blanco. Suma un tornasol leve (el tono gira un poco de
izquierda a derecha).

Uso: python3 recolorear-visor.py <foto-base.png> <carpeta-salida>
"""
import colorsys
import sys

import matplotlib.colors as mc
import numpy as np
from PIL import Image

base = np.asarray(Image.open(sys.argv[1]).convert("RGB")).astype(np.float32) / 255
salida = sys.argv[2]

hsv = mc.rgb_to_hsv(base)
h, s, v = hsv[..., 0], hsv[..., 1], hsv[..., 2]
H, W = h.shape
x = np.linspace(-1, 1, W)[None, :].repeat(H, 0)
y = np.linspace(-1, 1, H)[:, None].repeat(W, 1)
mask = np.clip((s - 0.15) / 0.25, 0, 1)  # solo la parte de color del visor

# Color de cada visor (hex del panel). Algunos tonos se ajustan para que se distingan.
COLORES = {
    "iridium-purpura": "#7B3FB8",
    "iridium-azul-turquesa": "#1FB0C4",
    "iridium-dorado": "#D4A02A",
    "iridium-violeta": "#6A4FD1",
    "iridium-naranja": "#F08030",
    "iridium-azul-electrico": "#2B5BE0",
}
TONO = {"iridium-purpura": 0.785, "iridium-violeta": 0.705, "iridium-azul-electrico": 0.615}

for nombre, hx in COLORES.items():
    tr, tg, tb = mc.to_rgb(hx)
    th, ts, tv = colorsys.rgb_to_hsv(tr, tg, tb)
    th = TONO.get(nombre, th)
    nh = (th + 0.022 * (x * 0.7 + y * 0.3)) % 1.0
    ns = np.clip(s * (0.55 + 0.45 * ts / 0.85), 0, 1)
    nv = np.clip(v * (0.75 + 0.35 * tv), 0, 1)
    out = hsv.copy()
    out[..., 0] = h * (1 - mask) + nh * mask
    out[..., 1] = s * (1 - mask) + ns * mask
    out[..., 2] = v * (1 - mask) + nv * mask
    rgb = (mc.hsv_to_rgb(out) * 255).round().astype(np.uint8)
    Image.fromarray(rgb).resize((600, 600), Image.LANCZOS).save(f"{salida}/{nombre}.jpg", quality=90)
