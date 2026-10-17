#!/bin/bash
# =============================================================================
# vps1-rpkd-nft.sh — tambah aturan nftables untuk jaringan Docker RPKD (br-rpkd-*)
# di tabel "inet hardening", di samping aturan PMB yang sudah ada.
#
#   - Di dalam tiap jaringan RPKD: diizinkan (sama dengan br-pmb-*, br-sia-*).
#   - Keluar ke internet: hanya dari br-rpkd-out, hanya TCP 443 dan 587 (SMTP).
#   - Masuk dari internet: TIDAK ada aturan baru. Situs dibuka lewat edge bersama
#     (port 443 yang sudah ada, hanya dari IP Cloudflare).
#
# /etc/nftables.conf diubah dengan menyisipkan baris setelah aturan PMB yang
# sama jenisnya; isi lain tidak disentuh, TIDAK ada "flush ruleset". Sintaks
# dicek dulu (nft -c). Pengaman: kalau tidak dikonfirmasi "YA" dalam 10 menit,
# file lama otomatis dikembalikan.
#
# Jalankan dari sesi SSH Tailscale: sudo bash vps1-rpkd-nft.sh
# Aman diulang (kalau aturan RPKD sudah ada, tidak ada yang diubah).
# =============================================================================
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo "Jalankan sebagai root (sudo)." >&2; exit 1; }
F=/etc/nftables.conf
D=/root/rpkd; install -d -m 0700 "$D"
WAKTU="$(date +%Y%m%d-%H%M%S)"

if grep -q 'br-rpkd-' "$F"; then
    echo "Aturan br-rpkd-* sudah ada di $F; tidak ada yang diubah."
    nft list chain inet hardening forward | grep 'br-rpkd-' | sed 's/^/  /'
    exit 0
fi

JANGKAR_DALAM='iifname "br-pmb-mgmt" oifname "br-pmb-mgmt" accept'
JANGKAR_KELUAR='iifname "br-pmb-out" oifname "eth0" tcp dport { 443, 587 } accept'
normal() { tr -s '[:space:]' ' ' < "$F"; }
for j in "$JANGKAR_DALAM" "$JANGKAR_KELUAR"; do
    [ "$(normal | grep -oF "$j" | wc -l)" = 1 ] \
        || { echo "FATAL: baris acuan tidak ditemukan tepat satu kali di $F: $j" >&2; exit 1; }
done

LAMA="$D/nftables.conf.sebelum-rpkd-$WAKTU"
BARU="$D/nftables.conf.rpkd-$WAKTU"
cp -p "$F" "$LAMA"
awk '
    { print }
    { n = $0; gsub(/[[:space:]]+/, " ", n); sub(/^ /, "", n) }
    n == "iifname \"br-pmb-mgmt\" oifname \"br-pmb-mgmt\" accept" {
        print ""
        print "        # RPKD (vps1-rpkd-nft.sh): di dalam tiap jaringan Docker RPKD."
        print "        iifname \"br-rpkd-edge\" oifname \"br-rpkd-edge\" accept"
        print "        iifname \"br-rpkd-back\" oifname \"br-rpkd-back\" accept"
        print "        iifname \"br-rpkd-out\"  oifname \"br-rpkd-out\"  accept"
        print "        iifname \"br-rpkd-mgmt\" oifname \"br-rpkd-mgmt\" accept"
    }
    n == "iifname \"br-pmb-out\" oifname \"eth0\" tcp dport { 443, 587 } accept" {
        print "        # RPKD: keluar hanya dari br-rpkd-out (SMTP notifikasi, HTTPS)."
        print "        iifname \"br-rpkd-out\"  oifname \"eth0\" tcp dport { 443, 587 } accept"
    }
' "$LAMA" > "$BARU"
[ "$(grep -cE '^[[:space:]]*iifname "br-rpkd-' "$BARU")" = 5 ] || { echo "FATAL: penyisipan tidak lengkap; $F tidak diubah." >&2; exit 1; }
nft -c -f "$BARU"
echo "Sintaks nftables baru: OK. Perubahan:"
diff -u "$LAMA" "$BARU" | sed -n '3,$p' | grep -E '^[+-]' | sed 's/^/  /' || true

cat > "$D/kembalikan-nft-rpkd.sh" <<EOF
#!/bin/sh
cp -p "$LAMA" /etc/nftables.conf && nft -f /etc/nftables.conf && systemctl start pmb-cloudflare-ips.service
logger "vps1-rpkd-nft: nftables dikembalikan ke versi sebelum RPKD"
EOF
chmod 700 "$D/kembalikan-nft-rpkd.sh"
systemctl stop rpkd-nft-rollback.timer 2>/dev/null || true
systemd-run --quiet --unit=rpkd-nft-rollback --on-active=10min /bin/sh "$D/kembalikan-nft-rpkd.sh"

install -m 0755 "$BARU" "$F"
nft -f "$F"
# Memuat ulang tabel mengisi set cloudflare_v4 dengan daftar awal di file;
# daftar terbaru diisi ulang sekarang.
systemctl start pmb-cloudflare-ips.service || echo "PERINGATAN: pmb-cloudflare-ips gagal; cek journalctl -u pmb-cloudflare-ips"
echo "nftables baru aktif. Pengaman: kembali otomatis dalam 10 menit kalau tidak dikonfirmasi."
echo
echo ">>> Buka sesi SSH (Tailscale) BARU dari laptop dan pastikan bisa login."
echo ">>> Pastikan juga devpmb dan sia masih terbuka di browser."
read -r -p ">>> Keduanya OK? ketik YA untuk mempertahankan aturan baru: " JAWAB
if [ "$JAWAB" != YA ]; then
    echo "Tidak dikonfirmasi. Mengembalikan nftables sekarang."
    systemctl stop rpkd-nft-rollback.timer 2>/dev/null || true
    sh "$D/kembalikan-nft-rpkd.sh"
    exit 1
fi
systemctl stop rpkd-nft-rollback.timer
echo "Dikonfirmasi; pengaman rollback dimatikan."
echo "Aturan RPKD di chain forward:"
nft list chain inet hardening forward | grep 'br-rpkd-' | sed 's/^/  /'
echo "Cadangan file lama: $LAMA"
