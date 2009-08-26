cd `dirname $0`
cd ..
xgettext --from-code=UTF-8 --default-domain=statusnet --output=locale/statusnet.po --language=PHP --join-existing actions/*.php classes/*.php lib/*.php scripts/*.php
