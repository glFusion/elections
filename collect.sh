#!/bin/sh
PI_NAME=${PWD##*/}
rsync -rav ../../../public_html/admin/plugins/${PI_NAME}/* admin/
rsync -rav ../../../public_html/${PI_NAME}/* public_html/
