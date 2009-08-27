# This version needs to match the tarball and unpacked directory name.
%define LACVER 0.7.3

BuildRequires:	php-pear
BuildRequires:	httpd-devel

Name:           statusnet
Version:        %{LACVER}
Release:        1%{?dist}
License:        GAGPL v3 or later
Source:         statusnet-%{version}.tar.gz
Group:          Applications/Internet
Summary:        StatusNet, the Open Source microblogging platform
BuildArch:      noarch

Requires:	httpd
Requires:	php >= 5
Requires:	php-pear-Mail-Mime
Requires:	php-curl
Requires:	php-mysql
Requires:	php-mbstring
Requires:	php-gettext
Requires:	php-xml
Requires:	php-gd

BuildRoot:      %{_tmppath}/%{name}-%{version}-build

%define apache_serverroot %(/usr/sbin/apxs -q DATADIR)
%define apache_sysconfdir %(/usr/sbin/apxs -q SYSCONFDIR)
%define wwwpath %{apache_serverroot}/%{name}
%define confpath %{_sysconfdir}/%{name}

%description
From the ABOUT file: StatusNet (pronounced "luh-KAWN-ih-kuh") is a Free
and Open Source microblogging platform. It helps people in a
community, company or group to exchange short (140 character) messages
over the Web. Users can choose which people to "follow" and receive
only their friends' or colleagues' status messages. It provides a
similar service to sites like Twitter, Jaiku, and Plurk. 


%prep
%setup -q

%build


%install
mkdir -p %{buildroot}%{wwwpath}
cp -a * %{buildroot}%{wwwpath}

mkdir -p %{buildroot}%{_datadir}/statusnet
cp -a db %{buildroot}%{_datadir}/statusnet/db

mkdir -p %{buildroot}%{_datadir}/statusnet/avatar

mkdir -p %{buildroot}%{_sysconfdir}/httpd/conf.d
cat > %{buildroot}%{_sysconfdir}/httpd/conf.d/statusnet.conf <<"EOF"
Alias /statusnet/ "/var/www/statusnet/"

<Directory "/var/www/statusnet">
    Options Indexes FollowSymLinks
    AllowOverride All
    Order allow,deny
    Allow from all
</Directory>
EOF

%clean
rm -rf %buildroot

%files
%defattr(-,root,root)
%dir %{wwwpath}
%{wwwpath}/*
%{_datadir}/statusnet/*
%attr(-,apache,apache) %dir %{_datadir}/statusnet/avatar
%doc COPYING README doc-src/*
%config(noreplace) %{_sysconfdir}/httpd/conf.d/statusnet.conf

%changelog
* Wed Apr 03 2009 Zach Copley <zach@status.net> - 0.7.3
- Changed version number to 0.7.3.

* Fri Mar 13 2009 Ken Sedgwick <ksedgwic@bonsai.com> - 0.7.2.1-1
- Factored statusnet version to the first line of the file.

* Wed Mar 03 2009 Zach Copley <zach@status.net> - 0.7.2
- Changed version number to 0.7.2.

* Sat Feb 28 2009 Ken Sedgwick <ken@bonsai.com> - 0.7.1-1
- Modified RPM for Fedora.

* Thu Feb 13 2009 tuukka.pasanen@ilmi.fi
- packaged statusnet version 0.7.1

* Wed Feb 04 2009 tuukka.pasanen@ilmi.fi
- packaged statusnet version 0.7.0 using the buildservice spec file wizard
