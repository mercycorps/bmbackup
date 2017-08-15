
Name: app-bmbackup
Epoch: 1
Version: 1.0.0
Release: 1%{dist}
Summary: Baremetal Backup And Restore
License: GPLv3
Group: ClearOS/Apps
Packager: Mercy Corps <mkhan@mercycorps.org>
Vendor: Mercy Corps <mkhan@mercycorps.org>
Source: %{name}-%{version}.tar.gz
Buildarch: noarch
Requires: %{name}-core = 1:%{version}-%{release}
Requires: app-base

%description
The Bare Metal Backup/Restore app saves and restores both users' home directories and the configuration settings to and from a USB disk that is initialized by the process below.

%package core
Summary: Baremetal Backup And Restore - Core
License: LGPLv3
Group: ClearOS/Libraries
Requires: app-base-core

%description core
The Bare Metal Backup/Restore app saves and restores both users' home directories and the configuration settings to and from a USB disk that is initialized by the process below.

This package provides the core API and libraries.

%prep
%setup -q
%build

%install
mkdir -p -m 755 %{buildroot}/usr/clearos/apps/bmbackup
cp -r * %{buildroot}/usr/clearos/apps/bmbackup/
rm -f %{buildroot}/usr/clearos/apps/bmbackup/README.md
install -d -m 755 %{buildroot}/etc/clearos/bmbackup.d
install -D -m 0644 packaging/backup.conf %{buildroot}/etc/clearos/bmbackup.d/backup.conf
install -D -m 0644 packaging/email.conf %{buildroot}/etc/clearos/bmbackup.d/email.conf
install -D -m 0644 packaging/usb.conf %{buildroot}/etc/clearos/bmbackup.d/usb.conf

%post
logger -p local6.notice -t installer 'app-bmbackup - installing'

%post core
logger -p local6.notice -t installer 'app-bmbackup-core - installing'

if [ $1 -eq 1 ]; then
    [ -x /usr/clearos/apps/bmbackup/deploy/install ] && /usr/clearos/apps/bmbackup/deploy/install
fi

[ -x /usr/clearos/apps/bmbackup/deploy/upgrade ] && /usr/clearos/apps/bmbackup/deploy/upgrade

exit 0

%preun
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-bmbackup - uninstalling'
fi

%preun core
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-bmbackup-core - uninstalling'
    [ -x /usr/clearos/apps/bmbackup/deploy/uninstall ] && /usr/clearos/apps/bmbackup/deploy/uninstall
fi

exit 0

%files
%defattr(-,root,root)
/usr/clearos/apps/bmbackup/controllers
/usr/clearos/apps/bmbackup/htdocs
/usr/clearos/apps/bmbackup/views

%files core
%defattr(-,root,root)
%doc README.md
%exclude /usr/clearos/apps/bmbackup/packaging
%dir /usr/clearos/apps/bmbackup
%dir %attr(755,webconfig,webconfig) /etc/clearos/bmbackup.d
/usr/clearos/apps/bmbackup/deploy
/usr/clearos/apps/bmbackup/language
/usr/clearos/apps/bmbackup/libraries
%attr(0644,webconfig,webconfig) %config(noreplace) /etc/clearos/bmbackup.d/backup.conf
%attr(0644,webconfig,webconfig) %config(noreplace) /etc/clearos/bmbackup.d/email.conf
%attr(0644,webconfig,webconfig) %config(noreplace) /etc/clearos/bmbackup.d/usb.conf
