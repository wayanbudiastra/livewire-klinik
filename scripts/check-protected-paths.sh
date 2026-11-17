#!/usr/bin/env bash
# Gagalkan jika perubahan menyentuh path terlarang (.github/protected-paths.txt).
#
# Pemakaian:
#   scripts/check-protected-paths.sh staged        # file yang di-stage (hook pre-commit)
#   scripts/check-protected-paths.sh <base-ref>    # diff <base-ref>...HEAD (CI)
#   ... | scripts/check-protected-paths.sh -       # daftar file dari stdin (untuk uji)
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"
LIST=".github/protected-paths.txt"
mode="${1:-}"

case "$mode" in
    staged) files=$(git diff --cached --name-only --diff-filter=ACMRD) ;;
    -)      files=$(cat) ;;
    "")     echo "Pemakaian: $0 staged | <base-ref> | -" >&2; exit 2 ;;
    *)      files=$(git diff --name-only --diff-filter=ACMRD "$mode"...HEAD) ;;
esac

patterns=()
while IFS= read -r line; do
    [[ -z "$line" || "$line" == \#* ]] && continue
    patterns+=("$line")
done < "$LIST"

hits=()
while IFS= read -r f; do
    [[ -z "$f" ]] && continue
    for p in "${patterns[@]}"; do
        # shellcheck disable=SC2053
        if [[ "$f" == $p ]]; then hits+=("$f"); break; fi
    done
done <<< "$files"

if (( ${#hits[@]} > 0 )); then
    echo "DITOLAK: perubahan menyentuh area terlarang (keuangan/stok/skema/infrastruktur):" >&2
    printf '  - %s\n' "${hits[@]}" >&2
    echo "Hapus perubahan tersebut dari branch ini dan laporkan ke pemilik repo (lihat AGENTS.md)." >&2
    exit 1
fi
echo "OK: tidak ada perubahan di area terlarang."
