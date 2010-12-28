.fake: all clean

TARGETS=util.min.js
SOURCES=util.js xbImportNode.js geometa.js

all: $(TARGETS)

clean:
	rm -f $(TARGETS)

util.min.js: $(SOURCES)
	cat $+ | yui-compressor --type js > $@
