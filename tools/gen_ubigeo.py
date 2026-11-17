#!/usr/bin/env python3
"""Genera ubigeo-peru.json para Vaporis.

Fuentes:
  A) scraper urbania/data/peru_districts.geojson  -> jerarquía oficial INEI + código
     de ubigeo (IDDIST). Completa (incluye Lima Metropolitana) pero en MAYÚSCULAS
     y sin tildes.
  B) custom-clothing-checkout/includes/data/ubigeo.json -> nombres bien acentuados,
     pero le falta la provincia de Lima entera.

Se usa A como esqueleto y B como diccionario de acentos.
"""
import json, unicodedata, collections, re

GEO = '/home/claude-user/scraper urbania/data/peru_districts.geojson'
ACC = '/home/claude-user/custom-clothing-checkout/includes/data/ubigeo.json'
OUT = '/home/claude-user/Vaporis-Dropdown-fragancias/checkout/data/ubigeo-peru.json'

def norm(s):
    """minúsculas, sin tildes, sin puntuación: clave para cruzar las dos fuentes."""
    s = unicodedata.normalize('NFD', s)
    s = ''.join(c for c in s if unicodedata.category(c) != 'Mn')
    return re.sub(r'[^a-z0-9]+', ' ', s.lower()).strip()

# Conectores que en español van en minúscula dentro de un nombre propio
MINUS = {'de', 'del', 'la', 'las', 'los', 'y', 'el', 'en'}

def titlecase(s):
    out = []
    for i, w in enumerate(s.lower().split()):
        out.append(w if (i and w in MINUS) else w[:1].upper() + w[1:])
    return ' '.join(out)

# ---- B) diccionario de acentos: normalizado -> forma acentuada ----
acc_map = {}
for dep, provs in json.load(open(ACC)).items():
    acc_map[norm(dep)] = dep
    for prov, dists in provs.items():
        acc_map[norm(prov)] = prov
        for d in dists:
            acc_map.setdefault(norm(d), d)

def pretty(name):
    """Nombre en MAYÚSCULAS del geojson -> forma legible y acentuada si la conocemos."""
    return acc_map.get(norm(name)) or titlecase(name)

# ---- A) esqueleto oficial ----
tree = collections.defaultdict(lambda: collections.defaultdict(list))
for feat in json.load(open(GEO))['features']:
    p = feat['properties']
    tree[pretty(p['NOMBDEP'])][pretty(p['NOMBPROV'])].append(
        [p['IDDIST'], pretty(p['NOMBDIST'])]
    )

# ---- Parches: distritos creados después del corte del geojson ----
# El geojson es una capa INEI antigua. "Mi Perú" (Callao) se creó en 2014 y falta.
EXTRA = [
    ('Callao', 'Callao', '070107', 'Mi Perú'),
]
for dep, prov, code, name in EXTRA:
    ya = {d[1] for d in tree[dep][prov]}
    if name not in ya:
        tree[dep][prov].append([code, name])

# Nombres de uso común (el código de ubigeo no cambia)
RENOMBRAR = {'150101': 'Cercado de Lima', '150121': 'Pueblo Libre'}
for dep in tree:
    for prov in tree[dep]:
        for d in tree[dep][prov]:
            if d[0] in RENOMBRAR: d[1] = RENOMBRAR[d[0]]

# Orden alfabético estable, con los distritos ordenados por nombre
data = {}
for dep in sorted(tree, key=norm):
    data[dep] = {}
    for prov in sorted(tree[dep], key=norm):
        data[dep][prov] = sorted(tree[dep][prov], key=lambda x: norm(x[1]))

import os
os.makedirs(os.path.dirname(OUT), exist_ok=True)
with open(OUT, 'w', encoding='utf-8') as f:
    json.dump(data, f, ensure_ascii=False, separators=(',', ':'), sort_keys=False)

nd = sum(len(v) for p in data.values() for v in p.values())
np_ = sum(len(p) for p in data.values())
print(f'departamentos: {len(data)}')
print(f'provincias:    {np_}')
print(f'distritos:     {nd}')
print(f'Lima/Lima:     {len(data["Lima"]["Lima"])}')
print(f'archivo:       {OUT}')

# Cuántos nombres quedaron sin acento conocido (solo informativo)
sin = [d[1] for p in data.values() for v in p.values() for d in v if norm(d[1]) not in acc_map]
print(f'distritos sin match en el diccionario de acentos: {len(sin)}')
print('ejemplos:', sin[:12])
