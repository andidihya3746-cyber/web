"""
Melatih model K-Means untuk mengelompokkan siswa (Berhasil / Sedang / Berisiko).

Pemakaian:
    python train.py
    python train.py --data data/dataset_siswa.csv --out model
"""
import argparse
import json
import sys
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
from sklearn.cluster import KMeans
from sklearn.metrics import davies_bouldin_score, silhouette_score
from sklearn.preprocessing import MinMaxScaler

import config


def load_and_clean(csv_path: Path) -> pd.DataFrame:
    df = pd.read_csv(csv_path, dtype=str)  # teks dulu agar ID "0001" tidak jadi 1
    df.columns = [c.strip().lower() for c in df.columns]

    # Kolom "nis" dipetakan ke "id_siswa" (nama kolom yang dikenal config.ID_COLUMNS)
    if "id_siswa" not in df.columns and "nis" in df.columns:
        df = df.rename(columns={"nis": "id_siswa"})

    missing = [c for c in config.FEATURES if c not in df.columns]
    if missing:
        raise ValueError(f"Kolom berikut tidak ada di CSV: {missing}. "
                         f"Kolom tersedia: {list(df.columns)}")

    n_awal = len(df)
    df[config.FEATURES] = df[config.FEATURES].apply(pd.to_numeric, errors="coerce")
    df = df.dropna(subset=config.FEATURES)
    df = df[(df[config.FEATURES] >= config.VALUE_MIN).all(axis=1)
            & (df[config.FEATURES] <= config.VALUE_MAX).all(axis=1)]
    df = df.drop_duplicates().reset_index(drop=True)

    print(f"Data awal: {n_awal} baris | setelah dibersihkan: {len(df)} baris")
    if len(df) < config.MIN_ROWS:
        raise ValueError(f"Data bersih hanya {len(df)} baris (minimal {config.MIN_ROWS}).")
    return df


def build_cluster_labels(kmeans: KMeans, scaler: MinMaxScaler) -> dict:
    """Urutkan cluster berdasarkan skor tertimbang centroid (skala asli),
    lalu beri label Berhasil / Sedang / Berisiko."""
    centroids = scaler.inverse_transform(kmeans.cluster_centers_)
    weights = np.array([config.LABEL_WEIGHTS[f] for f in config.FEATURES])
    scores = centroids @ weights / weights.sum()
    order = np.argsort(-scores)  # tertinggi -> terendah
    return {int(cluster): config.LABELS_HIGH_TO_LOW[rank]
            for rank, cluster in enumerate(order)}


def evaluate_against_label(df: pd.DataFrame):
    """Bandingkan cluster dengan status asli (jika kolom label ada di CSV).
    Tiap cluster dianggap memprediksi status yang paling banyak muncul di dalamnya."""
    col = config.LABEL_COLUMN
    if col not in df.columns:
        return None
    y = df[col].astype(str).str.strip().str.lower()
    tabel = pd.crosstab(df["kategori"], y)
    mayoritas = tabel.max(axis=1).sum()
    persen_berhasil = (y == config.POSITIVE_LABEL).groupby(df["kategori"]).mean().round(4)
    return {
        "akurasi_kesesuaian_mayoritas": round(float(mayoritas / len(df)), 4),
        "persen_berhasil_per_kategori": persen_berhasil.to_dict(),
        "tabel_kategori_vs_status": {k: {c: int(v) for c, v in r.items()}
                                     for k, r in tabel.to_dict("index").items()},
    }


def main():
    parser = argparse.ArgumentParser(description="Latih model K-Means prediksi siswa")
    parser.add_argument("--data", default="data/dataset_siswa.csv")
    parser.add_argument("--out", default="model")
    args = parser.parse_args()

    base = Path(__file__).parent
    data_path = (base / args.data) if not Path(args.data).is_absolute() else Path(args.data)
    out_dir = (base / args.out) if not Path(args.out).is_absolute() else Path(args.out)
    out_dir.mkdir(parents=True, exist_ok=True)

    df = load_and_clean(data_path)

    # 1. Normalisasi Min-Max (sesuai proposal)
    scaler = MinMaxScaler()
    X = scaler.fit_transform(df[config.FEATURES])

    # 2. K-Means
    kmeans = KMeans(n_clusters=config.N_CLUSTERS, n_init=10,
                    random_state=config.RANDOM_STATE)
    clusters = kmeans.fit_predict(X)

    # 3. Pemetaan cluster -> label
    cluster_to_label = build_cluster_labels(kmeans, scaler)
    df["cluster"] = clusters
    df["kategori"] = df["cluster"].map(cluster_to_label)

    # 4. Evaluasi (K-Means tidak punya label asli, jadi pakai metrik internal)
    metrics = {
        "jumlah_data": int(len(df)),
        "silhouette_score": round(float(silhouette_score(X, clusters)), 4),
        "davies_bouldin_index": round(float(davies_bouldin_score(X, clusters)), 4),
        "inertia": round(float(kmeans.inertia_), 4),
        "jumlah_per_kategori": df["kategori"].value_counts().to_dict(),
        "centroid_skala_asli": {
            cluster_to_label[i]: dict(zip(config.FEATURES, np.round(row, 2).tolist()))
            for i, row in enumerate(scaler.inverse_transform(kmeans.cluster_centers_))
        },
    }

    evaluasi_label = evaluate_against_label(df)
    if evaluasi_label:
        metrics["evaluasi_terhadap_label_asli"] = evaluasi_label

    # 5. Simpan
    joblib.dump({
        "scaler": scaler,
        "kmeans": kmeans,
        "features": config.FEATURES,
        "cluster_to_label": cluster_to_label,
    }, out_dir / "model_kmeans.joblib")

    keep = [c for c in config.ID_COLUMNS + [config.LABEL_COLUMN] if c in df.columns]
    df[keep + config.FEATURES + ["cluster", "kategori"]].to_csv(
        out_dir / "hasil_clustering.csv", index=False)
    (out_dir / "metrics.json").write_text(
        json.dumps(metrics, indent=2, ensure_ascii=False), encoding="utf-8")

    print(json.dumps(metrics, indent=2, ensure_ascii=False))
    print(f"\nModel tersimpan di: {out_dir / 'model_kmeans.joblib'}")


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(f"ERROR: {e}", file=sys.stderr)
        sys.exit(1)