# cara jalankan = python xlsx_to_csv.py "dataset_siswa.xlsx"

import argparse
import sys
from pathlib import Path

import pandas as pd


def main():
    parser = argparse.ArgumentParser(description="Konversi xlsx -> csv")
    parser.add_argument("xlsx", help="Path file .xlsx")
    parser.add_argument("--out", default=None,
                        help="Path CSV keluaran (default: data/dataset_siswa.csv)")
    parser.add_argument("--sheet", default=0,
                        help="Nama atau nomor sheet (default: sheet pertama)")
    args = parser.parse_args()

    src = Path(args.xlsx)
    if not src.exists():
        raise FileNotFoundError(f"File tidak ditemukan: {src}")

    out = Path(args.out) if args.out else Path(__file__).parent / "data" / "dataset_siswa.csv"
    out.parent.mkdir(parents=True, exist_ok=True)

    sheet = int(args.sheet) if str(args.sheet).isdigit() else args.sheet
    df = pd.read_excel(src, sheet_name=sheet, dtype=str)

    df.columns = [str(c).strip() for c in df.columns]
    df = df.loc[:, ~df.columns.str.startswith("Unnamed")] 
    df = df.dropna(how="all")                              
    df = df.apply(lambda col: col.str.strip())             

    df.to_csv(out, index=False, encoding="utf-8")
    print(f"Berhasil: {len(df)} baris, {len(df.columns)} kolom -> {out}")
    print("Kolom:", ", ".join(df.columns))


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(f"ERROR: {e}", file=sys.stderr)
        sys.exit(1)
