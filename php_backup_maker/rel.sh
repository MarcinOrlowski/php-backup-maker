#!/bin/bash


# USAGE: ./rel 3.1  (to release branch: release-3-1)

CUR=`pwd`

TAG=`echo ${1} | tr . -`

rm -rf "/tmp/pdm_${TAG}"
mkdir -p "/tmp/pdm_${TAG}"
cd "/tmp/pdm_${TAG}"

echo "Releasing PDM branch: release-${TAG}"

cvs -d:pserver:anonymous@cvs.sourceforge.net:/cvsroot/pdm login
cvs -d:pserver:anonymous@cvs.sourceforge.net:/cvsroot/pdm export -r release-${TAG} wfmh/php_backup_maker

mv wfmh/php_backup_maker/* .
rm -rf wfmh
rm -r rel.sh

cd ..

tar -zcvf "pdm-${1}.tgz" pdm_${TAG}
md5sum -b pdm-${1}.tgz >pdm-${1}.md5sum
echo ${1} >pdm-latest-version.txt

lukemftp -u ftp://upload.sf.net/incoming/ pdm-${1}.md5sum pdm-${1}.tgz
scp pdm-${1}.md5sum pdm-${1}.tgz pdm-latest-version.txt carlos@wfmh.org.pl:public_html/files/soft/

cd ${CUR}
