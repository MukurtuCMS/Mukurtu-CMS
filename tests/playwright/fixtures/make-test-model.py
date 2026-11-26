"""Writes test-model.glb: a box with a different color on each face.

Used by 3d-model-viewer.offline.spec.ts. Each face has its own color, so a
rotation changes what the viewer shows. Run with python3, no packages needed:

    python3 make-test-model.py
"""

import json
import os
import struct

# Face normal, the two axes across it, and its color.
FACES = [
    ((1, 0, 0), (0, 1, 0), (0, 0, 1), (0.85, 0.2, 0.2)),
    ((-1, 0, 0), (0, 1, 0), (0, 0, -1), (0.2, 0.7, 0.3)),
    ((0, 1, 0), (0, 0, 1), (1, 0, 0), (0.2, 0.4, 0.85)),
    ((0, -1, 0), (1, 0, 0), (0, 0, 1), (0.9, 0.8, 0.2)),
    ((0, 0, 1), (1, 0, 0), (0, 1, 0), (0.7, 0.3, 0.8)),
    ((0, 0, -1), (0, 1, 0), (1, 0, 0), (0.95, 0.55, 0.15)),
]


def main():
    positions, normals, colors, indices = [], [], [], []
    for normal, u, v, color in FACES:
        base = len(positions)
        for su, sv in ((-1, -1), (1, -1), (1, 1), (-1, 1)):
            positions.append(tuple(0.5 * (normal[i] + su * u[i] + sv * v[i]) for i in range(3)))
            normals.append(normal)
            colors.append(color)
        # Wind each triangle counter-clockwise when seen from outside.
        cross = (u[1] * v[2] - u[2] * v[1], u[2] * v[0] - u[0] * v[2], u[0] * v[1] - u[1] * v[0])
        if sum(cross[i] * normal[i] for i in range(3)) > 0:
            indices += [base, base + 1, base + 2, base, base + 2, base + 3]
        else:
            indices += [base, base + 2, base + 1, base, base + 3, base + 2]

    def pack(fmt, rows):
        return b''.join(struct.pack(fmt, *row) for row in rows)

    chunks = [
        pack('<3f', positions),
        pack('<3f', normals),
        pack('<3f', colors),
        struct.pack('<%dH' % len(indices), *indices),
    ]
    views, offset, data = [], 0, b''
    for chunk in chunks:
        views.append({'buffer': 0, 'byteOffset': offset, 'byteLength': len(chunk)})
        data += chunk
        offset += len(chunk)
    # Each buffer view starts on a 4-byte boundary; all but the last
    # already end on one.
    data += b'\0' * (-len(data) % 4)

    gltf = {
        'asset': {'version': '2.0', 'generator': 'make-test-model.py'},
        'scene': 0,
        'scenes': [{'nodes': [0]}],
        'nodes': [{'mesh': 0}],
        'meshes': [{'primitives': [{
            'attributes': {'POSITION': 0, 'NORMAL': 1, 'COLOR_0': 2},
            'indices': 3,
            'material': 0,
        }]}],
        'materials': [{'pbrMetallicRoughness': {'metallicFactor': 0, 'roughnessFactor': 1}}],
        'buffers': [{'byteLength': len(data)}],
        'bufferViews': views,
        'accessors': [
            {'bufferView': 0, 'componentType': 5126, 'count': len(positions), 'type': 'VEC3',
             'min': [-0.5, -0.5, -0.5], 'max': [0.5, 0.5, 0.5]},
            {'bufferView': 1, 'componentType': 5126, 'count': len(normals), 'type': 'VEC3'},
            {'bufferView': 2, 'componentType': 5126, 'count': len(colors), 'type': 'VEC3'},
            {'bufferView': 3, 'componentType': 5123, 'count': len(indices), 'type': 'SCALAR'},
        ],
    }
    json_chunk = json.dumps(gltf, separators=(',', ':')).encode()
    json_chunk += b' ' * (-len(json_chunk) % 4)
    body = (struct.pack('<I4s', len(json_chunk), b'JSON') + json_chunk
            + struct.pack('<I4s', len(data), b'BIN\0') + data)
    out = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'test-model.glb')
    with open(out, 'wb') as f:
        f.write(struct.pack('<4sII', b'glTF', 2, 12 + len(body)) + body)


if __name__ == '__main__':
    main()
