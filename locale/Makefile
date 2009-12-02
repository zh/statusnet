# Warning: do not transform tabs to spaces in this file.

all : translations

trans = $(patsubst %.po,%.mo,$(wildcard */LC_MESSAGES/statusnet.po))

translations : $(trans)

clean :
	rm -f $(trans)

%.mo : %.po
	msgfmt -o $@ $<
