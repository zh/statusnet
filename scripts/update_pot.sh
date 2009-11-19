cd `dirname $0`
cd ..
xgettext \
    --from-code=UTF-8 \
    --default-domain=statusnet \
    --output=locale/statusnet.po \
    --language=PHP \
    --keyword="pgettext:1c,2" \
    --keyword="npgettext:1c,2,3" \
    actions/*.php \
    classes/*.php \
    lib/*.php \
    scripts/*.php
