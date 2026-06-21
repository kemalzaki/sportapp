#!/bin/bash

# Hapus paksa semua berkas load MPM event agar tidak bentrok dengan prefork saat runtime
rm -f /etc/apache2/mods-enabled/mpm_event.load
rm -f /etc/apache2/mods-enabled/mpm_event.conf

# Jalankan Apache bawaan di foreground
exec apache2-foreground