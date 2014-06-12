
Name: app-bmbackup
Epoch: 1
Version: 1.0.0
Release: 1%{dist}
Summary: **bmbackup_app_name**
License: GPLv3
Group: ClearOS/Apps
Packager: Mercy Corps
Vendor: Mercy Corps
Source: %{name}-%{version}.tar.gz
Buildarch: noarch
Requires: %{name}-core = 1:%{version}-%{release}
Requires: app-base

%description
**bmbackup_app_description**

%package core
Summary: **bmbackup_app_name** - Core
License: LGPLv3
Group: ClearOS/Libraries
Requires: app-base-core

%description core
**bmbackup_app_description**

This package provides the core API and libraries.

%prep
%setup -q
%build

%install
mkdir -p -m 755 %{buildroot}/usr/clearos/apps/bmbackup
cp -r * %{buildroot}/usr/clearos/apps/bmbackup/

install -d -m 0755 %{buildroot}/etc/clearos/bmbackup.d
install -D -m 0644 packaging/backup.conf %{buildroot}/etc/clearos/bmbackup.d/backup.conf
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
%exclude /usr/clearos/apps/bmbackup/packaging
%dir /usr/clearos/apps/bmbackup
%dir /etc/clearos/bmbackup.d
/usr/clearos/apps/bmbackup/deploy
/usr/clearos/apps/bmbackup/language
/usr/clearos/apps/bmbackup/libraries
/etc/clearos/bmbackup.d/backup.conf
/etc/clearos/bmbackup.d/usb.conf
