#!/bin/sh
# 2023 - m5kro

[[ -f /tmp/zerotier.progress ]] && {
  exit 0
}

touch /tmp/zerotier.progress

if [ "$1" = "install" ]; then
  if [ "$2" = "internal" ]; then
     opkg update
     opkg install kmod-tun
     opkg install libmnl
     opkg install ip-tiny
     opkg install libminiupnpc
     opkg install libnatpmp
     opkg install zerotier

# kmod-tun installed at root due to /dev/net/tun missing issues
  elif [ "$2" = "sd" ]; then
     opkg update
     ln -s /sd/modules/ZeroTier /pineapple/modules/
     opkg install kmod-tun
     opkg -d sd install libmnl
     opkg -d sd install ip-tiny
     opkg -d sd install libminiupnpc
     opkg -d sd install libnatpmp
     opkg -d sd install zerotier
     # god this took forever to fix
     cp /sd/etc/config/zerotier /etc/config/
     ln -s /sd/etc/init.d/zerotier /etc/init.d/
     ln -s /sd/usr/bin/zerotier-one /usr/bin/
     ln -s /sd/usr/lib/libminiupnpc.so.17 /lib/libminiupnpc.so.17
     ln -s /sd/usr/lib/libnatpmp.so.1 /lib/libnatpmp.so.1

  fi

  uci set zerotier.openwrt_network=zerotier
  uci add_list zerotier.openwrt_network.join=''
  uci set zerotier.openwrt_network.enabled='1'
  uci commit zerotier
  /etc/init.d/zerotier start
  /etc/init.d/zerotier stop
  /etc/init.d/zerotier disable

elif [ "$1" = "remove" ]; then
    opkg remove zerotier
    opkg remove kmod-tun
    opkg remove libmnl
    opkg remove ip-tiny
    opkg remove libminiupnpc
    opkg remove libnatpmp
    rm -rf /etc/config/zerotier
    rm -rf /sd/etc/config/zerotier
    rm -rf /var/lib/zerotier-one
fi

rm /tmp/zerotier.progress