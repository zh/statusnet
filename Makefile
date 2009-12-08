# Warning: do not transform tabs to spaces in this file.

all : translations

core_mo = $(patsubst %.po,%.mo,$(wildcard locale/*/LC_MESSAGES/statusnet.po))
plugin_mo = $(patsubst %.po,%.mo,$(wildcard plugins/*/locale/*/LC_MESSAGES/*.po))

translations : $(core_mo) $(plugin_mo)

clean :
	rm -f $(core_mo) $(plugin_mo)

updatepo :
	php scripts/update_po_templates.php --all

%.mo : %.po
	msgfmt -o $@ $<

