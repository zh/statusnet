BuildRequires:	php-pear
BuildRequires:	httpd-devel

Name:           laconica
Version:        0.7.2
Release:        1%{?dist}
License:        GAGPL v3 or later
Source:         laconica-0.7.2.tar.gz
Group:          Applications/Internet
Summary:        Laconica, the Open Source microblogging platform
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
From the ABOUT file: Laconica (pronounced "luh-KAWN-ih-kuh") is a Free
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

mkdir -p %{buildroot}%{_datadir}/laconica
cp -a db %{buildroot}%{_datadir}/laconica/db

mkdir -p %{buildroot}%{_sysconfdir}/httpd/conf.d
cat > %{buildroot}%{_sysconfdir}/httpd/conf.d/laconica.conf <<"EOF"
Alias /laconica/ "/var/www/laconica/"

<Directory "/var/www/laconica">
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
%{_datadir}/laconica/*
%attr(-,apache,apache) %dir %{_datadir}/laconica/avatar
%doc COPYING README doc-src/*
%config(noreplace) %{_sysconfdir}/httpd/conf.d/laconica.conf

%changelog
* Wed Mar 03 2009 Zach Copley <zach@controlyourself.ca> - 0.7.2
- Changed version number to 0.7.2.

* Sat Feb 28 2009 Ken Sedgwick <ken@bonsai.com> - 0.7.1-1
- Modified RPM for Fedora.

* Thu Feb 13 2009 tuukka.pasanen@ilmi.fi
- packaged laconica version 0.7.1

* Wed Feb 04 2009 tuukka.pasanen@ilmi.fi
- packaged laconica version 0.7.0 using the buildservice spec file wizard
