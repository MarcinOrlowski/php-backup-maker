#!/bin/bash

CUR=`pwd`

rm -rf "/tmp/pdm_${1}"
mkdir -p "/tmp/pdm_${1}"
cd "/tmp/pdm_${1}"

TAG=`echo ${1} | tr . -`

cvs -d:pserver:anonymous@cvs.sourceforge.net:/cvsroot/pdm login
cvs -d:pserver:anonymous@cvs.sourceforge.net:/cvsroot/pdm export -r HEAD wfmh/php_backup_maker

mv wfmh/php_backup_maker/* .
rm -rf wfmh
rm -r rel.sh

cd ..

tar -zcvf "pdm-${1}.tgz" pdm_${1}
md5sum -b /tmp/pdm-${1}.tgz >/tmp/pdm-${1}.md5sum

lukemftp -d -u ftp://upload.sf.net/incoming/ pdm-${1}.md5sum  pdm-${1}.tgz

cd ${CUR}
