"""
Memprediksi kategori siswa memakai model yang sudah dilatih. Output selalu JSON di stdout.

Contoh:
    # satu siswa
    python predict.py --json "{\"rata_rata_nilai\": 80, \"nilai_tugas\": 85, \"nilai_uts\": 78, \"nilai_uas\": 80, \"kehadiran_persen\": 95}"

    # banyak siswa dari file JSON (list objek) -> pakai tanda @
    python predict.py --json @input.json

    # banyak siswa dari CSV
    python predict.py --csv data/siswa_baru.csv

Jika gagal: {"status": "error", "message": "..."} dan exit code 1.
"""
import argparse
import json
import sys
from pathlib import Path

import joblib
import numpy as np
import pandas as pd

import config

MODEL_PATH = Path(__file__).parent / "model" / "model_kmeans.joblib"


def normalize_columns(df: pd.DataFrame) -> pd.DataFrame:
    """Nama kolom huruf kecil; kolom "nis" dipetakan ke "id_siswa" (nama kolom internal)."""
    df.columns = [str(c).strip().lower() for c in df.columns]
    if "id_siswa" not in df.columns and "nis" in df.columns:
        df = df.rename(columns={"nis": "id_siswa"})
    return df


def load_input(args) -> pd.DataFrame:
    if args.csv:
        df = pd.read_csv(args.csv, dtype=str)  # teks dulu agar NIS "0001" utuh
        return normalize_columns(df)
    raw = args.json
    if raw.startswith("@"):
        raw = Path(raw[1:]).read_text(encoding="utf-8")
    data = json.loads(raw)
    if isinstance(data, dict):
        data = [data]
    df = pd.DataFrame(data)
    return normalize_columns(df)  # "Nilai_UTS" -> "nilai_uts", "NIS" -> "id_siswa"


def predict(df: pd.DataFrame, bundle: dict) -> list:
    features = bundle["features"]
    missing = [c for c in features if c not in df.columns]
    if missing:
        raise ValueError(f"Field wajib tidak ada: {missing}")

    X_raw = df[features].apply(pd.to_numeric, errors="coerce")
    if X_raw.isna().any().any():
        raise ValueError("Ada nilai kosong atau bukan angka pada field fitur.")
    if ((X_raw < config.VALUE_MIN) | (X_raw > config.VALUE_MAX)).any().any():
        raise ValueError(f"Semua nilai harus di antara {config.VALUE_MIN} dan {config.VALUE_MAX}.")

    # Clip ke rentang data latih agar scaler tidak menghasilkan nilai di luar 0-1
    X = np.clip(bundle["scaler"].transform(X_raw), 0, 1)
    kmeans = bundle["kmeans"]
    clusters = kmeans.predict(X)
    jarak = np.linalg.norm(X - kmeans.cluster_centers_[clusters], axis=1)

    hasil = []
    for i, row in df.reset_index(drop=True).iterrows():
        item = {c: row[c] for c in config.ID_COLUMNS if c in df.columns}
        item.update({f: float(X_raw.iloc[i][f]) for f in features})
        item["cluster"] = int(clusters[i])
        item["kategori"] = bundle["cluster_to_label"][int(clusters[i])]
        item["jarak_ke_centroid"] = round(float(jarak[i]), 4)
        hasil.append(item)
    return hasil


def main():
    parser = argparse.ArgumentParser(description="Prediksi kategori siswa (output JSON)")
    src = parser.add_mutually_exclusive_group(required=True)
    src.add_argument("--json", help="String JSON (objek/list objek) atau @path_file.json")
    src.add_argument("--csv", help="Path file CSV")
    args = parser.parse_args()

    if not MODEL_PATH.exists():
        raise FileNotFoundError("Model belum ada. Jalankan dulu: python train.py")

    bundle = joblib.load(MODEL_PATH)
    hasil = predict(load_input(args), bundle)
    print(json.dumps({"status": "ok", "jumlah": len(hasil), "hasil": hasil},
                     ensure_ascii=False, default=str))


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(json.dumps({"status": "error", "message": str(e)}, ensure_ascii=False))
        sys.exit(1)