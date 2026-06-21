#!/bin/bash

# Hapus paksa berkas pemicu konflik MPM ganda di runtime
rm -f /etc/apache2/mods-enabled/mpm_event.load
rm -f /etc/apache2/mods-enabled/mpm_event.conf

# Jalankan perintah utama bawaan apache
exec apache2-foreground