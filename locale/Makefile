# Warning: do not transform tabs to spaces in this file.

all : translations

translations : */LC_MESSAGES/statusnet.mo

clean :
	rm -f */LC_MESSAGES/statusnet.mo

%.mo : %.po
	msgfmt -o $@ $<
