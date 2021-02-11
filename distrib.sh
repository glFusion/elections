#!/bin/sh
PI_NAME=${PWD##*/}
rsync -av admin/* ../../../public_html/admin/plugins/$PI_NAME/
rsync -av public_html/* ../../../public_html/$PI_NAME/
